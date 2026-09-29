<?php
/** Preview/apply only the two local board subtitles used by the shared skin. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

[, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$targets = [
    'news' => [115, '동아리 소식과 활동, 일정을 전합니다.'],
    'seminar' => [131, '세미나 관련 내용이 올라오는 게시판'],
];
$newsCategories = [
    169 => ['동아리 내 경사', '동아리 내 경사를 알려드립니다'],
    170 => ['최근 진행 행사 보고', '최근 진행 행사에 관한 게시글이 올라옵니다'],
    171 => ['일정 공고', '일정공고는 이 곳에서 알려드립니다'],
];
$find = $pdo->prepare('SELECT module_srl, mid, module, description FROM ' . $db['prefix'] . 'modules WHERE module_srl = ?');
foreach ($targets as $mid => [$srl, $description]) {
    $find->execute([$srl]);
    $row = $find->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['mid'] !== $mid || $row['module'] !== 'board') {
        throw new RuntimeException("Unexpected board identity for {$mid} ({$srl}); no change made.");
    }
    printf("%s (%d): %s -> %s\n", $mid, $srl, (string)$row['description'], $description);
}
$findCategory = $pdo->prepare('SELECT module_srl, title, description FROM ' . $db['prefix'] . 'document_categories WHERE category_srl = ?');
foreach ($newsCategories as $srl => [$title, $description]) {
    $findCategory->execute([$srl]);
    $row = $findCategory->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['module_srl'] !== 115 || $row['title'] !== $title) {
        throw new RuntimeException("Unexpected News category identity for {$srl}; no change made.");
    }
    printf("category %d (%s): %s -> %s\n", $srl, $title, (string)$row['description'], $description);
}
if (!in_array('--apply', $argv, true)) {
    echo "DRY RUN. Pass --apply to update these two development board descriptions.\n";
    exit;
}
$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE ' . $db['prefix'] . 'modules SET description = ? WHERE module_srl = ? AND mid = ? AND module = ?');
    foreach ($targets as $mid => [$srl, $description]) {
        $update->execute([$description, $srl, $mid, 'board']);
    }
    $updateCategory = $pdo->prepare('UPDATE ' . $db['prefix'] . 'document_categories SET description = ? WHERE category_srl = ? AND module_srl = 115 AND title = ?');
    foreach ($newsCategories as $srl => [$title, $description]) {
        $updateCategory->execute([$description, $srl, $title]);
    }
    $pdo->commit();
    echo "APPLIED to local development DB.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
