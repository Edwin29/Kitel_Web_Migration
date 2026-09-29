<?php
class Kitelboardguard extends ModuleObject
{
    private const TRIGGER = ['file.downloadFile', 'kitelboardguard', 'controller', 'triggerBeforeDownload', 'before'];

    public function moduleInstall()
    {
        return ModuleController::getInstance()->insertTrigger(...self::TRIGGER);
    }

    public function checkUpdate()
    {
        return !ModuleModel::getInstance()->getTrigger(...self::TRIGGER);
    }

    public function moduleUpdate()
    {
        return ModuleController::getInstance()->insertTrigger(...self::TRIGGER);
    }
}
