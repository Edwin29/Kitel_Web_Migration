<?php
/** Rename the known News category and add its optional teaser/cover fields locally. Dry-run by default. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';
if (array_diff(array_slice($argv, 1), ['--apply'])) throw new RuntimeException('Use no argument or --apply.');
[, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$prefix = $db['prefix'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$module = $pdo->query("SELECT module_srl,mid,module,skin FROM {$prefix}modules WHERE module_srl=115 AND site_srl=0")->fetch();
$category = $pdo->query("SELECT * FROM {$prefix}document_categories WHERE category_srl=170 AND module_srl=115")->fetch();
$item = $pdo->query("SELECT * FROM {$prefix}menu_item WHERE menu_item_srl=173 AND parent_srl=116")->fetch();
$parent = $pdo->query("SELECT name,url FROM {$prefix}menu_item WHERE menu_item_srl=116")->fetch();
if (!$module || $module['mid'] !== 'news' || $module['module'] !== 'board' || $module['skin'] !== 'kitel_generic' ||
    !$category || !in_array($category['title'], ['최근 진행 행사 보고', 'KITEL 활동'], true) ||
    !$item || !in_array($item['name'], ['최근 진행 행사 보고', 'KITEL 활동'], true) ||
    $category['title'] !== $item['name'] ||
    $item['url'] !== '/index.php?mid=news&category=170' || !$parent || $parent['url'] !== 'news')
{
    throw new RuntimeException('Unexpected News category or menu identity. No changes made.');
}
$keys = $pdo->query("SELECT * FROM {$prefix}document_extra_keys WHERE module_srl=115 ORDER BY var_idx")->fetchAll();
$byEid = array_column($keys, null, 'eid');
foreach (['activity_thumbnail' => 'file', 'activity_teaser' => 'text'] as $eid => $type)
{
    if (isset($byEid[$eid]) && $byEid[$eid]['var_type'] !== $type) throw new RuntimeException("Unexpected {$eid} type.");
}
echo "News category 170: {$category['title']} -> KITEL 활동; menu 173: {$item['name']} -> KITEL 활동\n";
foreach (['activity_thumbnail', 'activity_teaser'] as $eid) echo $eid . ': ' . (isset($byEid[$eid]) ? 'present' : 'add') . PHP_EOL;
if (!in_array('--apply', $argv, true)) { echo "DRY RUN. Pass --apply for the identified local development database.\n"; exit; }
$backupDir = 'D:/rhymix_dev/phase9-activity-backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) throw new RuntimeException('Could not create local backup folder.');
$backupPath = $backupDir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
if (file_put_contents($backupPath, json_encode(compact('module', 'category', 'item', 'keys'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false)
{
    throw new RuntimeException('Could not save the local database backup.');
}
$pdo->beginTransaction();
try
{
    $update = $pdo->prepare("UPDATE {$prefix}document_categories SET title=?,description=? WHERE category_srl=170 AND module_srl=115");
    $update->execute(['KITEL 활동', '키텔의 활동 소식을 전합니다']);
    $update = $pdo->prepare("UPDATE {$prefix}menu_item SET name=? WHERE menu_item_srl=173 AND parent_srl=116");
    $update->execute(['KITEL 활동']);
    $next = $keys ? max(array_map(static fn($row) => (int)$row['var_idx'], $keys)) : 0;
    $insert = $pdo->prepare("INSERT INTO {$prefix}document_extra_keys (module_srl,var_idx,var_name,var_type,var_is_required,var_is_strict,var_search,var_sort,var_default,var_options,var_desc,eid) VALUES (115,?,?,?,'N','N','N','N',NULL,NULL,?,?)");
    if (!isset($byEid['activity_thumbnail'])) $insert->execute([++$next, '활동 썸네일', 'file', 'KITEL 활동 카드에 표시할 정사각형 이미지를 선택하세요. 다른 분류에서는 사용하지 않습니다.', 'activity_thumbnail']);
    if (!isset($byEid['activity_teaser'])) $insert->execute([++$next, '활동 소개', 'text', '비로그인 사용자에게도 공개할 짧은 소개입니다. 비워 두면 읽기 권한이 있는 회원에게만 본문 일부가 표시됩니다.', 'activity_teaser']);
    $pdo->commit();
}
catch (Throwable $error) { $pdo->rollBack(); throw $error; }
echo "APPLIED. Backup: {$backupPath}. Rebuild Rhymix menu/category/extra-var cache.\n";
