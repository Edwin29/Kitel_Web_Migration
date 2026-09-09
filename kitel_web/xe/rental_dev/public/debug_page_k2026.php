<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
header('Content-Type: text/plain; charset=UTF-8');

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err) {
        echo "\n\n[SHUTDOWN_ERROR]\n";
        echo "type=" . $err['type'] . "\n";
        echo "message=" . $err['message'] . "\n";
        echo "file=" . $err['file'] . "\n";
        echo "line=" . $err['line'] . "\n";
    }
});

$pages = array(
    'index' => __DIR__ . '/index.php',
    'my' => __DIR__ . '/my.php',
    'history' => __DIR__ . '/history.php',
    'admin' => __DIR__ . '/admin/index.php',
    'admin_items' => __DIR__ . '/admin/items.php',
    'admin_categories' => __DIR__ . '/admin/categories.php',
    'admin_loans' => __DIR__ . '/admin/loans.php',
    'admin_logs' => __DIR__ . '/admin/logs.php',
);

$page = isset($_GET['page']) ? $_GET['page'] : 'index';

echo "DEBUG_PAGE=" . $page . "\n";

if (!isset($pages[$page])) {
    echo "UNKNOWN_PAGE\n";
    echo "allowed=" . implode(', ', array_keys($pages)) . "\n";
    exit;
}

$file = $pages[$page];

echo "FILE=" . $file . "\n";
echo "FILE_EXISTS=" . (is_file($file) ? 'yes' : 'no') . "\n\n";

try {
    ob_start();
    require $file;
    $html = ob_get_clean();

    echo "[RESULT]\n";
    echo "RENDER_OK=yes\n";
    echo "OUTPUT_LENGTH=" . strlen($html) . "\n\n";

    echo "[OUTPUT_PREVIEW]\n";
    echo substr($html, 0, 2000);

} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo "[EXCEPTION]\n";
    echo "class=" . get_class($e) . "\n";
    echo "message=" . $e->getMessage() . "\n";
    echo "file=" . $e->getFile() . "\n";
    echo "line=" . $e->getLine() . "\n";
}