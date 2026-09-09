<?php
$root = dirname(__DIR__);
$required = array(
    'app/bootstrap.php',
    'app/config.php',
    'app/db.php',
    'app/auth.php',
    'app/permissions.php',
    'app/qr.php',
    'app/export.php',
    'public/index.php',
    'public/item.php',
    'public/admin/index.php',
    'public/admin/item_edit.php',
    'public/admin/item_history.php',
    'public/admin/import.php',
    'public/admin/member_history.php',
    'public/admin/permissions.php',
    'database/schema.sql',
    'database/seed.sql',
    'tools/install.php',
);

echo "KITEL rental preflight\n";
echo "PHP_VERSION=" . PHP_VERSION . "\n";

$ok = true;
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    echo ($exists ? 'OK   ' : 'MISS ') . $path . "\n";
    $ok = $ok && $exists;
}

$appDir = getenv('KITEL_RENTAL_APP_DIR');
if ($appDir) {
    echo "OK   KITEL_RENTAL_APP_DIR set\n";
} else {
    echo "WARN KITEL_RENTAL_APP_DIR not set; public/_bootstrap.php will use fallback paths\n";
}

$mode = getenv('KITEL_RENTAL_MODE') ?: 'local';
echo "MODE=" . $mode . "\n";

if ($mode !== 'local') {
    require_once $root . '/app/bootstrap.php';
    if (qr_backend_available()) {
        echo "OK   QR_BACKEND\n";
    } else {
        echo "FAIL QR_BACKEND qrencode not available\n";
        $ok = false;
    }
    try {
        $pdo = db_connect();
        echo "OK   DB_CONNECT\n";
        foreach (array(
            'kitel_rental_allowed_groups',
            'kitel_rental_categories',
            'kitel_rental_items',
            'kitel_rental_loans',
            'kitel_rental_logs',
        ) as $table) {
            $stmt = $pdo->query('SELECT COUNT(*) FROM ' . $table);
            echo "OK   TABLE " . $table . " rows=" . (int)$stmt->fetchColumn() . "\n";
        }
    } catch (Exception $e) {
        echo "FAIL DB_OR_TABLE_CHECK\n";
        $ok = false;
    }
}

exit($ok ? 0 : 1);
