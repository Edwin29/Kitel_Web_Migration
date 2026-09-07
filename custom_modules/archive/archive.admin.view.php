<?php
/**
 * @class  archiveAdminView
 * @brief  archive module admin view class
 */
class ArchiveAdminView extends Archive
{
	function init()
	{
		$template_path = sprintf('%stpl/', $this->module_path);
		$this->setTemplatePath($template_path);
	}

	/**
	 * @brief Permission settings (reuses the shared module grant editor, same as the calendar module)
	 */
	function dispArchiveAdminGrantInfo()
	{
		$oModuleAdminModel = getAdminModel('module');
		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);

		$this->setTemplateFile('grant_list');
	}
}
/* End of file archive.admin.view.php */
