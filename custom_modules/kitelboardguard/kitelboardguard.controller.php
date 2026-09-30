<?php
class KitelboardguardController extends Kitelboardguard
{
    /** The technical department controls publication of the entire exhibition. */
    public function procKitelboardguardSetExhibitionVisibility()
    {
        $module = ModuleModel::getModuleInfoByMid('exhibition');
        if (!$module || $module->module !== 'board' || $module->skin !== 'kitel_gallery')
        {
            throw new Rhymix\Framework\Exceptions\TargetNotFound;
        }
        $grant = ModuleModel::getGrant($module, Context::get('logged_info'));
        if (!$grant->manager)
        {
            throw new Rhymix\Framework\Exceptions\NotPermitted;
        }
        $visible = (string)Context::get('is_public');
        if (!in_array($visible, ['Y', 'N'], true))
        {
            throw new Rhymix\Framework\Exceptions\InvalidRequest;
        }
        $config = ModuleModel::getModulePartConfig('kitelboardguard', $module->module_srl);
        if (!is_object($config)) $config = new stdClass();
        $config->is_public = $visible;
        $database = DB::getInstance();
        $database->begin();
        try
        {
            $result = ModuleController::getInstance()->insertModulePartConfig('kitelboardguard', $module->module_srl, $config);
            if (!$result->toBool()) throw new RuntimeException($result->message);
            $result = executeQuery('kitelboardguard.updateExhibitionVisibility', (object)[
                'module_srl' => $module->module_srl,
                'status' => $visible === 'Y' ? DocumentModel::getConfigStatus('public') : DocumentModel::getConfigStatus('secret'),
                'last_update' => date('YmdHis'),
            ]);
            if (!$result->toBool()) throw new RuntimeException($result->message);
            $database->commit();
        }
        catch (Throwable $error)
        {
            $database->rollback();
            throw $error;
        }
        $this->setMessage($visible === 'Y' ? '전체 작품을 공개했습니다.' : '전체 작품을 미공개로 전환했습니다.');
    }
    /** Serve exhibition cover photos inline, after checking document visibility. */
    public function procKitelboardguardImage()
    {
        $file = FileModel::getFile((int)Context::get('file_srl'));
        if (!$file || !$file->file_srl || ($file->upload_target_type ?? '') !== 'ev:doc')
        {
            throw new Rhymix\Framework\Exceptions\TargetNotFound;
        }
        $module = ModuleModel::getModuleInfoByModuleSrl((int)$file->module_srl);
        $document = DocumentModel::getDocument((int)$file->upload_target_srl);
        if (!$module || $module->module !== 'board' || $module->mid !== 'exhibition' ||
            $module->skin !== 'kitel_gallery' || !$document->isExists() ||
            (int)$document->get('module_srl') !== (int)$module->module_srl ||
            !self::exhibitionDocumentAccessible($document))
        {
            throw new Rhymix\Framework\Exceptions\NotPermitted;
        }
        $path = realpath(FileHandler::getRealPath($file->uploaded_filename));
        $privateRoot = realpath(self::exhibitionPrivateRoot());
        if (!$path || !$privateRoot || !str_starts_with($path, $privateRoot . DIRECTORY_SEPARATOR))
        {
            throw new Rhymix\Framework\Exceptions\TargetNotFound;
        }
        $image = @getimagesize($path);
        if (!$image || !in_array($image['mime'] ?? '', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true))
        {
            throw new Rhymix\Framework\Exceptions\TargetNotFound;
        }
        header('Content-Type: ' . $image['mime']);
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        header('Content-Length: ' . filesize($path));
        Context::close();
        readfile($path);
        exit;
    }

    /** Preserve the same visibility contract for a file URL as for its post or comment. */
    public function triggerBeforeDownload($file)
    {
        $module = ModuleModel::getModuleInfoByModuleSrl((int)$file->module_srl);
        if (!$module || $module->module !== 'board' || !in_array($module->skin, ['kitel_generic', 'kitel_gallery'], true))
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
        elseif ($type === 'doc' || $type === 'document' || $type === '' || ($module->skin === 'kitel_gallery' && $type === 'ev:doc'))
        {
            $document = DocumentModel::getDocument($target);
        }
        else
        {
            return new BaseObject(-1, 'msg_not_permitted_download');
        }

        if (!$document->isExists() || (int)$document->get('module_srl') !== (int)$module->module_srl ||
            !($module->skin === 'kitel_gallery' ? self::exhibitionDocumentAccessible($document) : $document->isAccessible()))
        {
            return new BaseObject(-1, 'msg_not_permitted_download');
        }
        if ($module->mid === 'suggestion')
        {
            $member = Context::get('logged_info');
            $owner = $member && (int)$member->member_srl > 0 && abs((int)$document->get('member_srl')) === (int)$member->member_srl;
            if (!$grant->manager && !$grant->consultation_read && !$owner)
            {
                return new BaseObject(-1, 'msg_not_permitted_download');
            }
        }
        return new BaseObject();
    }


    /** Every exhibition document follows the single board-wide release state. */
    public function triggerBeforeExhibitionSave($document)
    {
        $module = ModuleModel::getModuleInfoByModuleSrl((int)($document->module_srl ?? 0));
        if (!$module || $module->module !== 'board' || $module->mid !== 'exhibition' || $module->skin !== 'kitel_gallery')
        {
            return new BaseObject();
        }

        $document->status = self::exhibitionIsPublic($module->module_srl)
            ? DocumentModel::getConfigStatus('public') : DocumentModel::getConfigStatus('secret');
        $document->comment_status = 'DENY';
        $document->allow_trackback = 'N';
        return new BaseObject();
    }

    /** Suggestions always allow staff replies and have no trackback or public-status choice. */
    public function triggerBeforeSuggestionSave($document)
    {
        $module = ModuleModel::getModuleInfoByModuleSrl((int)($document->module_srl ?? 0));
        if ($module && $module->module === 'board' && $module->mid === 'suggestion' && $module->skin === 'kitel_generic')
        {
            $document->status = DocumentModel::getConfigStatus('public');
            $document->comment_status = 'ALLOW';
            $document->commentStatus = 'ALLOW';
            $document->allow_trackback = 'N';
        }
        return new BaseObject();
    }

    public function triggerBeforeExhibitionComment($comment)
    {
        $module = ModuleModel::getModuleInfoByModuleSrl((int)($comment->module_srl ?? 0));
        if ($module && $module->module === 'board' && $module->mid === 'exhibition' && $module->skin === 'kitel_gallery')
        {
            return new BaseObject(-1, 'msg_not_permitted');
        }
        return new BaseObject();
    }

    /** Move sensitive board uploads outside the web root before returning the upload response. */
    public function triggerAfterExhibitionUpload($file)
    {
        $module = ModuleModel::getModuleInfoByModuleSrl((int)($file->module_srl ?? 0));
        $isExhibition = $module && $module->module === 'board' && $module->mid === 'exhibition' && $module->skin === 'kitel_gallery';
        $isSuggestion = $module && $module->module === 'board' && $module->mid === 'suggestion' && $module->skin === 'kitel_generic';
        if (!$isExhibition && !$isSuggestion)
        {
            return new BaseObject();
        }
        $folder = $isExhibition ? 'exhibition' : 'suggestion';
        $privateRoot = $isExhibition ? self::exhibitionPrivateRoot() : self::suggestionPrivateRoot();
        if (!is_dir($privateRoot) && !mkdir($privateRoot, 0700, true))
        {
            throw new RuntimeException('Cannot create private board storage.');
        }
        $attachRoot = realpath(RX_BASEDIR . 'files/attach');
        $moves = [];
        foreach (['uploaded_filename', 'thumbnail_filename'] as $field)
        {
            if (empty($file->$field)) continue;
            $source = realpath(FileHandler::getRealPath($file->$field));
            if (!$source || !$attachRoot || !str_starts_with($source, $attachRoot . DIRECTORY_SEPARATOR))
            {
                throw new RuntimeException('Unexpected private board upload location.');
            }
            $name = bin2hex(random_bytes(20));
            $moves[$field] = [$source, $privateRoot . $name, './../../kitel-private/' . $folder . '/' . $name];
        }
        try
        {
            foreach ($moves as $field => $move)
            {
                if (!rename($move[0], $move[1])) throw new RuntimeException('Cannot privatize board upload.');
                $file->$field = $move[2];
            }
            $update = (object)[
                'file_srl' => $file->file_srl,
                'module_srl' => $file->module_srl,
                'uploaded_filename' => $file->uploaded_filename,
                'thumbnail_filename' => $file->thumbnail_filename ?? null,
                'direct_download' => 'N',
            ];
            $result = executeQuery('kitelboardguard.updateExhibitionFilePath', $update);
            if (!$result->toBool()) throw new RuntimeException('Cannot update private board upload path.');
            $file->direct_download = 'N';
        }
        catch (Throwable $error)
        {
            foreach ($moves as $field => $move)
            {
                if (is_file($move[1])) rename($move[1], $move[0]);
            }
            throw $error;
        }
        return new BaseObject();
    }
}
