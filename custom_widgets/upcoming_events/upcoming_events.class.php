<?php
/**
 * @class  upcoming_events
 * @brief  메인페이지 "다가오는 일정" 미리보기 위젯. custom_modules/calendar의 model을 재사용.
 */
class upcoming_events extends WidgetHandler
{
	function proc($args)
	{
		$count = (int) ($args->event_count ?: 5);
		$days_ahead = (int) ($args->days_ahead ?: 90);

		$module_srls = array_filter(array_map('trim', explode(',', $args->calendar_module_srl ?? '')));

		$today = date('Ymd');
		$range_end = date('Ymd', strtotime('+' . $days_ahead . ' days'));

		$oCalendarModel = getModel('calendar');
		$events = array();
		foreach ($module_srls as $module_srl)
		{
			$events = array_merge($events, $oCalendarModel->getEventList((int) $module_srl, $today, $range_end));
		}
		usort($events, function ($a, $b) {
			return strcmp($a->start_date, $b->start_date);
		});
		$events = array_slice($events, 0, $count);

		$calendar_url = '';
		if (count($module_srls))
		{
			$module_info = ModuleModel::getModuleInfoByModuleSrl((int) $module_srls[0]);
			if ($module_info)
			{
				$calendar_url = '/' . $module_info->mid;
			}
		}

		Context::set('events', $events);
		Context::set('calendar_url', $calendar_url);

		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin ?: 'default');
		$oTemplate = TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, 'upcoming_events');
	}
}
/* End of file upcoming_events.class.php */
