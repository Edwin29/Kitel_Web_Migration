<?php
/** Configure local Integrated Search with an explicitly public content source. Dry-run by default. */
declare(strict_types=1);

require_once __DIR__ . '/dev-environment-guard.php';
[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'] ?: 3306, $db['database']),
    $db['user'], $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$modules = $db['prefix'] . 'modules';
$grants = $db['prefix'] . 'module_grants';
$moduleConfig = $db['prefix'] . 'module_config';
$rows = $pdo->query("SELECT module_srl FROM {$modules} WHERE site_srl=0 AND mid='exhibition' AND module='board'")->fetchAll();
if (count($rows) !== 1) {
    throw new RuntimeException('Expected one exhibition board.');
}
$targetId = (int)$rows[0]['module_srl'];
$grant = $pdo->prepare("SELECT 1 FROM {$grants} WHERE module_srl=? AND name='view' AND group_srl=0");
$grant->execute([$targetId]);
if (!$grant->fetch()) {
    throw new RuntimeException('Exhibition must allow public document viewing before it can be indexed.');
}
$existing = $pdo->query("SELECT * FROM {$moduleConfig} WHERE module='integration_search' AND site_srl=0")->fetchAll();
if (count($existing) > 1) {
    throw new RuntimeException('Unexpected duplicate search configuration.');
}
$desired = (object)[
    'skin' => 'default',
    'mskin' => '/USE_RESPONSIVE/',
    'block_robots' => true,
    'target_types' => ['document' => true, 'comment' => false, 'multimedia' => false, 'file' => false],
    'target' => 'include',
    'target_module_srl' => (string)$targetId,
];
if ($existing && $existing[0]['config'] !== serialize($desired)) {
    throw new RuntimeException('Existing search configuration differs; inspect it before applying.');
}
echo "Integrated Search: public exhibition board {$targetId}; document results only.\n";
if (!in_array('--apply', $argv, true)) {
    echo "Dry-run only. Pass --apply to configure local Rhymix.\n";
    exit(0);
}
$backupDir = dirname(dirname($root)) . '/sync-backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Cannot create backup directory.');
}
$backupFile = $backupDir . '/search-config-' . date('Ymd-His') . '.json';
if (file_put_contents($backupFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot write backup.');
}
if (!$existing) {
    $insert = $pdo->prepare("INSERT INTO {$moduleConfig} (module,site_srl,config,regdate) VALUES ('integration_search',0,?,?)");
    $insert->execute([serialize($desired), date('YmdHis')]);
}
// Rhymix uses a file cache in this development installation. Remove only this key.
$cacheDir = $root . '/files/cache/store';
if (is_dir($cacheDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $handle = fopen($file->getPathname(), 'rb');
        $firstLine = $handle ? fgets($handle) : '';
        if ($handle) fclose($handle);
        if (str_contains($firstLine ?: '', ':module_config:integration_search ')) {
            if (!unlink($file->getPathname())) throw new RuntimeException('Configured search but could not invalidate its cache.');
        }
    }
}
echo "Search configured. Backup: {$backupFile}\n";
