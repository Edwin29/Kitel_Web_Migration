<?php
/**
 * @class  archive
 * @brief  The parent class of the archive (자료실) module
 */
class Archive extends ModuleObject
{
	function moduleInstall()
	{
		$config = new stdClass;
		$config->skin = 'default';
		$oModuleController = ModuleController::getInstance();
		$oModuleController->insertModuleConfig('archive', $config);
	}

	function checkUpdate()
	{
		return false;
	}

	function moduleUpdate()
	{
	}
}
/* End of file archive.class.php */
