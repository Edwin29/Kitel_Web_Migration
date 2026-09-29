<?php
/** Move local development Homework submissions out of the Rhymix document root. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run', '--copy', '--purge-public'], true) || count($argv) > 2) {
    throw new RuntimeException('Use --dry-run, --copy, or --purge-public.');
}
[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$prefix = $db['prefix'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']), $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$module = $pdo->query("SELECT module_srl FROM {$prefix}modules WHERE mid='homework' AND module='homework'")->fetchColumn();
if ((int)$module !== 149) throw new RuntimeException('Unexpected development Homework module.');

$public = $root . '/files/attach/homework/149';
$privateRoot = getenv('KITEL_HOMEWORK_PRIVATE_ROOT') ?: dirname(dirname($root)) . '/kitel-homework-private';
if (!preg_match('~^(?:[a-zA-Z]:[/\\\\]|/)~', $privateRoot)) throw new RuntimeException('Private root must be absolute.');
$private = rtrim($privateRoot, '/\\') . '/149';
if (is_link($public) || strcasecmp(str_replace('\\', '/', (string)realpath($public)), str_replace('\\', '/', $public)) !== 0 || is_link(dirname($private))) throw new RuntimeException('Unexpected storage path.');
if (str_starts_with(str_replace('\\', '/', $private) . '/', str_replace('\\', '/', $root) . '/')) throw new RuntimeException('Private path is public.');

$names = $pdo->query("SELECT DISTINCT stored_filename FROM {$prefix}homework_submission WHERE module_srl=149 AND stored_filename IS NOT NULL AND stored_filename<>''")->fetchAll(PDO::FETCH_COLUMN);
$expected = [];
foreach ($names as $name) {
    if (!is_string($name) || basename($name) !== $name || preg_match('/[\\x00-\\x1f\\\\\/]/', $name)) throw new RuntimeException('Unsafe stored filename.');
    $expected[$name] = true;
}
$imageNames = [];
if (is_link($public . '/task-images')) throw new RuntimeException('Unexpected public image symlink.');
$taskIds = array_fill_keys($pdo->query("SELECT task_srl FROM {$prefix}homework_task WHERE module_srl=149")->fetchAll(PDO::FETCH_COLUMN), true);
foreach (glob($public . '/task-images/*') ?: [] as $path) {
    $name = basename($path);
    if (is_link($path) || !is_file($path) || !preg_match('/^([0-9]+)_[a-f0-9]{16}\.(?:jpg|png|webp|gif)$/', $name, $match) || !isset($taskIds[$match[1]])) throw new RuntimeException('Unexpected public task image: ' . $name);
    $imageNames[$name] = true;
}
$topLevel = glob($public . '/*') ?: [];
foreach ($topLevel as $path) {
    if (is_dir($path)) continue; // task-images are a separate, controlled image route.
    if (is_link($path) || !isset($expected[basename($path)])) throw new RuntimeException('Untracked public Homework file: ' . basename($path));
}
foreach (array_keys($expected) as $name) {
    $source = $public . '/' . $name;
    $target = $private . '/' . $name;
    if (!is_file($source) && !is_file($target)) throw new RuntimeException('Missing Homework attachment: ' . $name);
    if (is_file($source) && is_link($source)) throw new RuntimeException('Unsafe source symlink.');
    if (is_file($target) && (is_link($target) || is_file($source) && hash_file('sha256', $source) !== hash_file('sha256', $target))) throw new RuntimeException('Private copy differs: ' . $name);
}
foreach (array_keys($imageNames) as $name) {
    $source = $public . '/task-images/' . $name;
    $target = $private . '/task-images/' . $name;
    if (is_file($target) && (is_link($target) || hash_file('sha256', $source) !== hash_file('sha256', $target))) throw new RuntimeException('Private image differs: ' . $name);
}
printf("%s: %d submission files, %d task images; public=%s private=%s\n", $mode, count($expected), count($imageNames), $public, $private);
if ($mode === '--dry-run') exit;

if (!is_dir($private) && !mkdir($private, 0700, true) && !is_dir($private)) throw new RuntimeException('Cannot create private storage.');
if (!realpath($private) || str_starts_with(str_replace('\\', '/', realpath($private)) . '/', str_replace('\\', '/', $root) . '/')) throw new RuntimeException('Private storage resolved under web root.');
if ($imageNames && !is_dir($private . '/task-images') && !mkdir($private . '/task-images', 0700, true) && !is_dir($private . '/task-images')) throw new RuntimeException('Cannot create private image storage.');
foreach (array_keys($expected) as $name) {
    $source = $public . '/' . $name;
    $target = $private . '/' . $name;
    if ($mode === '--copy' && is_file($source) && !is_file($target)) {
        $temp = $target . '.copying-' . bin2hex(random_bytes(4));
        if (!copy($source, $temp) || hash_file('sha256', $source) !== hash_file('sha256', $temp) || !rename($temp, $target)) throw new RuntimeException('Copy failed: ' . $name);
    }
    if ($mode === '--purge-public' && is_file($source)) {
        if (!is_file($target) || hash_file('sha256', $source) !== hash_file('sha256', $target)) throw new RuntimeException('Cannot verify private copy: ' . $name);
        if (!unlink($source)) throw new RuntimeException('Cannot remove public copy: ' . $name);
    }
}
foreach (array_keys($imageNames) as $name) {
    $source = $public . '/task-images/' . $name;
    $target = $private . '/task-images/' . $name;
    if ($mode === '--copy' && !is_file($target)) {
        $temp = $target . '.copying-' . bin2hex(random_bytes(4));
        if (!copy($source, $temp) || hash_file('sha256', $source) !== hash_file('sha256', $temp) || !rename($temp, $target)) throw new RuntimeException('Image copy failed: ' . $name);
    }
    if ($mode === '--purge-public') {
        if (!is_file($target) || hash_file('sha256', $source) !== hash_file('sha256', $target) || !unlink($source)) throw new RuntimeException('Cannot safely purge image: ' . $name);
    }
}
echo "Completed {$mode}.\n";
