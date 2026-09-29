<?php
class KitelboardguardController extends Kitelboardguard
{
    /** Preserve the same visibility contract for a file URL as for its post or comment. */
    public function triggerBeforeDownload($file)
    {
        $module = ModuleModel::getModuleInfoByModuleSrl((int)$file->module_srl);
        if (!$module || $module->module !== 'board' || $module->skin !== 'kitel_generic')
        {
            return new BaseObject();
        }

        $grant = ModuleModel::getGrant($module, Context::get('logged_info'));
        if (!$grant->access || !$grant->list || !$grant->view)
        {
            return new BaseObject(-1, 'msg_not_permitted_download');
        }

        $target = (int)$file->upload_target_srl;
        $type = $file->upload_target_type ?? 'doc';
        if ($type === 'com' || $type === 'comment')
        {
            $comment = CommentModel::getComment($target);
            if (!$comment->isExists() || in_array($comment->get('status'), [RX_STATUS_DELETED, RX_STATUS_DELETED_BY_ADMIN], true) || !$comment->isAccessible())
            {
                return new BaseObject(-1, 'msg_not_permitted_download');
            }
            $document = DocumentModel::getDocument((int)$comment->get('document_srl'));
        }
        elseif ($type === 'doc' || $type === 'document' || $type === '')
        {
            $document = DocumentModel::getDocument($target);
        }
        else
        {
            return new BaseObject(-1, 'msg_not_permitted_download');
        }

        if (!$document->isExists() || (int)$document->get('module_srl') !== (int)$module->module_srl || !$document->isAccessible())
        {
            return new BaseObject(-1, 'msg_not_permitted_download');
        }
        return new BaseObject();
    }
}
