<?php
class Kitelboardguard extends ModuleObject
{
    public const ACTIVITY_CATEGORY_SRL = 170;
    private const TRIGGERS = [
        ['file.downloadFile', 'kitelboardguard', 'controller', 'triggerBeforeDownload', 'before'],
        ['document.insertDocument', 'kitelboardguard', 'controller', 'triggerBeforeExhibitionSave', 'before'],
        ['document.updateDocument', 'kitelboardguard', 'controller', 'triggerBeforeExhibitionSave', 'before'],
        ['document.insertDocument', 'kitelboardguard', 'controller', 'triggerBeforeSuggestionSave', 'before'],
        ['document.updateDocument', 'kitelboardguard', 'controller', 'triggerBeforeSuggestionSave', 'before'],
        ['comment.insertComment', 'kitelboardguard', 'controller', 'triggerBeforeExhibitionComment', 'before'],
        ['file.insertFile', 'kitelboardguard', 'controller', 'triggerAfterExhibitionUpload', 'after'],
    ];

    public static function exhibitionPrivateRoot()
    {
        return RX_BASEDIR . '../../kitel-private/exhibition/';
    }

    public static function suggestionPrivateRoot()
    {
        return RX_BASEDIR . '../../kitel-private/suggestion/';
    }

    public static function exhibitionIsPublic($moduleSrl)
    {
        $config = ModuleModel::getModulePartConfig('kitelboardguard', (int)$moduleSrl);
        // Existing exhibitions remain visible until an owner explicitly closes them.
        return !is_object($config) || ($config->is_public ?? 'Y') === 'Y';
    }

    public static function exhibitionDocumentAccessible($document)
    {
        return $document->isExists() && $document->isAccessible() &&
            (self::exhibitionIsPublic($document->get('module_srl')) || $document->isGranted());
    }

    public function moduleInstall()
    {
        foreach (self::TRIGGERS as $trigger)
        {
            ModuleController::getInstance()->insertTrigger(...$trigger);
        }
        return new BaseObject();
    }

    public function checkUpdate()
    {
        foreach (self::TRIGGERS as $trigger)
        {
            if (!ModuleModel::getInstance()->getTrigger(...$trigger)) return true;
        }
        return false;
    }

    public function moduleUpdate()
    {
        foreach (self::TRIGGERS as $trigger)
        {
            if (!ModuleModel::getInstance()->getTrigger(...$trigger))
            {
                ModuleController::getInstance()->insertTrigger(...$trigger);
            }
        }
        return new BaseObject();
    }
}
