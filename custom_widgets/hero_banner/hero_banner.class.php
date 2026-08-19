<?php
/**
 * @class  hero_banner
 * @brief  메인페이지 히어로 배너 위젯. 전환(캐러셀) 없이 admin이 편집하는 고정 배너.
 */
class hero_banner extends WidgetHandler
{
	function proc($args)
	{
		Context::set('headline', $args->headline ?? '');
		Context::set('subtext', $args->subtext ?? '');
		Context::set('bg_image', $args->bg_image ?? '');
		Context::set('cta_text', $args->cta_text ?? '');
		Context::set('cta_url', $args->cta_url ?? '');

		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin ?: 'default');
		$oTemplate = TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, 'hero_banner');
	}
}
/* End of file hero_banner.class.php */
