<?php
/** Dynamic Home previews. The widget page owns their composition, and each module owns its data. */
class home_dashboard extends WidgetHandler
{
    private function module($value, $mid, $type)
    {
        $module = ModuleModel::getModuleInfoByModuleSrl((int)$value);
        return $module && $module->mid === $mid && $module->module === $type ? $module : null;
    }

    private function documents($module_srl, $limit)
    {
        $query = (object)[
            'module_srl' => (int)$module_srl,
            'statusList' => [DocumentModel::getConfigStatus('public')],
            'page' => 1,
            'list_count' => (int)$limit,
            'page_count' => 1,
            'sort_index' => 'list_order',
            'order_type' => 'asc',
        ];
        $result = DocumentModel::getDocumentList($query, false, true);
        return $result->toBool() && is_array($result->data) ? $result->data : [];
    }

    public function proc($args)
    {
        $news = $this->module($args->news_module_srl ?? 0, 'news', 'board');
        $homework = $this->module($args->homework_module_srl ?? 0, 'homework', 'homework');
        $exhibition = $this->module($args->exhibition_module_srl ?? 0, 'exhibition', 'board');

        $news_list = $news ? $this->documents($news->module_srl, 4) : [];
        $news_categories = $news ? DocumentModel::getCategoryList($news->module_srl) : [];

        $homework_list = [];
        $member = Context::get('logged_info');
        $homework_access = false;
        if ($homework && $member && (int)($member->member_srl ?? 0) > 0)
        {
            $grant = ModuleModel::getGrant($homework, $member);
            $homework_access = !empty($grant->access) && !empty($grant->list);
            if ($homework_access)
            {
                $result = getModel('homework')->getTaskPage($homework->module_srl, 1, 3, true);
                $homework_list = $result->toBool() && is_array($result->data) ? $result->data : [];
                if (!empty($grant->submit))
                {
                    foreach ($homework_list as $task)
                    {
                        $task->home_submitted = (bool)getModel('homework')->getSubmissionByMember($task->task_srl, $member->member_srl);
                    }
                }
            }
        }

        $exhibition_list = [];
        if ($exhibition && Kitelboardguard::exhibitionIsPublic($exhibition->module_srl))
        {
            foreach ($this->documents($exhibition->module_srl, 8) as $document)
            {
                if (!Kitelboardguard::exhibitionDocumentAccessible($document)) continue;
                $photo = null;
                foreach (FileModel::getFiles($document->document_srl, [], 'file_srl', true, 'ev:doc') as $file)
                {
                    if (str_starts_with((string)($file->mime_type ?? ''), 'image/')) { $photo = $file; break; }
                }
                $exhibition_list[] = ['document' => $document, 'photo' => $photo];
                if (count($exhibition_list) === 3) break;
            }
        }

        Context::set('home_news', $news_list);
        Context::set('home_news_categories', $news_categories ?: []);
        Context::set('home_homework', $homework_list);
        Context::set('home_homework_access', $homework_access);
        Context::set('home_exhibition', $exhibition_list);

        $skin = preg_match('/^[a-zA-Z0-9_-]+$/', (string)($args->skin ?? 'default')) ? $args->skin : 'default';
        return TemplateHandler::getInstance()->compile($this->widget_path . 'skins/' . $skin, 'home_dashboard');
    }
}
