<?php
/** Sync the Phase 1 IA into the local Rhymix menu and News categories. Dry-run by default. */
declare(strict_types=1);

$apply = in_array('--apply', $argv, true);
$root = 'D:/rhymix_dev/www/rhymix';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--rhymix-root=')) {
        $root = substr($argument, strlen('--rhymix-root='));
    }
}
$root = realpath($root);
if (!$root || !is_file($root . '/files/config/config.php')) {
    throw new RuntimeException('A local Rhymix installation is required.');
}
$config = include $root . '/files/config/config.php';
$db = $config['db']['master'];
if (!in_array($db['host'], ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Refusing a non-local database host.');
}
$prefix = $db['prefix'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
    throw new RuntimeException('Invalid table prefix.');
}
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'] ?: 3306, $db['database']),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$table = static fn(string $name): string => $prefix . $name;
$menu = $pdo->query("SELECT menu_srl FROM {$table('menu')} WHERE title='Main Menu'")->fetchAll();
$news = $pdo->query("SELECT module_srl FROM {$table('modules')} WHERE mid='news' AND module='board'")->fetchAll();
if (count($menu) !== 1 || count($news) !== 1) {
    throw new RuntimeException('Expected exactly one Main Menu and one news board.');
}
$menuId = (int)$menu[0]['menu_srl'];
$newsId = (int)$news[0]['module_srl'];
$items = $pdo->query("SELECT * FROM {$table('menu_item')} WHERE menu_srl={$menuId} ORDER BY parent_srl,listorder DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM {$table('document_categories')} WHERE module_srl={$newsId} ORDER BY list_order")->fetchAll();

$byUrl = [];
$children = [];
foreach ($items as $item) {
    $byUrl[$item['url']] = $item;
    $children[(int)$item['parent_srl']][] = $item;
}
foreach (['about', 'news', 'study'] as $url) {
    if (!isset($byUrl[$url]) || (int)$byUrl[$url]['parent_srl'] !== 0) {
        throw new RuntimeException("Missing top-level menu item: {$url}");
    }
}
$about = ['kitelinfo', 'staff', 'schedule', 'rule'];
$study = ['storage', 'homework', 'exhibition', 'seminar', 'sharing'];
$newsTitles = ['동아리 내 경사', '최근 진행 행사 보고', '일정 공고'];
foreach (['about' => $about, 'study' => $study] as $parent => $expected) {
    $actual = array_column($children[(int)$byUrl[$parent]['menu_item_srl']] ?? [], 'url');
    if (count($actual) !== count($expected) || array_diff($actual, $expected)) {
        throw new RuntimeException("Unexpected {$parent} children; inspect manually before applying.");
    }
}
$newsChildren = $children[(int)$byUrl['news']['menu_item_srl']] ?? [];
if ($newsChildren && (count($newsChildren) !== 3 || array_column($newsChildren, 'name') !== $newsTitles)) {
    throw new RuntimeException('Unexpected News menu children; inspect manually before applying.');
}
if ($categories && (count($categories) !== 3 || array_column($categories, 'title') !== $newsTitles)) {
    throw new RuntimeException('Unexpected News categories; inspect manually before applying.');
}

echo "Main Menu {$menuId}, News board {$newsId}\n";
echo 'About: ' . implode(' > ', $about) . "\n";
echo 'Study: ' . implode(' > ', $study) . "\n";
echo 'News: ' . implode(' > ', $newsTitles) . "\n";
echo $apply ? "Applying to local Rhymix only.\n" : "Dry-run only. Pass --apply to sync.\n";
if (!$apply) {
    exit(0);
}

$backupDir = dirname(dirname($root)) . '/sync-backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Cannot create backup directory.');
}
$backupFile = $backupDir . '/navigation-' . date('Ymd-His') . '.json';
$backup = json_encode(['menu_srl' => $menuId, 'news_module_srl' => $newsId, 'menu_items' => $items, 'news_categories' => $categories], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
if (file_put_contents($backupFile, $backup) === false) {
    throw new RuntimeException('Cannot write backup.');
}
echo "Backup: {$backupFile}\n";

$pdo->beginTransaction();
try {
    $nextId = static function () use ($pdo, $table): int {
        $pdo->exec("INSERT INTO {$table('sequence')} (seq) VALUES (NULL)");
        return (int)$pdo->lastInsertId();
    };
    $updateOrder = $pdo->prepare("UPDATE {$table('menu_item')} SET listorder=? WHERE menu_item_srl=?");
    foreach (['about' => $about, 'study' => $study] as $parent => $urls) {
        foreach ($urls as $index => $url) {
            $updateOrder->execute([(count($urls) - $index) * 10, $byUrl[$url]['menu_item_srl']]);
        }
    }
    if (!$categories) {
        $insertCategory = $pdo->prepare("INSERT INTO {$table('document_categories')} (category_srl,module_srl,parent_srl,title,expand,is_default,document_count,regdate,last_update,list_order) VALUES (?,?,?,?, 'N','N',0,?,?,?)");
        $now = date('YmdHis');
        foreach ($newsTitles as $index => $title) {
            $insertCategory->execute([$nextId(), $newsId, 0, $title, $now, $now, ($index + 1) * 10]);
        }
        $categories = $pdo->query("SELECT * FROM {$table('document_categories')} WHERE module_srl={$newsId} ORDER BY list_order")->fetchAll();
    }
    if (!$newsChildren) {
        $insertItem = $pdo->prepare("INSERT INTO {$table('menu_item')} (menu_item_srl,parent_srl,menu_srl,name,url,is_shortcut,open_window,expand,listorder,regdate) VALUES (?,?,?,?,?,'Y','N','N',?,?)");
        foreach ($categories as $index => $category) {
            $insertItem->execute([$nextId(), $byUrl['news']['menu_item_srl'], $menuId, $category['title'], '/index.php?mid=news&category=' . $category['category_srl'], (3 - $index) * 10, date('YmdHis')]);
        }
    } else {
        foreach ($newsChildren as $index => $item) {
            $expectedUrl = '/index.php?mid=news&category=' . $categories[$index]['category_srl'];
            if ($item['url'] !== $expectedUrl) {
                throw new RuntimeException('Existing News link does not match its category.');
            }
            $updateOrder->execute([(3 - $index) * 10, $item['menu_item_srl']]);
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}
foreach (["{$root}/files/cache/menu/{$menuId}.php", "{$root}/files/cache/menu/{$menuId}.xml.php", "{$root}/files/cache/document_category/{$newsId}.php", "{$root}/files/cache/document_category/{$newsId}.xml.php"] as $cache) {
    if (is_file($cache) && !unlink($cache)) {
        throw new RuntimeException("Applied DB changes but could not remove cache: {$cache}");
    }
}
echo "Navigation synced. Rhymix will regenerate menu/category caches.\n";
