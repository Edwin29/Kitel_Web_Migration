<?php
/** Bind the shared board skin to the known local Sharing board. Preview by default. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$find = $pdo->prepare('SELECT module_srl, mid, module, skin, is_skin_fix FROM ' . $db['prefix'] . 'modules WHERE module_srl = ?');
$find->execute([133]);
$row = $find->fetch(PDO::FETCH_ASSOC);
if (!$row || $row['mid'] !== 'sharing' || $row['module'] !== 'board') {
    throw new RuntimeException('Unexpected Sharing board identity (133); no change made.');
}
printf("sharing (133): %s / %s -> kitel_generic / Y\n", $row['skin'], $row['is_skin_fix']);
$findConfig = $pdo->prepare('SELECT config FROM ' . $db['prefix'] . 'module_part_config WHERE module = ? AND module_srl = ?');
$findConfig->execute(['board', 133]);
$serialized = $findConfig->fetchColumn();
$columns = $serialized === false ? ['no', 'title', 'nick_name', 'regdate', 'readed_count'] : unserialize($serialized, ['allowed_classes' => false]);
if (!is_array($columns) || array_filter($columns, 'is_string') !== $columns) {
    throw new RuntimeException('Unexpected Sharing list column configuration; no change made.');
}
foreach (['summary', 'voted_count'] as $requiredColumn) {
    if (!in_array($requiredColumn, $columns, true)) $columns[] = $requiredColumn;
}
printf("sharing list columns: %s\n", implode(', ', $columns));
if (!in_array('--apply', $argv, true)) {
    echo "DRY RUN. Pass --apply to update only this development board skin/list setting. Grants are unchanged.\n";
    exit;
}
$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE ' . $db['prefix'] . 'modules SET skin = ?, is_skin_fix = ? WHERE module_srl = 133 AND mid = ? AND module = ?');
    $update->execute(['kitel_generic', 'Y', 'sharing', 'board']);
    $saveConfig = $pdo->prepare('INSERT INTO ' . $db['prefix'] . 'module_part_config (module, module_srl, config, regdate) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE config = VALUES(config)');
    $saveConfig->execute(['board', 133, serialize($columns), date('YmdHis')]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
echo "APPLIED to local development DB. Grants unchanged; rebuild Rhymix cache.\n";
