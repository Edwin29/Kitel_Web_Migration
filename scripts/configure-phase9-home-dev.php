<?php
/** Compose the known local Rhymix widget page from repository-owned Phase 9 markup. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

if (array_diff(array_slice($argv, 1), ['--apply'])) throw new RuntimeException('Use no argument or --apply.');
[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
if (!is_file($root . '/widgets/home_dashboard/skins/default/home_dashboard.html') ||
    !is_file($root . '/widgets/hero_banner/skins/default/hero_banner.css')) {
    throw new RuntimeException('Sync Phase 9 widgets to the local Rhymix root first.');
}
$db = $config['db']['master'];
$prefix = $db['prefix'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$module = $pdo->query("SELECT module_srl,mid,module,content,mcontent FROM {$prefix}modules WHERE module_srl=49 AND site_srl=0")->fetch(PDO::FETCH_ASSOC);
if (!$module || $module['mid'] !== 'index' || $module['module'] !== 'page') throw new RuntimeException('Unexpected Home module identity.');
$pageType = $pdo->query("SELECT value FROM {$prefix}module_extra_vars WHERE module_srl=49 AND name='page_type'")->fetchColumn();
if ($pageType !== 'WIDGET') throw new RuntimeException('Home must remain a Rhymix widget page.');
foreach (['news' => [115, 'board'], 'homework' => [149, 'homework'], 'exhibition' => [129, 'board'], 'schedule' => [141, 'calendar']] as $mid => [$srl, $type]) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}modules WHERE module_srl=? AND site_srl=0 AND mid=? AND module=?");
    $check->execute([$srl, $mid, $type]);
    if ((int)$check->fetchColumn() !== 1) throw new RuntimeException("Unexpected Home data source: {$mid}.");
}

$content = (string)$module['content'];
$heroCount = preg_match_all('~<img\b(?=[^>]*\bwidget="hero_banner")[^>]*\/>~i', $content, $heroMatch);
$upcomingCount = preg_match_all('~<img\b(?=[^>]*\bwidget="upcoming_events")[^>]*\/>~i', $content, $upcomingMatch);
if ($heroCount !== 1 || $upcomingCount !== 1) throw new RuntimeException('Expected one existing Hero and Upcoming Events widget.');
if (!str_contains($content, 'data-kitel-home="phase9"')) {
    $remainder = preg_replace('~<img\b[^>]*\/>~is', '', $content);
    if (trim((string)$remainder) !== '' || !str_contains($content, 'widget="widgetContent"')) {
        throw new RuntimeException('Unexpected existing Home composition; preserve it manually before applying.');
    }
    $mobileRemainder = preg_replace('~<img\b[^>]*\/>~is', '', (string)$module['mcontent']);
    if (trim((string)$mobileRemainder) !== '') throw new RuntimeException('Unexpected existing mobile Home composition.');
}

$hero = str_replace('cta_url="/about"', 'cta_url="/kitelinfo"', $heroMatch[0][0]);
$upcoming = $upcomingMatch[0][0];
$template = file_get_contents(__DIR__ . '/phase9-home-page.html');
if ($template === false || substr_count($template, '{{HERO_WIDGET}}') !== 1 || substr_count($template, '{{UPCOMING_WIDGET}}') !== 1) {
    throw new RuntimeException('Invalid repository Home page template.');
}
$newContent = str_replace(['{{HERO_WIDGET}}', '{{UPCOMING_WIDGET}}'], [$hero, $upcoming], $template);
if ($content === $newContent && (string)$module['mcontent'] === $newContent) {
    echo "Home widget page is already configured; no change needed.\n";
    exit;
}
echo 'Home widget page: Hero + live overview/exhibition + Upcoming Events; old Rhymix welcome removed. ' .
    'Desktop bytes ' . strlen($content) . ' -> ' . strlen($newContent) . '; mobile uses the same responsive composition.' . PHP_EOL;
if (!in_array('--apply', $argv, true)) { echo "DRY RUN. Pass --apply for this local development page only.\n"; exit; }

$backupDir = 'D:/rhymix_dev/phase9-home-backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) throw new RuntimeException('Cannot create local Home backup folder.');
$backupPath = $backupDir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
$backup = json_encode(['module_srl' => 49, 'content' => $content, 'mcontent' => $module['mcontent']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
if (file_put_contents($backupPath, $backup) === false) throw new RuntimeException('Cannot back up existing Home content.');
$pdo->beginTransaction();
try {
    $update = $pdo->prepare("UPDATE {$prefix}modules SET content=?,mcontent=? WHERE module_srl=49 AND mid='index' AND module='page'");
    $update->execute([$newContent, $newContent]);
    if ($update->rowCount() !== 1) throw new RuntimeException('Home update did not affect one module.');
    $pdo->commit();
} catch (Throwable $error) { $pdo->rollBack(); throw $error; }
echo "APPLIED. Backup: {$backupPath}. Rebuild Rhymix cache before browser QA.\n";
