<?php
/**
 * @class  calendarModel
 * @brief  calendar module model class
 */
class CalendarModel extends Calendar
{
	/**
	 * @brief Get all events whose date range overlaps [range_start, range_end] (YYYYMMDD strings)
	 */
	function getEventList($module_srl, $range_start, $range_end)
	{
		$args = new stdClass;
		$args->module_srl = $module_srl;
		$args->range_start = $range_start;
		$args->range_end = $range_end;

		$output = executeQueryArray('calendar.getEventList', $args);
		if (!$output->toBool() || !is_array($output->data))
		{
			return array();
		}
		return $output->data;
	}

	/**
	 * @brief Get a single event
	 */
	function getEvent($event_srl)
	{
		$args = new stdClass;
		$args->event_srl = $event_srl;

		$output = executeQuery('calendar.getEvent', $args);
		if (!$output->toBool() || !$output->data)
		{
			return null;
		}
		return $output->data;
	}
}
/* End of file calendar.model.php */
