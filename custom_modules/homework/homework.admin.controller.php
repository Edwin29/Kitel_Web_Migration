<?php
/**
 * @class  homeworkAdminController
 * @brief  homework module admin controller class (기술부 전용)
 */
class HomeworkAdminController extends Homework
{
	function init()
	{
	}

	/**
	 * @brief Create or update a task (task_srl present = update)
	 */
	function procHomeworkAdminInsertTask()
	{
		$task_srl = (int) Context::get('task_srl');
		$title = trim(Context::get('title'));
		$description = Context::get('description');
		$field_ids = Context::get('field_id');
		$field_titles = Context::get('field_title');
		$field_heights = Context::get('field_height');
		$fields = array();
		if ($field_titles !== null)
		{
			if (!is_array($field_titles) || count($field_titles) > 20 || !is_array($field_ids) || !is_array($field_heights) || count($field_ids) !== count($field_titles) || count($field_heights) !== count($field_titles))
			{
				throw new Rhymix\Framework\Exceptions\InvalidRequest;
			}
			$seen = array();
			foreach ($field_titles as $i => $field_title)
			{
				$field_title = trim((string)$field_title);
				if ($field_title === '' || mb_strlen($field_title) > 120) throw new Rhymix\Framework\Exceptions\InvalidRequest;
				$id = (string)$field_ids[$i];
				if (!preg_match('/^[a-zA-Z0-9_-]{8,40}$/', $id) || isset($seen[$id])) throw new Rhymix\Framework\Exceptions\InvalidRequest;
				$seen[$id] = true;
				$height = (int)$field_heights[$i];
				$fields[] = array('id' => $id, 'title' => $field_title, 'height' => max(120, min(900, $height)));
			}
		}
		$extensions = strtolower(trim((string)Context::get('allowed_extensions')));
		$extensions = preg_split('/[\s,]+/', $extensions, -1, PREG_SPLIT_NO_EMPTY);
		if (count($extensions) > 30) throw new Rhymix\Framework\Exceptions\InvalidRequest;
		foreach ($extensions as $extension)
		{
			if (!preg_match('/^[a-z0-9]{1,12}$/', $extension)) throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		$extensions = implode(',', array_unique($extensions));
		$deadline_date = Context::get('deadline');
		if ($deadline_date)
		{
			$date = DateTimeImmutable::createFromFormat('!Y-m-d', $deadline_date);
			if (!$date || $date->format('Y-m-d') !== $deadline_date)
			{
				throw new Rhymix\Framework\Exceptions\InvalidRequest;
			}
		}
		$deadline = $deadline_date ? (str_replace('-', '', $deadline_date) . '235959') : '';

		if ($title === '')
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$logged_info = Context::get('logged_info');

		$args = new stdClass;
		$args->title = $title;
		$args->description = $description;
		$args->answer_fields = json_encode($fields, JSON_UNESCAPED_UNICODE);
		$args->allowed_extensions = $extensions;
		$args->deadline = $deadline;
		$existing = null;

		if ($task_srl)
		{
			$existing = getModel('homework')->getTask($task_srl);
			if (!$existing || (int) $existing->module_srl !== (int) $this->module_info->module_srl)
			{
				throw new Rhymix\Framework\Exceptions\InvalidRequest;
			}
			$args->task_srl = $task_srl;
			// The image is stored only after the task row has passed validation.
		}
		else
		{
			$args->task_srl = getNextSequence();
			$args->module_srl = $this->module_info->module_srl;
			$args->member_srl = $logged_info->member_srl;
		}
		$image = Context::get('description_image_upload');
		if ($image && !empty($image['error']) && (int)$image['error'] !== UPLOAD_ERR_NO_FILE)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest('설명 이미지 업로드에 실패했습니다. 파일 크기를 확인해 주세요.');
		}
		$new_image = null;
		if ($image && !empty($image['tmp_name']) && is_uploaded_file($image['tmp_name']))
		{
			if ($image['size'] > 10 * 1024 * 1024) throw new Rhymix\Framework\Exceptions\InvalidRequest('설명 이미지는 10MB 이하로 첨부해 주세요.');
			$mime = (new finfo(FILEINFO_MIME_TYPE))->file($image['tmp_name']);
			$types = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');
			if (!isset($types[$mime]) || !getimagesize($image['tmp_name'])) throw new Rhymix\Framework\Exceptions\InvalidRequest;
			$new_image = $args->task_srl . '_' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
			$dir = './files/attach/homework/' . $this->module_info->module_srl . '/task-images/';
			FileHandler::makeDir($dir);
			if (!move_uploaded_file($image['tmp_name'], FileHandler::getRealPath($dir . $new_image))) throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		$args->description_image = $new_image ?: ($existing->description_image ?? '');
		$output = $task_srl ? executeQuery('homework.updateTask', $args) : executeQuery('homework.insertTask', $args);

		if (!$output->toBool())
		{
			if ($new_image) Rhymix\Framework\Storage::delete(FileHandler::getRealPath($dir . $new_image));
			return $output;
		}
		if ($new_image && $existing && $existing->description_image && basename($existing->description_image) === $existing->description_image)
		{
			$old_path = FileHandler::getRealPath($dir . $existing->description_image);
			if (Rhymix\Framework\Storage::isFile($old_path)) Rhymix\Framework\Storage::delete($old_path);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHomeworkAdminContent', 'mid', Context::get('mid')));
	}

	/**
	 * @brief Delete a task and every submission under it, including attached files
	 */
	function procHomeworkAdminDeleteTask()
	{
		$task_srl = (int) Context::get('task_srl');
		$oHomeworkModel = getModel('homework');
		$task = $oHomeworkModel->getTask($task_srl);
		if (!$task || (int) $task->module_srl !== (int) $this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$args = new stdClass;
		$args->task_srl = $task_srl;
		$submissions = $oHomeworkModel->getSubmissionsByTask($task_srl);
		$deleted_submissions = executeQuery('homework.deleteSubmissionsByTask', $args);
		if (!$deleted_submissions->toBool())
		{
			return $deleted_submissions;
		}
		$deleted_task = executeQuery('homework.deleteTask', $args);
		if (!$deleted_task->toBool())
		{
			return $deleted_task;
		}
		foreach ($submissions as $submission)
		{
			if (!$submission->stored_filename || basename($submission->stored_filename) !== $submission->stored_filename)
			{
				continue;
			}
			$path = FileHandler::getRealPath('./files/attach/homework/' . $this->module_info->module_srl . '/' . $submission->stored_filename);
			if (Rhymix\Framework\Storage::isFile($path))
			{
				Rhymix\Framework\Storage::delete($path);
			}
		}
		if ($task->description_image && basename($task->description_image) === $task->description_image)
		{
			$image_path = FileHandler::getRealPath('./files/attach/homework/' . $this->module_info->module_srl . '/task-images/' . $task->description_image);
			if (Rhymix\Framework\Storage::isFile($image_path)) Rhymix\Framework\Storage::delete($image_path);
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHomeworkAdminContent', 'mid', Context::get('mid')));
	}
}
/* End of file homework.admin.controller.php */
