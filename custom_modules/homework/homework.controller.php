<?php
/**
 * @class  homeworkController
 * @brief  homework module controller class (public-facing actions)
 */
class HomeworkController extends Homework
{
	function init()
	{
	}

	private function getStorageDir()
	{
		return './files/attach/homework/' . $this->module_info->module_srl . '/';
	}

	private function deleteStoredFile($filename)
	{
		if (!$filename || basename($filename) !== $filename)
		{
			return;
		}
		$path = FileHandler::getRealPath($this->getStorageDir() . $filename);
		if (Rhymix\Framework\Storage::isFile($path))
		{
			Rhymix\Framework\Storage::delete($path);
		}
	}

	/**
	 * @brief Submit or resubmit (upsert keyed by task_srl + member_srl)
	 */
	function procHomeworkSubmit()
	{
		$task_srl = (int) Context::get('task_srl');
		$content = trim(Context::get('content'));

		$oHomeworkModel = getModel('homework');
		$task = $oHomeworkModel->getTask($task_srl);
		if (!$task || (int) $task->module_srl !== (int) $this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		if (($task->is_visible ?? 'Y') === 'N' && !($this->grant->create ?? false))
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound;
		}

		$logged_info = Context::get('logged_info');
		$posted_answers = Context::get('answer');
		if ($posted_answers !== null && !is_array($posted_answers)) throw new Rhymix\Framework\Exceptions\InvalidRequest;
		$answers = array();
		foreach (self::getAnswerFields($task) as $field)
		{
			$id = $field['id'];
			$value = $posted_answers[$id] ?? '';
			if (!is_string($value)) throw new Rhymix\Framework\Exceptions\InvalidRequest;
			$answers[$id] = trim($value);
		}
		$modified_at = date('YmdHis');
		$is_late = ($task->deadline && $task->deadline < $modified_at) ? 'Y' : 'N';

		$source_filename = null;
		$stored_filename = null;
		$file_info = Context::get('Filedata');
		if ($file_info && !empty($file_info['error']) && (int)$file_info['error'] !== UPLOAD_ERR_NO_FILE)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest('첨부파일 업로드에 실패했습니다. 파일 크기를 확인해 주세요.');
		}
		if ($file_info && is_uploaded_file($file_info['tmp_name']))
		{
			$allowed = array_filter(explode(',', (string)($task->allowed_extensions ?? '')));
			$extension = strtolower(pathinfo((string)$file_info['name'], PATHINFO_EXTENSION));
			if ($allowed && !in_array($extension, $allowed, true)) throw new Rhymix\Framework\Exceptions\InvalidRequest('이 과제에서 허용하지 않는 첨부파일 형식입니다.');
			$submission_srl_for_file = getNextSequence();
			$safe_name = preg_replace('/[^a-zA-Z0-9._\-가-힣]/u', '_', $file_info['name']);
			$stored_filename = $submission_srl_for_file . '_' . $safe_name;

			$storage_dir = $this->getStorageDir();
			FileHandler::makeDir($storage_dir);

			$target = FileHandler::getRealPath($storage_dir . $stored_filename);
			if (move_uploaded_file($file_info['tmp_name'], $target))
			{
				$source_filename = $file_info['name'];
			}
			else
			{
				$stored_filename = null;
			}
		}

		$existing = $oHomeworkModel->getSubmissionByMember($task_srl, $logged_info->member_srl);

		$args = new stdClass;
		$args->content = $content;
		$args->answers = json_encode($answers, JSON_UNESCAPED_UNICODE);
		$args->is_late = $is_late;
		$args->regdate = $modified_at;
		if ($source_filename)
		{
			$args->source_filename = $source_filename;
			$args->stored_filename = $stored_filename;
		}

		if ($existing)
		{
			// Keep the previous file if no new one was uploaded this time
			if (!$source_filename)
			{
				$args->source_filename = $existing->source_filename;
				$args->stored_filename = $existing->stored_filename;
			}
			$args->submission_srl = $existing->submission_srl;
			$output = executeQuery('homework.updateSubmission', $args);
		}
		else
		{
			$args->submission_srl = getNextSequence();
			$args->module_srl = $this->module_info->module_srl;
			$args->task_srl = $task_srl;
			$args->member_srl = $logged_info->member_srl;
			$output = executeQuery('homework.insertSubmission', $args);
		}

		if (!$output->toBool())
		{
			if ($stored_filename)
			{
				$this->deleteStoredFile($stored_filename);
			}
			return $output;
		}
		if ($existing && $stored_filename && $existing->stored_filename && $existing->stored_filename !== $stored_filename)
		{
			$this->deleteStoredFile($existing->stored_filename);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispHomeworkView', 'mid', Context::get('mid'), 'task_srl', $task_srl));
	}

	/** Serve assignment artwork through the same list grant as its description. */
	function procHomeworkTaskImage()
	{
		$task = getModel('homework')->getTask((int)Context::get('task_srl'));
		if (!$task || (int)$task->module_srl !== (int)$this->module_info->module_srl || !$task->description_image || basename($task->description_image) !== $task->description_image)
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound;
		}
		if (($task->is_visible ?? 'Y') === 'N' && !($this->grant->create ?? false)) throw new Rhymix\Framework\Exceptions\TargetNotFound;
		$path = FileHandler::getRealPath($this->getStorageDir() . 'task-images/' . $task->description_image);
		if (!Rhymix\Framework\Storage::isFile($path)) throw new Rhymix\Framework\Exceptions\TargetNotFound;
		$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
		if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp', 'image/gif'), true)) throw new Rhymix\Framework\Exceptions\TargetNotFound;
		header('Content-Type: ' . $mime);
		header('X-Content-Type-Options: nosniff');
		readfile($path);
		exit;
	}

	/**
	 * @brief Download a submission's attached file.
	 * Allowed for: the submitter themselves, or anyone with the view_all grant.
	 */
	function procHomeworkDownloadSubmission()
	{
		$submission_srl = (int) Context::get('submission_srl');
		$oHomeworkModel = getModel('homework');
		$submission = $oHomeworkModel->getSubmission($submission_srl);
		if (!$submission || (int) $submission->module_srl !== (int) $this->module_info->module_srl || !$submission->stored_filename)
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound;
		}
		$task = $oHomeworkModel->getTask($submission->task_srl);
		if (!$task || (int)$task->module_srl !== (int)$this->module_info->module_srl || (($task->is_visible ?? 'Y') === 'N' && !($this->grant->create ?? false))) throw new Rhymix\Framework\Exceptions\TargetNotFound;

		$logged_info = Context::get('logged_info');
		$is_owner = $logged_info && (int) $submission->member_srl === (int) $logged_info->member_srl;
		if (!$is_owner && !($this->grant->view_all ?? false))
		{
			throw new Rhymix\Framework\Exceptions\NotPermitted;
		}

		$path = FileHandler::getRealPath($this->getStorageDir() . $submission->stored_filename);
		if (!Rhymix\Framework\Storage::isFile($path))
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound;
		}

		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . rawurlencode($submission->source_filename) . '"');
		header('Content-Length: ' . filesize($path));
		header('Pragma: public');
		readfile($path);
		exit;
	}
}
/* End of file homework.controller.php */
