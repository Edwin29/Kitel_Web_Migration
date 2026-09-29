<?php
/** Technical department assignment authoring. */
class HomeworkAdminController extends Homework
{
    function init()
    {
    }

    /** Create or update a task, including inline question images. */
    function procHomeworkAdminInsertTask()
    {
        $task_srl = (int)Context::get('task_srl');
        $title = trim((string)Context::get('title'));
        if ($title === '' || mb_strlen($title) > 250) throw new Rhymix\Framework\Exceptions\InvalidRequest;
        $prompts = Context::get('field_prompt_html');
        $ids = Context::get('field_id');
        $heights = Context::get('field_height');
        if ($prompts === null) $prompts = array();
        if (!is_array($prompts) || count($prompts) > 20 || count($prompts) !== count((array)$ids) || count($prompts) !== count((array)$heights)) throw new Rhymix\Framework\Exceptions\InvalidRequest;

        $extensions = preg_split('/[\s,]+/', strtolower(trim((string)Context::get('allowed_extensions'))), -1, PREG_SPLIT_NO_EMPTY);
        if (count($extensions) > 30) throw new Rhymix\Framework\Exceptions\InvalidRequest;
        foreach ($extensions as $extension)
        {
            if (!preg_match('/^[a-z0-9]{1,12}$/', $extension)) throw new Rhymix\Framework\Exceptions\InvalidRequest;
        }
        $deadline_date = (string)Context::get('deadline');
        if ($deadline_date !== '')
        {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $deadline_date);
            if (!$date || $date->format('Y-m-d') !== $deadline_date) throw new Rhymix\Framework\Exceptions\InvalidRequest;
        }

        $existing = null;
        $args = new stdClass;
        if ($task_srl)
        {
            $existing = getModel('homework')->getTask($task_srl);
            if (!$existing || (int)$existing->module_srl !== (int)$this->module_info->module_srl) throw new Rhymix\Framework\Exceptions\InvalidRequest;
            $args->task_srl = $task_srl;
        }
        else
        {
            $args->task_srl = getNextSequence();
            $args->module_srl = $this->module_info->module_srl;
            $args->member_srl = Context::get('logged_info')->member_srl;
        }

        $pending = array();
        $image_count = 0;
        $description = $this->preparePrompt((string)Context::get('description'), $args->task_srl, $existing, $pending, $image_count);
        $fields = array();
        $seen = array();
        foreach ($prompts as $i => $prompt)
        {
            if (!is_string($prompt) || !is_string($ids[$i])) throw new Rhymix\Framework\Exceptions\InvalidRequest;
            $id = $ids[$i];
            if (!preg_match('/^[a-zA-Z0-9_-]{8,40}$/', $id) || isset($seen[$id])) throw new Rhymix\Framework\Exceptions\InvalidRequest;
            $seen[$id] = true;
            $html = $this->preparePrompt($prompt, $args->task_srl, $existing, $pending, $image_count);
            $summary = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($html))), 0, 120);
            $fields[] = array('id' => $id, 'title' => $summary, 'prompt_html' => $html, 'height' => max(120, min(900, (int)$heights[$i])));
        }
        $args->title = $title;
        $args->description = $description;
        $args->answer_fields = json_encode(array('version' => 2, 'primary_height' => max(120, min(900, (int)Context::get('primary_height'))), 'fields' => $fields), JSON_UNESCAPED_UNICODE);
        $args->allowed_extensions = implode(',', array_unique($extensions));
        $args->deadline = $deadline_date ? str_replace('-', '', $deadline_date) . '235959' : '';
        // Legacy artwork is migrated into the first rich prompt when the task is edited.
        $args->description_image = '';

        $dir = './files/attach/homework/' . $this->module_info->module_srl . '/task-images/';
        if ($pending) FileHandler::makeDir($dir);
        $written = array();
        foreach ($pending as $filename => $bytes)
        {
            if (file_put_contents(FileHandler::getRealPath($dir . $filename), $bytes, LOCK_EX) === false)
            {
                foreach ($written as $name) Rhymix\Framework\Storage::delete(FileHandler::getRealPath($dir . $name));
                throw new Rhymix\Framework\Exceptions\InvalidRequest('이미지를 저장하지 못했습니다.');
            }
            $written[] = $filename;
        }
        $output = $task_srl ? executeQuery('homework.updateTask', $args) : executeQuery('homework.insertTask', $args);
        if (!$output->toBool())
        {
            foreach ($written as $name) Rhymix\Framework\Storage::delete(FileHandler::getRealPath($dir . $name));
            return $output;
        }
        $this->setMessage('success_registed');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHomeworkAdminContent', 'mid', Context::get('mid')));
    }

    private function preparePrompt($html, $task_srl, $existing, &$pending, &$image_count)
    {
        if (strlen($html) > 24 * 1024 * 1024) throw new Rhymix\Framework\Exceptions\InvalidRequest;
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="homework-prompt-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $dom->getElementById('homework-prompt-root');
        if (!$root) throw new Rhymix\Framework\Exceptions\InvalidRequest;
        $image_urls = array();
        foreach ($root->getElementsByTagName('img') as $img)
        {
            if (++$image_count > 10) throw new Rhymix\Framework\Exceptions\InvalidRequest('이미지는 과제당 10개까지 넣을 수 있습니다.');
            $src = html_entity_decode($img->getAttribute('src'), ENT_QUOTES, 'UTF-8');
            if (preg_match('~^data:(image/(?:jpeg|png|webp|gif));base64,([a-zA-Z0-9+/=]+)$~', $src, $matches))
            {
                $bytes = base64_decode($matches[2], true);
                $types = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');
                if ($bytes === false || strlen($bytes) > 3 * 1024 * 1024 || !getimagesizefromstring($bytes) || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $matches[1]) throw new Rhymix\Framework\Exceptions\InvalidRequest('JPG, PNG, WebP, GIF 이미지만 3MB 이하로 넣을 수 있습니다.');
                $filename = $task_srl . '_' . bin2hex(random_bytes(8)) . '.' . $types[$matches[1]];
                $pending[$filename] = $bytes;
            }
            else
            {
                $query = array();
                parse_str((string)parse_url($src, PHP_URL_QUERY), $query);
                $filename = (string)($query['image'] ?? ($existing->description_image ?? ''));
                $is_current_file = preg_match('/^' . preg_quote((string)$task_srl, '/') . '_[a-f0-9]{16}\.(?:jpg|png|webp|gif)$/', $filename);
                if (($query['act'] ?? '') !== 'procHomeworkTaskImage' || (int)($query['task_srl'] ?? 0) !== (int)$task_srl || !$existing || (!$is_current_file && $filename !== ($existing->description_image ?? ''))) throw new Rhymix\Framework\Exceptions\InvalidRequest('이 과제에 속하지 않은 이미지입니다.');
                $path = FileHandler::getRealPath('./files/attach/homework/' . $this->module_info->module_srl . '/task-images/' . $filename);
                if (basename($filename) !== $filename || !Rhymix\Framework\Storage::isFile($path)) throw new Rhymix\Framework\Exceptions\InvalidRequest;
            }
            $url = getNotEncodedUrl('', 'act', 'procHomeworkTaskImage', 'mid', Context::get('mid'), 'task_srl', $task_srl, 'image', $filename);
            $placeholder = 'https://example.com/kitel-homework-inline/' . $filename;
            $image_urls[$placeholder] = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $img->setAttribute('src', $placeholder);
            $img->setAttribute('alt', '과제 설명 이미지');
        }
        $output = '';
        foreach ($root->childNodes as $child) $output .= $dom->saveHTML($child);
        $clean = Rhymix\Framework\Filters\HTMLFilter::clean($output, false, false, false);
        foreach ($image_urls as $placeholder => $url) $clean = str_replace('src="' . $placeholder . '"', 'src="' . $url . '"', $clean);
        return $clean;
    }

    /** Show or hide a task without modifying its submissions or attachments. */
    function procHomeworkAdminSetVisibility()
    {
        $task_srl = (int)Context::get('task_srl');
        $is_visible = Context::get('is_visible');
        if (!$task_srl || !in_array($is_visible, array('Y', 'N'), true)) throw new Rhymix\Framework\Exceptions\InvalidRequest;
        $task = getModel('homework')->getTask($task_srl);
        if (!$task || (int)$task->module_srl !== (int)$this->module_info->module_srl) throw new Rhymix\Framework\Exceptions\TargetNotFound;
        $args = new stdClass;
        $args->task_srl = $task_srl;
        $args->module_srl = $this->module_info->module_srl;
        $args->is_visible = $is_visible;
        $output = executeQuery('homework.updateTaskVisibility', $args);
        if (!$output->toBool()) return $output;
        $this->setMessage('success_updated');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispHomeworkAdminContent', 'mid', Context::get('mid'), 'page', max(1, (int)Context::get('page'))));
    }
}
/* End of file homework.admin.controller.php */
