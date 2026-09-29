<?php
/** Register the KITEL board attachment visibility trigger in the known local DB. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
if (!is_file($root . '/modules/kitelboardguard/kitelboardguard.controller.php')) {
    throw new RuntimeException('Sync the repository module before registering its trigger.');
}
$db = $config['db']['master'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$findBoard = $pdo->prepare('SELECT mid, module, skin FROM ' . $db['prefix'] . 'modules WHERE module_srl = ?');
foreach (['news' => 115, 'seminar' => 131] as $mid => $srl) {
    $findBoard->execute([$srl]);
    $row = $findBoard->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['mid'] !== $mid || $row['module'] !== 'board' || $row['skin'] !== 'kitel_generic') {
        throw new RuntimeException("Unexpected board identity or skin for {$mid} ({$srl}).");
    }
}
$trigger = ['file.downloadFile', 'before', 'kitelboardguard', 'controller', 'triggerBeforeDownload'];
$find = $pdo->prepare('SELECT COUNT(*) FROM ' . $db['prefix'] . 'module_trigger WHERE trigger_name = ? AND called_position = ? AND module = ? AND type = ? AND called_method = ?');
$find->execute($trigger);
$registered = (int)$find->fetchColumn() === 1;
echo $registered ? "Trigger already registered.\n" : "Trigger not registered.\n";
if (!in_array('--apply', $argv, true)) {
    echo "DRY RUN. Pass --apply to register only this development trigger.\n";
    exit;
}
if (!$registered) {
    $insert = $pdo->prepare('INSERT INTO ' . $db['prefix'] . 'module_trigger (trigger_name, called_position, module, type, called_method) VALUES (?, ?, ?, ?, ?)');
    $insert->execute($trigger);
}
echo "APPLIED to local development DB. Rebuild Rhymix cache before testing.\n";
