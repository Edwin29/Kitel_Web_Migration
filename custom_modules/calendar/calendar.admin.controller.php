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
		$title_input = Context::get('title');
		if (!is_string($title_input) || trim($title_input) === '')
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		$title = trim($title_input);
		$start_date = $this->normalizeEventDate(Context::get('start_date'));
		$end_input = Context::get('end_date');
		$end_date = $end_input === null || (is_string($end_input) && trim($end_input) === '')
			? $start_date : $this->normalizeEventDate($end_input);
		if ($end_date < $start_date)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$logged_info = Context::get('logged_info');

		$args = new stdClass;
		$args->title = $title;
		$args->start_date = $start_date;
		$args->end_date = $end_date;
		$args->location = Context::get('location');
		$args->category = Context::get('category');
		$args->description = Context::get('description');
		$args->module_srl = $this->module_info->module_srl;

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

	private function normalizeEventDate($value)
	{
		if (!is_string($value))
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		$value = trim($value);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)
			&& !preg_match('/^(\d{4})(\d{2})(\d{2})$/D', $value, $parts))
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		if (!checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]))
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}
		return $parts[1] . $parts[2] . $parts[3];
	}

	/**
	 * @brief Delete an event
	 */
	function procCalendarAdminDeleteEvent()
	{
		$args = new stdClass;
		$args->event_srl = (int) Context::get('event_srl');
		$args->module_srl = $this->module_info->module_srl;

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
