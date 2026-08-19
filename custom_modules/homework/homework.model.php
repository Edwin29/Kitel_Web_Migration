<?php
/**
 * @class  homeworkModel
 * @brief  homework module model class
 */
class HomeworkModel extends Homework
{
	function getTaskList($module_srl)
	{
		$args = new stdClass;
		$args->module_srl = $module_srl;

		$output = executeQueryArray('homework.getTaskList', $args);
		return (is_array($output->data)) ? $output->data : array();
	}

	function getTask($task_srl)
	{
		$args = new stdClass;
		$args->task_srl = $task_srl;

		$output = executeQuery('homework.getTask', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	function getSubmissionByMember($task_srl, $member_srl)
	{
		$args = new stdClass;
		$args->task_srl = $task_srl;
		$args->member_srl = $member_srl;

		$output = executeQuery('homework.getSubmissionByMember', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	function getSubmission($submission_srl)
	{
		$args = new stdClass;
		$args->submission_srl = $submission_srl;

		$output = executeQuery('homework.getSubmission', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	function getSubmissionsByTask($task_srl)
	{
		$args = new stdClass;
		$args->task_srl = $task_srl;

		$output = executeQueryArray('homework.getSubmissionsByTask', $args);
		return (is_array($output->data)) ? $output->data : array();
	}

	function getSubmissionsByModule($module_srl)
	{
		$args = new stdClass;
		$args->module_srl = $module_srl;

		$output = executeQueryArray('homework.getSubmissionsByModule', $args);
		return (is_array($output->data)) ? $output->data : array();
	}

	/**
	 * @brief All members in the 준회원(junior) group — the dashboard's row axis
	 */
	function getJuniorMembers()
	{
		$args = new stdClass;
		$args->selected_group_srl = array(self::JUNIOR_GROUP_SRL);

		$output = executeQueryArray('member.getMemberListWithinGroup', $args);
		return (is_array($output->data)) ? $output->data : array();
	}
}
/* End of file homework.model.php */
