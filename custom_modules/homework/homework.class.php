<?php
/**
 * @class  homework
 * @brief  The parent class of the homework (과제게시판) module
 */
class Homework extends ModuleObject
{
	const JUNIOR_GROUP_SRL = 3; // 준회원

	function moduleInstall()
	{
		$config = new stdClass;
		$config->skin = 'default';
		$oModuleController = ModuleController::getInstance();
		$oModuleController->insertModuleConfig('homework', $config);
	}

	function checkUpdate()
	{
		$db = DB::getInstance();
		foreach (array('answer_fields', 'allowed_extensions', 'description_image', 'is_visible') as $column)
		{
			if (!$db->isColumnExists('homework_task', $column)) return true;
		}
		return !$db->isColumnExists('homework_submission', 'answers');
	}

	function moduleUpdate()
	{
		$db = DB::getInstance();
		if (!$db->isColumnExists('homework_task', 'answer_fields')) $db->addColumn('homework_task', 'answer_fields', 'text');
		if (!$db->isColumnExists('homework_task', 'allowed_extensions')) $db->addColumn('homework_task', 'allowed_extensions', 'varchar', 250);
		if (!$db->isColumnExists('homework_task', 'description_image')) $db->addColumn('homework_task', 'description_image', 'varchar', 250);
		if (!$db->isColumnExists('homework_task', 'is_visible')) $db->addColumn('homework_task', 'is_visible', 'char', 1, 'Y', true);
		if (!$db->isColumnExists('homework_submission', 'answers')) $db->addColumn('homework_submission', 'answers', 'text');
	}

	public static function getAnswerFields($task)
	{
		$fields = json_decode($task->answer_fields ?? '', true);
		return is_array($fields) ? $fields : array();
	}

	public static function getAnswers($submission)
	{
		$answers = json_decode($submission->answers ?? '', true);
		return is_array($answers) ? $answers : array();
	}
}
/* End of file homework.class.php */
