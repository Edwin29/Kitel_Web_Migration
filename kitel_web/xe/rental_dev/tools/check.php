<?php
$root = dirname(__DIR__);

// app/ 아래 파일은 하나라도 없으면 앱이 뜨지 않으므로 전부 확인한다.
// 예전 목록에는 items/loans/categories/bundles/storage/helpers/logger/layout이
// 빠져 있어서, 앱의 절반이 없는 배포도 이 점검을 통과했다.
$required = array(
    'app/bootstrap.php',
    'app/config.php',
    'app/helpers.php',
    'app/db.php',
    'app/storage.php',
    'app/auth.php',
    'app/permissions.php',
    'app/logger.php',
    'app/qr.php',
    'app/categories.php',
    'app/bundles.php',
    'app/items.php',
    'app/loans.php',
    'app/export.php',
    'app/layout.php',
    'public/_bootstrap.php',
    'public/index.php',
    'public/item.php',
    'public/catalog.php',
    'public/category.php',
    'public/bundle.php',
    'public/scan.php',
    'public/scan_add.php',
    'public/borrow.php',
    'public/borrow_bulk.php',
    'public/return.php',
    'public/rent_list.php',
    'public/return_list.php',
    'public/my.php',
    'public/history.php',
    'public/login.php',
    'public/logout.php',
    'public/admin/index.php',
    'public/admin/items.php',
    'public/admin/item_new.php',
    'public/admin/item_edit.php',
    'public/admin/item_detail.php',
    'public/admin/item_history.php',
    'public/admin/categories.php',
    'public/admin/loans.php',
    'public/admin/loan_new.php',
    'public/admin/logs.php',
    'public/admin/import.php',
    'public/admin/export.php',
    'public/admin/member_history.php',
    'public/admin/permissions.php',
    'public/admin/qr.php',
    'public/admin/qr_print.php',
    'public/admin/network_check.php',
    'public/.htaccess',
    'public/admin/.htaccess',
    'public/assets/xlsx.full.min.js',
    'public/assets/style.css',
    'public/assets/jsQR.js',
    'app/.htaccess',
    'database/.htaccess',
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

// 모드는 반드시 앱과 같은 방식으로 판단한다 — app/config.php를 그대로 읽는다.
// 예전에는 여기서만 기본값을 'local'로 잡아서(앱은 'production'), 환경변수를 넣지
// 않은 운영 서버에서 이 점검이 DB/qrencode 검사를 통째로 건너뛰고 성공으로 끝났다.
require_once $root . '/app/bootstrap.php';
$mode = config('mode');
echo "MODE=" . $mode . ($mode === 'production' && !getenv('KITEL_RENTAL_MODE') ? ' (config.php 기본값)' : '') . "\n";

if ($mode !== 'local') {
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
