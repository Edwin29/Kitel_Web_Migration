<?php
/**
 * @class  calendar
 * @brief  The parent class of the calendar module
 */
class Calendar extends ModuleObject
{
	/**
	 * @brief Additional tasks required to accomplish during the installation
	 */
	function moduleInstall()
	{
		$config = new stdClass;
		$config->skin = 'default';
		$oModuleController = ModuleController::getInstance();
		$oModuleController->insertModuleConfig('calendar', $config);
	}

	/**
	 * @brief Check if the installation has been successful
	 */
	function checkUpdate()
	{
		return false;
	}

	/**
	 * @brief Execute update
	 */
	function moduleUpdate()
	{
	}
}
/* End of file calendar.class.php */
