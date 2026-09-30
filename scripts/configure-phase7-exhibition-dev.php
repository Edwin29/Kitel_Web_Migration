<?php
/** Register the Phase 7 exhibition policy triggers in the known local development DB. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
if (!is_file($root . '/modules/kitelboardguard/kitelboardguard.controller.php')) {
    throw new RuntimeException('Sync the repository module before registering triggers.');
}
$db = $config['db']['master'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$modules = $db['prefix'] . 'modules';
$rows = $pdo->query("SELECT module_srl, mid, module, skin, is_skin_fix FROM {$modules} WHERE site_srl=0 AND mid='exhibition'")->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) !== 1 || (int)$rows[0]['module_srl'] !== 129 ||
    $rows[0]['module'] !== 'board' || $rows[0]['skin'] !== 'kitel_gallery' || $rows[0]['is_skin_fix'] !== 'Y') {
    throw new RuntimeException('Unexpected exhibition board identity or skin; no change made.');
}
$extra = $db['prefix'] . 'document_extra_keys';
$keys = $pdo->query("SELECT eid, var_type, var_is_required FROM {$extra} WHERE module_srl=129")->fetchAll(PDO::FETCH_ASSOC);
$keyMap = array_column($keys, null, 'eid');
foreach (['photo', 'proposal'] as $eid) {
    if (!isset($keyMap[$eid]) || $keyMap[$eid]['var_type'] !== 'file' || $keyMap[$eid]['var_is_required'] !== 'Y') {
        throw new RuntimeException("Missing required exhibition file field: {$eid}");
    }
}
$newKeys = [
    'team_members' => ['팀원 정보', '쉼표로 구분하여 XX기_이름 형식으로 입력'],
    'project_info' => ['작품 한 줄 소개', '무엇을 활용하여 무엇을 하는 작품인지 설명'],
];
foreach ($newKeys as $eid => $definition) {
    echo $eid . ': ' . (isset($keyMap[$eid]) ? 'already configured' : 'add board extra field') . PHP_EOL;
}
$partTable = $db['prefix'] . 'module_part_config';
$filePartRow = $pdo->query("SELECT config FROM {$partTable} WHERE module='file' AND module_srl=129")->fetch(PDO::FETCH_ASSOC);
$filePart = $filePartRow ? unserialize($filePartRow['config'], ['allowed_classes' => ['stdClass']]) : new stdClass();
if (!is_object($filePart)) throw new RuntimeException('Unexpected exhibition file config.');
$filePart->inline_download_format = array_values(array_unique(array_merge((array)($filePart->inline_download_format ?? []), ['image'])));
echo 'Exhibition image downloads: inline through the checked file controller.' . PHP_EOL;
$directCount = (int)$pdo->query("SELECT COUNT(*) FROM {$db['prefix']}files WHERE module_srl=129 AND direct_download='Y'")->fetchColumn();
echo $directCount . ' exhibition file(s) need controller-only download mode.' . PHP_EOL;
$legacyComments = (int)$pdo->query("SELECT COUNT(*) FROM {$db['prefix']}documents WHERE module_srl=129 AND (comment_status='ALLOW' OR allow_trackback='Y')")->fetchColumn();
echo $legacyComments . ' exhibition document(s) need comments/trackbacks disabled.' . PHP_EOL;
$triggers = [
    ['document.insertDocument', 'before', 'kitelboardguard', 'controller', 'triggerBeforeExhibitionSave'],
    ['document.updateDocument', 'before', 'kitelboardguard', 'controller', 'triggerBeforeExhibitionSave'],
    ['comment.insertComment', 'before', 'kitelboardguard', 'controller', 'triggerBeforeExhibitionComment'],
    ['file.insertFile', 'after', 'kitelboardguard', 'controller', 'triggerAfterExhibitionUpload'],
];
$obsolete = ['file.insertFile', 'before', 'kitelboardguard', 'controller', 'triggerBeforeExhibitionUpload'];
$find = $pdo->prepare('SELECT COUNT(*) FROM ' . $db['prefix'] . 'module_trigger WHERE trigger_name=? AND called_position=? AND module=? AND type=? AND called_method=?');
$find->execute($obsolete);
$obsoleteRegistered = (int)$find->fetchColumn() > 0;
echo 'Obsolete upload trigger: ' . ($obsoleteRegistered ? 'remove' : 'absent') . PHP_EOL;
$missing = [];
foreach ($triggers as $trigger) {
    $find->execute($trigger);
    $registered = (int)$find->fetchColumn() > 0;
    if (!$registered) $missing[] = $trigger;
    echo $trigger[0] . ': ' . ($registered ? 'already registered' : 'register') . PHP_EOL;
}
if (!in_array('--apply', $argv, true)) {
    echo 'DRY RUN. Pass --apply to register only these local development triggers.' . PHP_EOL;
    exit;
}
$pdo->beginTransaction();
try {
    $nextKey = (int)$pdo->query("SELECT COALESCE(MAX(var_idx),0) FROM {$extra} WHERE module_srl=129")->fetchColumn();
    $insertKey = $pdo->prepare("INSERT INTO {$extra} (module_srl,var_idx,var_name,var_type,var_is_required,var_is_strict,var_search,var_sort,var_default,var_options,var_desc,eid) VALUES (129,?,?,?,'N','N','N','N',NULL,NULL,?,?)");
    foreach ($newKeys as $eid => $definition) {
        if (!isset($keyMap[$eid])) $insertKey->execute([++$nextKey, $definition[0], 'text', $definition[1], $eid]);
    }
    $pdo->exec("UPDATE {$db['prefix']}files SET direct_download='N' WHERE module_srl=129 AND direct_download='Y'");
    $pdo->exec("UPDATE {$db['prefix']}documents SET comment_status='DENY', allow_trackback='N' WHERE module_srl=129 AND (comment_status='ALLOW' OR allow_trackback='Y')");
    $serializedFilePart = serialize($filePart);
    if ($filePartRow) {
        $updatePart = $pdo->prepare("UPDATE {$partTable} SET config=? WHERE module='file' AND module_srl=129");
        $updatePart->execute([$serializedFilePart]);
    } else {
        $insertPart = $pdo->prepare("INSERT INTO {$partTable} (module, module_srl, config, regdate) VALUES ('file',129,?,?)");
        $insertPart->execute([$serializedFilePart, date('YmdHis')]);
    }
    if ($obsoleteRegistered) {
        $delete = $pdo->prepare('DELETE FROM ' . $db['prefix'] . 'module_trigger WHERE trigger_name=? AND called_position=? AND module=? AND type=? AND called_method=?');
        $delete->execute($obsolete);
    }
    $insert = $pdo->prepare('INSERT INTO ' . $db['prefix'] . 'module_trigger (trigger_name, called_position, module, type, called_method) VALUES (?, ?, ?, ?, ?)');
    foreach ($missing as $trigger) $insert->execute($trigger);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
echo 'APPLIED to local development DB. Rebuild Rhymix cache before testing.' . PHP_EOL;
