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

		$page = max(1, (int)Context::get('page'));
		$output = $oHomeworkModel->getTaskPage($module_srl, $page, 10, true);
		if (!$output->toBool()) return $output;
		if ($output->page_navigation && $page > $output->page_navigation->last_page && $output->page_navigation->last_page > 0)
		{
			$page = $output->page_navigation->last_page;
			$output = $oHomeworkModel->getTaskPage($module_srl, $page, 10, true);
			if (!$output->toBool()) return $output;
		}
		$tasks = is_array($output->data) ? $output->data : array();

		if ($this->grant->submit ?? false)
		{
			foreach ($tasks as $task)
			{
				$submission = $oHomeworkModel->getSubmissionByMember($task->task_srl, $logged_info->member_srl);
				$task->my_status = $submission ? '제출완료' : '미제출';
				$task->has_submission = (bool)$submission;
				$task->is_past_deadline = $task->deadline && $task->deadline < date('YmdHis');
			}
		}

		Context::set('tasks', $tasks);
		Context::set('page', $page);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('is_submitter', $this->grant->submit ?? false);
		Context::set('can_view_all', $this->grant->view_all ?? false);
		Context::set('can_manage', $this->grant->create ?? false);

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
		if (($task->is_visible ?? 'Y') === 'N' && !($this->grant->create ?? false))
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
				$answers = self::getAnswers($submission);
				$parts = array($submission->content);
				foreach (self::getAnswerFields($task) as $field)
				{
					if (isset($answers[$field['id']])) $parts[] = $field['title'] . ': ' . $answers[$field['id']];
				}
				$submission->display_content = trim(implode("\n", $parts));
			}
		}

		Context::set('task', $task);
		Context::set('answer_fields', self::getAnswerFields($task));
		Context::set('my_answers', $my_submission ? self::getAnswers($my_submission) : array());
		Context::set('allowed_accept', $task->allowed_extensions ? '.' . str_replace(',', ',.', $task->allowed_extensions) : '');
		Context::set('show_deadline_banner', false);
		Context::set('my_submission', $my_submission);
		Context::set('all_submissions', $all_submissions);
		Context::set('is_submitter', $this->grant->submit ?? false);
		Context::set('can_view_all', $this->grant->view_all ?? false);
		Context::set('can_manage', $this->grant->create ?? false);
		Context::set('is_past_deadline', $task->deadline && $task->deadline < date('YmdHis'));
		$days_remaining = $task->deadline ? (int) (new DateTimeImmutable(substr($task->deadline, 0, 8)))->diff(new DateTimeImmutable('today'))->format('%r%a') * -1 : null;
		Context::set('days_remaining', $days_remaining);

		$this->setTemplateFile('view');
	}
}
/* End of file homework.view.php */
