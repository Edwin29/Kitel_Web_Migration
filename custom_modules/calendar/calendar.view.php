<?php
/**
 * @class  calendarView
 * @brief  calendar module view class (public-facing)
 */
class CalendarView extends Calendar
{
	function init()
	{
	}

	/**
	 * @brief Month view of the calendar
	 */
	function dispCalendarIndex()
	{
		$year = (int) Context::get('y');
		$month = (int) Context::get('m');
		if ($year < 1970 || $month < 1 || $month > 12)
		{
			$year = (int) date('Y');
			$month = (int) date('n');
		}

		$firstDayTs = mktime(0, 0, 0, $month, 1, $year);
		$daysInMonth = (int) date('t', $firstDayTs);
		$startWeekday = (int) date('w', $firstDayTs); // 0=Sun
		$rangeStart = date('Ymd', $firstDayTs);
		$rangeEnd = date('Ymd', mktime(0, 0, 0, $month, $daysInMonth, $year));
		$today = date('Ymd');

		$oCalendarModel = getModel('calendar');
		$events = $oCalendarModel->getEventList($this->module_info->module_srl, $rangeStart, $rangeEnd);

		// Index events by each date (YYYYMMDD) they touch
		$eventsByDate = array();
		foreach ($events as $event)
		{
			$cursor = max($event->start_date, $rangeStart);
			$last = min($event->end_date, $rangeEnd);
			while ($cursor <= $last)
			{
				$eventsByDate[$cursor][] = $event;
				$cursor = date('Ymd', strtotime($cursor) + 86400);
			}
		}

		// Build week grid
		$weeks = array();
		$week = array();
		for ($i = 0; $i < $startWeekday; $i++)
		{
			$week[] = null;
		}
		for ($d = 1; $d <= $daysInMonth; $d++)
		{
			$dateStr = sprintf('%04d%02d%02d', $year, $month, $d);
			$cell = new stdClass;
			$cell->day = $d;
			$cell->date = $dateStr;
			$cell->is_today = ($dateStr === $today);
			$cell->events = isset($eventsByDate[$dateStr]) ? $eventsByDate[$dateStr] : array();
			$week[] = $cell;

			if (count($week) === 7)
			{
				$weeks[] = $week;
				$week = array();
			}
		}
		if (count($week) > 0)
		{
			while (count($week) < 7)
			{
				$week[] = null;
			}
			$weeks[] = $week;
		}

		$prevTs = mktime(0, 0, 0, $month - 1, 1, $year);
		$nextTs = mktime(0, 0, 0, $month + 1, 1, $year);

		Context::set('cur_year', $year);
		Context::set('cur_month', $month);
		Context::set('month_title', sprintf('%04d년 %02d월', $year, $month));
		Context::set('prev_year', (int) date('Y', $prevTs));
		Context::set('prev_month', (int) date('n', $prevTs));
		Context::set('next_year', (int) date('Y', $nextTs));
		Context::set('next_month', (int) date('n', $nextTs));
		Context::set('weeks', $weeks);
		Context::set('weekday_names', array('일', '월', '화', '수', '목', '금', '토'));
		Context::set('is_manager', $this->grant->manage ?? false);

		$this->setTemplateFile('list');
	}
}
/* End of file calendar.view.php */
