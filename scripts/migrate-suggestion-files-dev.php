<?php
/** Move existing local suggestion attachments outside the web root. Dry-run by default. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';
if (array_diff(array_slice($argv, 1), ['--apply'])) throw new RuntimeException('Use no argument or --apply.');
[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$module = $pdo->query("SELECT module_srl,module,skin FROM {$db['prefix']}modules WHERE site_srl=0 AND mid='suggestion'")->fetch(PDO::FETCH_ASSOC);
if (!$module || (int)$module['module_srl'] !== 135 || $module['module'] !== 'board' || $module['skin'] !== 'kitel_generic') {
    throw new RuntimeException('Unexpected suggestion board; no files moved.');
}
$attachRoot = realpath($root . '/files/attach');
$privateRoot = realpath($root . '/../..') . '/kitel-private/suggestion';
if (!$attachRoot || !$privateRoot || str_starts_with($privateRoot, $root . DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Unsafe storage roots.');
}
$rows = $pdo->query("SELECT file_srl,uploaded_filename,thumbnail_filename FROM {$db['prefix']}files WHERE module_srl=135 ORDER BY file_srl")->fetchAll(PDO::FETCH_ASSOC);
$moves = [];
foreach ($rows as $row) {
    if (!str_starts_with($row['uploaded_filename'], './files/attach/')) continue;
    if ($row['thumbnail_filename']) throw new RuntimeException('Review thumbnail before moving file ' . $row['file_srl']);
    $source = realpath($root . '/' . substr($row['uploaded_filename'], 2));
    if (!$source || !str_starts_with($source, $attachRoot . DIRECTORY_SEPARATOR) || !is_file($source)) {
        throw new RuntimeException('Missing or unsafe file ' . $row['file_srl']);
    }
    $name = bin2hex(random_bytes(20));
    $moves[] = ['file_srl'=>(int)$row['file_srl'], 'old_db'=>$row['uploaded_filename'], 'old_path'=>$source,
        'new_db'=>'./../../kitel-private/suggestion/' . $name, 'new_path'=>$privateRoot . '/' . $name];
}
echo count($moves) . " file(s) need private storage.\n";
if (!in_array('--apply', $argv, true)) { echo "DRY RUN. Pass --apply for this local DB only.\n"; exit; }
if (!$moves) { echo "Nothing to move.\n"; exit; }
if (!is_dir($privateRoot) && !mkdir($privateRoot, 0700, true)) throw new RuntimeException('Cannot create private storage.');
$manifest = realpath($root . '/../..') . '/qa-fixtures/phase8-file-migration-' . date('Ymd-His') . '.json';
if (!is_dir(dirname($manifest))) mkdir(dirname($manifest), 0700, true);
file_put_contents($manifest, json_encode($moves, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$done = [];
$pdo->beginTransaction();
try {
    $update = $pdo->prepare("UPDATE {$db['prefix']}files SET uploaded_filename=?,direct_download='N' WHERE file_srl=? AND module_srl=135 AND uploaded_filename=?");
    foreach ($moves as $move) {
        if (!rename($move['old_path'], $move['new_path'])) throw new RuntimeException('Move failed for ' . $move['file_srl']);
        $done[] = $move;
        $update->execute([$move['new_db'], $move['file_srl'], $move['old_db']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('DB update failed for ' . $move['file_srl']);
    }
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    foreach (array_reverse($done) as $move) rename($move['new_path'], $move['old_path']);
    throw $error;
}
echo 'Moved ' . count($done) . ' file(s). Recovery manifest: ' . $manifest . PHP_EOL;
