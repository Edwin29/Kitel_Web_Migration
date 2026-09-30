<?php
/** Dynamic Home previews. The widget page owns their composition, and each module owns its data. */
class home_dashboard extends WidgetHandler
{
    private function module($value, $mid, $type)
    {
        $module = ModuleModel::getModuleInfoByModuleSrl((int)$value);
        return $module && $module->mid === $mid && $module->module === $type ? $module : null;
    }

    private function documents($module_srl, $limit, $category_srl = null)
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
        if ($category_srl) $query->category_srl = is_array($category_srl) ? array_map('intval', $category_srl) : (int)$category_srl;
        $result = DocumentModel::getDocumentList($query, false, true);
        return $result->toBool() && is_array($result->data) ? $result->data : [];
    }

    public function proc($args)
    {
        $news = $this->module($args->news_module_srl ?? 0, 'news', 'board');
        $homework = $this->module($args->homework_module_srl ?? 0, 'homework', 'homework');
        $exhibition = $this->module($args->exhibition_module_srl ?? 0, 'exhibition', 'board');

        $news_categories = $news ? DocumentModel::getCategoryList($news->module_srl) : [];
        $notice_categories = array_values(array_filter(array_keys($news_categories), static fn($id) => (int)$id !== Kitelboardguard::ACTIVITY_CATEGORY_SRL));
        $news_list = $news && $notice_categories ? $this->documents($news->module_srl, 4, $notice_categories) : [];
        $activity_candidates = [];
        $activity_featured = null;
        $activity_recent = [];
        $member = Context::get('logged_info');
        $news_grant = $news && $member ? ModuleModel::getGrant($news, $member) : null;
        if ($news && isset($news_categories[Kitelboardguard::ACTIVITY_CATEGORY_SRL]))
        {
            foreach ($this->documents($news->module_srl, 8, Kitelboardguard::ACTIVITY_CATEGORY_SRL) as $document)
            {
                if ((int)$document->get('category_srl') !== Kitelboardguard::ACTIVITY_CATEGORY_SRL || !$document->isAccessible()) continue;
                $file_srl = (int)$document->getExtraEidValue('activity_thumbnail');
                $file = $file_srl ? FileModel::getFile($file_srl) : null;
                $photo = $file && (int)$file->upload_target_srl === (int)$document->document_srl &&
                    ($file->upload_target_type ?? '') === 'ev:doc' &&
                    in_array((string)($file->mime_type ?? ''), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) ? $file : null;
                $activity_candidates[] = ['document' => $document, 'photo' => $photo];
            }
            $featured_index = 0;
            foreach ($activity_candidates as $index => $candidate)
            {
                if ($candidate['photo']) { $featured_index = $index; break; }
            }
            if ($activity_candidates)
            {
                $activity_featured = $activity_candidates[$featured_index];
                $document = $activity_featured['document'];
                $teaser = (string)$document->getExtraEidValue('activity_teaser');
                if (!$teaser && $news_grant && !empty($news_grant->view)) $teaser = html_entity_decode($document->getContentPlainText(180), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $activity_featured['teaser'] = $teaser;
                unset($activity_candidates[$featured_index]);
                $activity_recent = array_slice(array_values($activity_candidates), 0, 3);
            }
        }

        $homework_list = [];
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
        Context::set('home_activity_featured', $activity_featured);
        Context::set('home_activity_recent', $activity_recent);
        Context::set('home_homework', $homework_list);
        Context::set('home_homework_access', $homework_access);
        Context::set('home_exhibition', $exhibition_list);

        $skin = preg_match('/^[a-zA-Z0-9_-]+$/', (string)($args->skin ?? 'default')) ? $args->skin : 'default';
        return TemplateHandler::getInstance()->compile($this->widget_path . 'skins/' . $skin, 'home_dashboard');
    }
}
