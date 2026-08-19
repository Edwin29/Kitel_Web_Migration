<?php
/**
 * @class  calendarAdminController
 * @brief  calendar module admin controller class
 */
class CalendarAdminController extends Calendar
{
	function init()
	{
	}

	/**
	 * @brief Insert or update an event (event_srl present = update)
	 */
	function procCalendarAdminInsertEvent()
	{
		$event_srl = (int) Context::get('event_srl');
		$title = trim(Context::get('title'));
		$start_date = str_replace('-', '', Context::get('start_date'));
		$end_date = str_replace('-', '', Context::get('end_date'));
		if ($end_date === '')
		{
			$end_date = $start_date;
		}
		if ($end_date < $start_date)
		{
			$end_date = $start_date;
		}

		$logged_info = Context::get('logged_info');

		$args = new stdClass;
		$args->title = $title;
		$args->start_date = $start_date;
		$args->end_date = $end_date;
		$args->location = Context::get('location');
		$args->category = Context::get('category');
		$args->description = Context::get('description');

		if ($event_srl)
		{
			$args->event_srl = $event_srl;
			$output = executeQuery('calendar.updateEvent', $args);
		}
		else
		{
			$args->event_srl = getNextSequence();
			$args->module_srl = $this->module_info->module_srl;
			$args->member_srl = $logged_info->member_srl;
			$output = executeQuery('calendar.insertEvent', $args);
		}

		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCalendarAdminContent', 'mid', Context::get('mid')));
	}

	/**
	 * @brief Delete an event
	 */
	function procCalendarAdminDeleteEvent()
	{
		$args = new stdClass;
		$args->event_srl = (int) Context::get('event_srl');

		$output = executeQuery('calendar.deleteEvent', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCalendarAdminContent', 'mid', Context::get('mid')));
	}
}
/* End of file calendar.admin.controller.php */
