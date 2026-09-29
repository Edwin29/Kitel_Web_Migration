<?php
/**
 * @class  homework
 * @brief  The parent class of the homework (과제게시판) module
 */
class Homework extends ModuleObject
{
	const JUNIOR_GROUP_SRL = 3; // 준회원

	/** Submission files must never be placed under a web-served document root. */
	public static function getSubmissionStorageDir($module_srl)
	{
		$root = getenv('KITEL_HOMEWORK_PRIVATE_ROOT') ?: dirname(rtrim(RX_BASEDIR, '/\\'), 2) . '/kitel-homework-private';
		if (!preg_match('~^(?:[a-zA-Z]:[/\\\\]|/)~', $root)) throw new RuntimeException('Homework private storage path must be absolute.');
		if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new RuntimeException('Cannot create Homework private storage.');
		$real_root = realpath($root);
		if (!$real_root) throw new RuntimeException('Cannot resolve Homework private storage.');
		$real_root = str_replace('\\', '/', $real_root);
		foreach (array(RX_BASEDIR, $_SERVER['DOCUMENT_ROOT'] ?? '') as $public)
		{
			$real_public = $public ? realpath($public) : false;
			if (!$real_public) continue;
			$real_public = rtrim(str_replace('\\', '/', $real_public), '/');
			if (strcasecmp($real_root, $real_public) === 0 || stripos($real_root . '/', $real_public . '/') === 0) throw new RuntimeException('Homework storage must be outside the document root.');
		}
		$path = $real_root . '/' . (int)$module_srl;
		if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('Cannot create Homework module storage.');
		$real_path = str_replace('\\', '/', realpath($path));
		if (stripos($real_path . '/', rtrim($real_root, '/') . '/') !== 0) throw new RuntimeException('Unsafe Homework storage symlink.');
		return rtrim($real_path, '/') . '/';
	}

	public static function getTaskImageStorageDir($module_srl)
	{
		$parent = self::getSubmissionStorageDir($module_srl);
		$path = $parent . 'task-images';
		if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('Cannot create Homework image storage.');
		$real_path = str_replace('\\', '/', realpath($path));
		if (is_link($path) || stripos($real_path . '/', $parent) !== 0) throw new RuntimeException('Unsafe Homework image storage symlink.');
		return rtrim($real_path, '/') . '/';
	}

	function moduleInstall()
	{
		$config = new stdClass;
		$config->skin = 'default';
		$oModuleController = ModuleController::getInstance();
		$oModuleController->insertModuleConfig('homework', $config);
	}

	function checkUpdate()
	{
		$db = DB::getInstance();
		foreach (array('answer_fields', 'allowed_extensions', 'description_image', 'is_visible') as $column)
		{
			if (!$db->isColumnExists('homework_task', $column)) return true;
		}
		return !$db->isColumnExists('homework_submission', 'answers');
	}

	function moduleUpdate()
	{
		$db = DB::getInstance();
		if (!$db->isColumnExists('homework_task', 'answer_fields')) $db->addColumn('homework_task', 'answer_fields', 'text');
		if (!$db->isColumnExists('homework_task', 'allowed_extensions')) $db->addColumn('homework_task', 'allowed_extensions', 'varchar', 250);
		if (!$db->isColumnExists('homework_task', 'description_image')) $db->addColumn('homework_task', 'description_image', 'varchar', 250);
		if (!$db->isColumnExists('homework_task', 'is_visible')) $db->addColumn('homework_task', 'is_visible', 'char', 1, 'Y', true);
		if (!$db->isColumnExists('homework_submission', 'answers')) $db->addColumn('homework_submission', 'answers', 'text');
	}

	public static function getAnswerFields($task)
	{
		$data = json_decode($task->answer_fields ?? '', true);
		if (!is_array($data)) return array();
		return isset($data['version']) ? ($data['fields'] ?? array()) : $data;
	}

	public static function getPrimaryAnswerHeight($task)
	{
		$data = json_decode($task->answer_fields ?? '', true);
		return isset($data['version']) ? max(120, min(900, (int)($data['primary_height'] ?? 270))) : 270;
	}

	public static function getQuestionPromptHtml($task, $field = null)
	{
		$data = json_decode($task->answer_fields ?? '', true);
		if ($field !== null)
		{
			if (!empty($field['prompt_html'])) return $field['prompt_html'];
			return nl2br(htmlspecialchars((string)($field['title'] ?? ''), ENT_QUOTES, 'UTF-8'));
		}
		if (isset($data['version'])) return (string)$task->description;
		return nl2br(htmlspecialchars((string)$task->description, ENT_QUOTES, 'UTF-8'));
	}

	public static function getAnswers($submission)
	{
		$answers = json_decode($submission->answers ?? '', true);
		return is_array($answers) ? $answers : array();
	}

	/** The submission regdate is updated on every resubmission, so it is the last edit time. */
	public static function getDashboardSubmissionStatus($submission, $task)
	{
		if (!$submission) return 'missing';
		return $task->deadline && strcmp((string)$submission->regdate, (string)$task->deadline) > 0 ? 'late' : 'submitted';
	}
}
/* End of file homework.class.php */
