<?php
/** Use Rhymix's compact CKEditor toolbar for KITEL boards in the known local DB. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

if (array_diff(array_slice($argv, 1), ['--apply'])) throw new RuntimeException('Use no argument or --apply.');
[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$prefix = $db['prefix'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$expected = [
    'exhibition' => [129, 'kitel_gallery'],
    'news' => [115, 'kitel_generic'],
    'seminar' => [131, 'kitel_generic'],
    'sharing' => [133, 'kitel_generic'],
    'suggestion' => [135, 'kitel_generic'],
];
$findModule = $pdo->prepare("SELECT module_srl,module,skin FROM {$prefix}modules WHERE site_srl=0 AND mid=?");
$findConfig = $pdo->prepare("SELECT config FROM {$prefix}module_part_config WHERE module='editor' AND module_srl=?");
$updates = [];
foreach ($expected as $mid => [$moduleSrl, $skin]) {
    $findModule->execute([$mid]);
    $module = $findModule->fetch(PDO::FETCH_ASSOC);
    if (!$module || (int)$module['module_srl'] !== $moduleSrl || $module['module'] !== 'board' || $module['skin'] !== $skin) {
        throw new RuntimeException("Unexpected board identity for {$mid}; no change made.");
    }
    $findConfig->execute([$moduleSrl]);
    $serialized = $findConfig->fetchColumn();
    $editor = $serialized === false ? new stdClass() : unserialize($serialized, ['allowed_classes' => ['stdClass']]);
    if (!$editor instanceof stdClass) throw new RuntimeException("Unexpected editor config for {$mid}; no change made.");
    $current = ($editor->default_editor_settings ?? 'Y') === 'Y' ? 'global default' : ($editor->editor_toolbar ?? 'default');
    $editor->default_editor_settings = 'N';
    $editor->editor_toolbar = 'simple';
    $editor->editor_toolbar_hide = 'N';
    $updates[$moduleSrl] = $editor;
    echo "{$mid}/{$moduleSrl}: {$current} -> simple (desktop document toolbar only)\n";
}

if (!in_array('--apply', $argv, true)) {
    echo "DRY RUN. Pass --apply to update only these five local development board editors.\n";
    exit;
}
$save = $pdo->prepare("INSERT INTO {$prefix}module_part_config (module,module_srl,config,regdate) VALUES ('editor',?,?,?) ON DUPLICATE KEY UPDATE config=VALUES(config)");
$pdo->beginTransaction();
try {
    foreach ($updates as $moduleSrl => $editor) $save->execute([$moduleSrl, serialize($editor), date('YmdHis')]);
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}
echo "APPLIED. Rebuild Rhymix cache before browser verification.\n";
