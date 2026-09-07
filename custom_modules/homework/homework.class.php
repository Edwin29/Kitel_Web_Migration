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
		return false;
	}

	function moduleUpdate()
	{
	}
}
/* End of file homework.class.php */
