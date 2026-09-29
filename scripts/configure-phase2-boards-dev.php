<?php
/** Bind the shared board skin to the two Phase 2 boards in the known local DB. Preview by default. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$targets = ['news' => 115, 'seminar' => 131];
$find = $pdo->prepare('SELECT module_srl, mid, module, skin, is_skin_fix FROM ' . $db['prefix'] . 'modules WHERE module_srl = ?');
foreach ($targets as $mid => $srl) {
    $find->execute([$srl]);
    $row = $find->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['mid'] !== $mid || $row['module'] !== 'board') {
        throw new RuntimeException("Unexpected board identity for {$mid} ({$srl}); no change made.");
    }
    printf("%s (%d): %s / %s -> kitel_generic / Y\n", $mid, $srl, $row['skin'], $row['is_skin_fix']);
}
if (!in_array('--apply', $argv, true)) {
    echo "DRY RUN. Pass --apply to update only these two development board settings.\n";
    exit;
}
$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE ' . $db['prefix'] . 'modules SET skin = ?, is_skin_fix = ? WHERE module_srl = ? AND mid = ? AND module = ?');
    foreach ($targets as $mid => $srl) {
        $update->execute(['kitel_generic', 'Y', $srl, $mid, 'board']);
    }
    $pdo->commit();
    echo "APPLIED to local development DB.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
