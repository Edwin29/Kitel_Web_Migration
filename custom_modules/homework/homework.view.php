<?php
/**
 * @class  homeworkView
 * @brief  homework module view class (public-facing)
 */
class HomeworkView extends Homework
{
	function init()
	{
	}

	/**
	 * @brief Task list
	 */
	function dispHomeworkIndex()
	{
		$module_srl = $this->module_info->module_srl;
		$oHomeworkModel = getModel('homework');
		$logged_info = Context::get('logged_info');

		$tasks = $oHomeworkModel->getTaskList($module_srl);

		if ($this->grant->submit ?? false)
		{
			foreach ($tasks as $task)
			{
				$submission = $oHomeworkModel->getSubmissionByMember($task->task_srl, $logged_info->member_srl);
				$task->my_status = $submission ? ($submission->is_late === 'Y' ? '제출완료(지각)' : '제출완료') : '미제출';
			}
		}

		Context::set('tasks', $tasks);
		Context::set('is_submitter', $this->grant->submit ?? false);
		Context::set('can_view_all', $this->grant->view_all ?? false);

		$this->setTemplateFile('index');
	}

	/**
	 * @brief Task detail + submission form (for 준회원) or full submitter list (for 정회원 이상)
	 */
	function dispHomeworkView()
	{
		$task_srl = (int) Context::get('task_srl');
		$oHomeworkModel = getModel('homework');
		$task = $oHomeworkModel->getTask($task_srl);
		if (!$task || (int) $task->module_srl !== (int) $this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound;
		}

		$logged_info = Context::get('logged_info');
		$my_submission = null;
		if ($this->grant->submit ?? false)
		{
			$my_submission = $oHomeworkModel->getSubmissionByMember($task_srl, $logged_info->member_srl);
		}

		$all_submissions = array();
		if ($this->grant->view_all ?? false)
		{
			$all_submissions = $oHomeworkModel->getSubmissionsByTask($task_srl);
			foreach ($all_submissions as $submission)
			{
				$member = MemberModel::getMemberInfoByMemberSrl($submission->member_srl);
				$submission->nick_name = $member ? $member->nick_name : ('#' . $submission->member_srl);
			}
		}

		Context::set('task', $task);
		Context::set('my_submission', $my_submission);
		Context::set('all_submissions', $all_submissions);
		Context::set('is_submitter', $this->grant->submit ?? false);
		Context::set('can_view_all', $this->grant->view_all ?? false);
		Context::set('is_past_deadline', $task->deadline && $task->deadline < date('YmdHis'));

		$this->setTemplateFile('view');
	}
}
/* End of file homework.view.php */
