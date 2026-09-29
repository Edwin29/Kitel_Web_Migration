<?php
/**
 * @class  homeworkAdminView
 * @brief  homework module admin view class (기술부 전용)
 */
class HomeworkAdminView extends Homework
{
	function init()
	{
		$template_path = sprintf('%stpl/', $this->module_path);
		$this->setTemplatePath($template_path);
	}

	/**
	 * @brief Task management list (default admin landing page)
	 */
	function dispHomeworkAdminContent()
	{
		$oHomeworkModel = getModel('homework');
		$tasks = $oHomeworkModel->getTaskList($this->module_info->module_srl);

		Context::set('tasks', $tasks);
		$this->setTemplateFile('task_list');
	}

	/**
	 * @brief Create/edit task form
	 */
	function dispHomeworkAdminForm()
	{
		$task_srl = Context::get('task_srl');
		$task = null;
		if ($task_srl)
		{
			$oHomeworkModel = getModel('homework');
			$task = $oHomeworkModel->getTask($task_srl);
			if (!$task || (int) $task->module_srl !== (int) $this->module_info->module_srl)
			{
				throw new Rhymix\Framework\Exceptions\TargetNotFound;
			}
		}
		Context::set('task', $task);
		$this->setTemplateFile('task_form');
	}

	/**
	 * @brief 과제 × 준회원 제출현황 매트릭스
	 */
	function dispHomeworkAdminDashboard()
	{
		$oHomeworkModel = getModel('homework');
		$module_srl = $this->module_info->module_srl;

		$tasks = $oHomeworkModel->getTaskList($module_srl);
		$selected_task = null;
		$requested_task_srl = (int) Context::get('task_srl');
		foreach ($tasks as $task)
		{
			if (!$selected_task || (int) $task->task_srl === $requested_task_srl)
			{
				$selected_task = $task;
			}
		}
		$members = $oHomeworkModel->getJuniorMembers();
		$submissions = $oHomeworkModel->getSubmissionsByModule($module_srl);

		// Index submissions by "task_srl:member_srl" for O(1) lookup while building the grid
		$submission_map = array();
		foreach ($submissions as $submission)
		{
			$key = $submission->task_srl . ':' . $submission->member_srl;
			$submission_map[$key] = $submission;
		}

		$rows = array();
		foreach ($members as $member)
		{
			$row = new stdClass;
			$row->member = $member;
			$row->cells = array();
			foreach ($tasks as $task)
			{
				$key = $task->task_srl . ':' . $member->member_srl;
				$row->cells[] = isset($submission_map[$key]) ? $submission_map[$key] : null;
			}
			$rows[] = $row;
		}

		Context::set('tasks', $tasks);
		Context::set('rows', $rows);
		$selected_rows = array();
		$submitted_count = 0;
		foreach ($rows as $row)
		{
			foreach ($tasks as $index => $task)
			{
				if ($selected_task && (int) $task->task_srl === (int) $selected_task->task_srl)
				{
					$selected_rows[] = (object) array('member' => $row->member, 'submission' => $row->cells[$index]);
					if ($row->cells[$index])
					{
						$submitted_count++;
					}
					break;
				}
			}
		}
		Context::set('selected_task', $selected_task);
		Context::set('selected_rows', $selected_rows);
		Context::set('submitted_count', $submitted_count);
		Context::set('missing_count', count($rows) - $submitted_count);
		Context::set('days_remaining', $selected_task && $selected_task->deadline ? (int) (new DateTimeImmutable(substr($selected_task->deadline, 0, 8)))->diff(new DateTimeImmutable('today'))->format('%r%a') * -1 : null);
		$this->setTemplateFile('dashboard');
	}

	/**
	 * @brief Permission settings (reuses the shared module grant editor)
	 */
	function dispHomeworkAdminGrantInfo()
	{
		$oModuleAdminModel = getAdminModel('module');
		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);

		$this->setTemplateFile('grant_list');
	}
}
/* End of file homework.admin.view.php */
