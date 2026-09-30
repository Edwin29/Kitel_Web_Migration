<?php
/** Bind the consultation board to KITEL's composite skin in the known local DB. Preview by default. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

if (array_diff(array_slice($argv, 1), ['--apply'])) throw new RuntimeException('Use no argument or --apply.');
[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
if (!is_file($root . '/modules/board/skins/kitel_generic/_suggestion_list.html') ||
    !is_file($root . '/modules/kitelboardguard/kitelboardguard.controller.php')) {
    throw new RuntimeException('Sync the repository skin and policy module before applying.');
}
$db = $config['db']['master'];
$prefix = $db['prefix'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$module = $pdo->query("SELECT module_srl,mid,module,skin,is_skin_fix,open_rss FROM {$prefix}modules WHERE site_srl=0 AND mid='suggestion'")->fetch(PDO::FETCH_ASSOC);
if (!$module || (int)$module['module_srl'] !== 135 || $module['module'] !== 'board') {
    throw new RuntimeException('Unexpected suggestion board identity.');
}
$options = $pdo->query("SELECT name,value FROM {$prefix}module_extra_vars WHERE module_srl=135")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (['consultation'=>'Y','anonymous_except_admin'=>'N','use_status'=>'PUBLIC'] as $key=>$value) {
    if (($options[$key] ?? null) !== $value) throw new RuntimeException("Unexpected suggestion option: {$key}");
}
if (!in_array($options['use_anonymous'] ?? null, ['Y','N'], true)) throw new RuntimeException('Unexpected anonymous option.');
$grants = $pdo->query("SELECT name,group_srl FROM {$prefix}module_grants WHERE module_srl=135")->fetchAll(PDO::FETCH_ASSOC);
$map = [];
foreach ($grants as $row) $map[$row['name']][] = (int)$row['group_srl'];
foreach (['access','list','view','write_document'] as $name) {
    sort($map[$name]);
    if ($map[$name] !== [4,108,109,111,112]) throw new RuntimeException("Unexpected {$name} grants.");
}
sort($map['consultation_read']);
if ($map['consultation_read'] !== [108,111,112]) throw new RuntimeException('Unexpected consultation read grants.');
$triggers = [
    ['document.insertDocument','before','kitelboardguard','controller','triggerBeforeSuggestionSave'],
    ['document.updateDocument','before','kitelboardguard','controller','triggerBeforeSuggestionSave'],
    ['file.downloadFile','before','kitelboardguard','controller','triggerBeforeDownload'],
    ['file.insertFile','after','kitelboardguard','controller','triggerAfterExhibitionUpload'],
];
$find = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}module_trigger WHERE trigger_name=? AND called_position=? AND module=? AND type=? AND called_method=?");
$missing = [];
foreach ($triggers as $trigger) { $find->execute($trigger); if (!$find->fetchColumn()) $missing[] = $trigger; }
echo "suggestion/135: {$module['skin']} -> kitel_generic; RSS {$module['open_rss']} -> N; anonymous {$options['use_anonymous']} -> N; triggers to add: " . count($missing) . PHP_EOL;
if (!in_array('--apply', $argv, true)) { echo "DRY RUN. Pass --apply to update the local development board.\n"; exit; }
$pdo->beginTransaction();
try {
    $update = $pdo->prepare("UPDATE {$prefix}modules SET skin='kitel_generic',is_skin_fix='Y',open_rss='N' WHERE module_srl=135 AND mid='suggestion' AND module='board'");
    $update->execute();
    $option = $pdo->prepare("UPDATE {$prefix}module_extra_vars SET value='N' WHERE module_srl=135 AND name='use_anonymous'");
    $option->execute();
    $pdo->exec("UPDATE {$prefix}documents SET comment_status='ALLOW',allow_trackback='N' WHERE module_srl=135 AND (comment_status<>'ALLOW' OR allow_trackback<>'N')");
    $insert = $pdo->prepare("INSERT INTO {$prefix}module_trigger (trigger_name,called_position,module,type,called_method) VALUES (?,?,?,?,?)");
    foreach ($missing as $trigger) $insert->execute($trigger);
    $pdo->commit();
} catch (Throwable $error) { $pdo->rollBack(); throw $error; }
echo "APPLIED to local development DB. Rebuild Rhymix cache before testing.\n";
