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
		$deadline_date = Context::get('deadline');
		$deadline = $deadline_date ? (str_replace('-', '', $deadline_date) . '235959') : '';

		if ($title === '')
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$logged_info = Context::get('logged_info');

		$args = new stdClass;
		$args->title = $title;
		$args->description = $description;
		$args->deadline = $deadline;

		if ($task_srl)
		{
			$args->task_srl = $task_srl;
			$output = executeQuery('homework.updateTask', $args);
		}
		else
		{
			$args->task_srl = getNextSequence();
			$args->module_srl = $this->module_info->module_srl;
			$args->member_srl = $logged_info->member_srl;
			$output = executeQuery('homework.insertTask', $args);
		}

		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHomeworkAdminContent', 'mid', Context::get('mid')));
	}

	/**
	 * @brief Delete a task and every submission under it (DB rows; physical files are left for manual cleanup)
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
		executeQuery('homework.deleteSubmissionsByTask', $args);
		executeQuery('homework.deleteTask', $args);

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHomeworkAdminContent', 'mid', Context::get('mid')));
	}
}
/* End of file homework.admin.controller.php */
