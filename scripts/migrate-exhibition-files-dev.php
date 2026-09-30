<?php
/** Move known development exhibition uploads outside the Rhymix web root. Dry-run by default. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$board = $pdo->query("SELECT module_srl, module, skin FROM {$db['prefix']}modules WHERE site_srl=0 AND mid='exhibition'")->fetchAll(PDO::FETCH_ASSOC);
if (count($board) !== 1 || (int)$board[0]['module_srl'] !== 129 ||
    $board[0]['module'] !== 'board' || $board[0]['skin'] !== 'kitel_gallery') {
    throw new RuntimeException('Unexpected exhibition board; no files moved.');
}
$attachRoot = realpath($root . '/files/attach');
$privateRoot = realpath($root . '/../..') . '/kitel-private/exhibition';
if (!$attachRoot || !$privateRoot || str_starts_with($privateRoot, $root . DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Unsafe storage roots.');
}
$query = $pdo->query("SELECT file_srl, uploaded_filename, thumbnail_filename FROM {$db['prefix']}files WHERE module_srl=129 ORDER BY file_srl");
$moves = [];
foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $file) {
    if (!str_starts_with($file['uploaded_filename'], './files/attach/')) continue;
    if ($file['thumbnail_filename']) throw new RuntimeException('Review thumbnail migration before moving file ' . $file['file_srl']);
    $source = realpath($root . '/' . substr($file['uploaded_filename'], 2));
    if (!$source || !str_starts_with($source, $attachRoot . DIRECTORY_SEPARATOR) || !is_file($source)) {
        throw new RuntimeException('Missing or unsafe source file ' . $file['file_srl']);
    }
    $name = bin2hex(random_bytes(20));
    $moves[] = [
        'file_srl' => (int)$file['file_srl'],
        'old_db' => $file['uploaded_filename'],
        'old_path' => $source,
        'new_db' => './../../kitel-private/exhibition/' . $name,
        'new_path' => $privateRoot . '/' . $name,
    ];
}
foreach ($moves as $move) {
    echo $move['file_srl'] . ': ' . $move['old_db'] . ' -> ' . $move['new_db'] . PHP_EOL;
}
if (!in_array('--apply', $argv, true)) {
    echo 'DRY RUN. ' . count($moves) . ' file(s) would move. Pass --apply for this local DB only.' . PHP_EOL;
    exit;
}
if (!$moves) { echo 'Nothing to move.' . PHP_EOL; exit; }
if (!is_dir($privateRoot) && !mkdir($privateRoot, 0700, true)) {
    throw new RuntimeException('Cannot create private storage.');
}
$manifest = realpath($root . '/../..') . '/qa-fixtures/phase7-file-migration-' . date('Ymd-His') . '.json';
if (!is_dir(dirname($manifest))) mkdir(dirname($manifest), 0700, true);
file_put_contents($manifest, json_encode($moves, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$done = [];
$pdo->beginTransaction();
try {
    $update = $pdo->prepare("UPDATE {$db['prefix']}files SET uploaded_filename=? WHERE file_srl=? AND module_srl=129 AND uploaded_filename=?");
    foreach ($moves as $move) {
        if (!rename($move['old_path'], $move['new_path'])) throw new RuntimeException('Move failed for ' . $move['file_srl']);
        $done[] = $move;
        $update->execute([$move['new_db'], $move['file_srl'], $move['old_db']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('DB update failed for ' . $move['file_srl']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    foreach (array_reverse($done) as $move) rename($move['new_path'], $move['old_path']);
    throw $e;
}
echo 'Moved ' . count($done) . ' file(s). Recovery manifest: ' . $manifest . PHP_EOL;
