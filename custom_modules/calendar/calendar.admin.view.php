<?php
/**
 * @class  calendarAdminView
 * @brief  calendar module admin view class
 */
class CalendarAdminView extends Calendar
{
	function init()
	{
		$template_path = sprintf('%stpl/', $this->module_path);
		$this->setTemplatePath($template_path);
	}

	/**
	 * @brief Admin event list (default admin landing page for this module)
	 */
	function dispCalendarAdminContent()
	{
		$oCalendarModel = getModel('calendar');
		$events = $oCalendarModel->getEventList($this->module_info->module_srl, '00000000', '99999999');

		Context::set('event_list', $events);
		$this->setTemplateFile('event_list');
	}

	/**
	 * @brief Add/edit event form
	 */
	function dispCalendarAdminForm()
	{
		$event_srl = Context::get('event_srl');
		$event = null;
		if ($event_srl)
		{
			$oCalendarModel = getModel('calendar');
			$event = $oCalendarModel->getEvent($event_srl);
		}
		Context::set('event', $event);
		$this->setTemplateFile('event_form');
	}

	/**
	 * @brief Permission settings (reuses the shared module grant editor)
	 */
	function dispCalendarAdminGrantInfo()
	{
		$oModuleAdminModel = getAdminModel('module');
		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);

		$this->setTemplateFile('grant_list');
	}
}
/* End of file calendar.admin.view.php */
