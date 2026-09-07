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

		$logged_info = Context::get('logged_info');
		$is_late = ($task->deadline && $task->deadline < date('YmdHis')) ? 'Y' : 'N';

		$source_filename = null;
		$stored_filename = null;
		$file_info = Context::get('Filedata');
		if ($file_info && is_uploaded_file($file_info['tmp_name']))
		{
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
		$args->is_late = $is_late;
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
			return $output;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispHomeworkView', 'mid', Context::get('mid'), 'task_srl', $task_srl));
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
