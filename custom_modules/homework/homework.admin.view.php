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
		$page = max(1, (int)Context::get('page'));
		$output = $oHomeworkModel->getTaskPage($this->module_info->module_srl, $page);
		if (!$output->toBool()) return $output;
		if ($output->page_navigation && $page > $output->page_navigation->last_page && $output->page_navigation->last_page > 0)
		{
			$page = $output->page_navigation->last_page;
			$output = $oHomeworkModel->getTaskPage($this->module_info->module_srl, $page);
			if (!$output->toBool()) return $output;
		}
		$tasks = is_array($output->data) ? $output->data : array();

		Context::set('tasks', $tasks);
		Context::set('page', $page);
		Context::set('page_navigation', $output->page_navigation);
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
		Context::set('answer_fields', $task ? self::getAnswerFields($task) : array());
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
		$task_map = array();
		foreach ($tasks as $task) $task_map[(int)$task->task_srl] = $task;

		// Index submissions by "task_srl:member_srl" for O(1) lookup while building the grid
		$submission_map = array();
		foreach ($submissions as $submission)
		{
			if (!isset($task_map[(int)$submission->task_srl])) continue;
			$submission->dashboard_status = self::getDashboardSubmissionStatus($submission, $task_map[(int)$submission->task_srl]);
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
		$status_counts = array('submitted' => 0, 'missing' => 0, 'late' => 0);
		foreach ($rows as $row)
		{
			foreach ($tasks as $index => $task)
			{
				if ($selected_task && (int) $task->task_srl === (int) $selected_task->task_srl)
				{
					$submission = $row->cells[$index];
					$status = $submission ? $submission->dashboard_status : 'missing';
					$selected_rows[] = (object) array('member' => $row->member, 'submission' => $submission, 'status' => $status);
					$status_counts[$status]++;
					break;
				}
			}
		}
		Context::set('selected_task', $selected_task);
		$status_filter = (string)Context::get('member_status');
		if (!in_array($status_filter, array('submitted', 'missing', 'late'), true)) $status_filter = 'all';
		$filtered_selected_rows = array();
		foreach ($selected_rows as $selected_row)
		{
			if ($status_filter === 'all' || $selected_row->status === $status_filter) $filtered_selected_rows[] = $selected_row;
		}
		Context::set('selected_rows', $filtered_selected_rows);
		Context::set('status_filter', $status_filter);
		Context::set('status_counts', $status_counts);

		$matrix_search_target = Context::get('matrix_search_target') === 'task' ? 'task' : 'nickname';
		$matrix_keyword = mb_substr(trim((string)Context::get('matrix_keyword')), 0, 100);
		$matrix_tasks = array();
		$matrix_task_indexes = array();
		foreach ($tasks as $index => $task)
		{
			if ($matrix_keyword !== '' && $matrix_search_target === 'task' && mb_stripos($task->title, $matrix_keyword) === false) continue;
			$matrix_tasks[] = $task;
			$matrix_task_indexes[] = $index;
		}
		$matrix_rows = array();
		if ($matrix_tasks)
		{
			foreach ($rows as $row)
			{
				if ($matrix_keyword !== '' && $matrix_search_target === 'nickname' && mb_stripos($row->member->nick_name, $matrix_keyword) === false) continue;
				$matrix_row = new stdClass;
				$matrix_row->member = $row->member;
				$matrix_row->cells = array();
				foreach ($matrix_task_indexes as $index) $matrix_row->cells[] = $row->cells[$index];
				$matrix_rows[] = $matrix_row;
			}
		}
		$matrix_page_count = max(1, (int)ceil(count($matrix_rows) / 6));
		$matrix_page = min($matrix_page_count, max(1, (int)Context::get('matrix_page')));
		$matrix_page_start = max(1, min($matrix_page - 2, $matrix_page_count - 4));
		$matrix_page_numbers = range($matrix_page_start, min($matrix_page_count, $matrix_page_start + 4));
		Context::set('matrix_tasks', $matrix_tasks);
		Context::set('matrix_rows', array_slice($matrix_rows, ($matrix_page - 1) * 6, 6));
		Context::set('matrix_total_rows', count($matrix_rows));
		Context::set('matrix_page', $matrix_page);
		Context::set('matrix_page_count', $matrix_page_count);
		Context::set('matrix_page_numbers', $matrix_page_numbers);
		Context::set('matrix_search_target', $matrix_search_target);
		Context::set('matrix_keyword', $matrix_keyword);
		Context::set('days_remaining', $selected_task && $selected_task->deadline ? (int) (new DateTimeImmutable(substr($selected_task->deadline, 0, 8)))->diff(new DateTimeImmutable('today'))->format('%r%a') * -1 : null);
		Context::set('show_deadline_banner', false);
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
