<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
header('Content-Type: text/plain; charset=UTF-8');

echo "KITEL rental debug\n";
echo "PHP_VERSION=" . PHP_VERSION . "\n";
echo "SCRIPT=" . __FILE__ . "\n";
echo "DIR=" . __DIR__ . "\n\n";

try {
    echo "[1] path check\n";
    echo "bootstrap exists=" . (is_file(__DIR__ . '/_bootstrap.php') ? 'yes' : 'no') . "\n";
    echo "app exists=" . (is_dir(dirname(__DIR__) . '/app') ? 'yes' : 'no') . "\n";
    echo "config exists=" . (is_file(dirname(__DIR__) . '/app/config.php') ? 'yes' : 'no') . "\n\n";

    echo "[2] load bootstrap\n";
    require_once __DIR__ . '/_bootstrap.php';
    echo "BOOTSTRAP=ok\n\n";

    echo "[3] config\n";
    echo "MODE=" . config('mode') . "\n";
    echo "BASE_URL=" . config('base_url') . "\n";
    echo "CANONICAL_BASE_URL=" . config('canonical_base_url') . "\n";
    echo "XE_ROOT=" . config('xe_root') . "\n";
    echo "XE_ROOT_EXISTS=" . (is_dir(config('xe_root')) ? 'yes' : 'no') . "\n";
    echo "XE_DB_CONFIG_EXISTS=" . (is_file(rtrim(config('xe_root'), '/') . '/files/config/db.config.php') ? 'yes' : 'no') . "\n\n";

    echo "[4] db connect\n";
    $pdo = db_connect();
    echo "DB_CONNECT=ok\n\n";

    echo "[5] current user\n";
    $user = current_user();
    if ($user) {
        echo "LOGIN=yes\n";
        echo "member_srl=" . $user['member_srl'] . "\n";
        echo "user_id=" . $user['user_id'] . "\n";
        echo "nick_name=" . $user['nick_name'] . "\n";
        echo "is_admin=" . $user['is_admin'] . "\n";
        echo "groups=" . implode(',', $user['groups']) . "\n";
    } else {
        echo "LOGIN=no\n";
    }
    echo "\n";

    echo "[6] rental tables\n";
    $tables = array(
        'kitel_rental_allowed_groups',
        'kitel_rental_categories',
        'kitel_rental_items',
        'kitel_rental_loans',
        'kitel_rental_logs',
    );

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute(array($table));
        echo $table . "=" . ($stmt->fetch() ? 'yes' : 'no') . "\n";
    }
    echo "\n";

    echo "[7] permission check\n";
    try {
        $userGroups = group_permissions('user');
        echo "USER_ALLOWED_GROUPS=" . implode(',', $userGroups) . "\n";
    } catch (Throwable $e) {
        echo "USER_ALLOWED_GROUPS_ERROR=" . $e->getMessage() . "\n";
    }

    try {
        $adminGroups = group_permissions('admin');
        echo "ADMIN_ALLOWED_GROUPS=" . implode(',', $adminGroups) . "\n";
    } catch (Throwable $e) {
        echo "ADMIN_ALLOWED_GROUPS_ERROR=" . $e->getMessage() . "\n";
    }
    echo "\n";

    echo "[8] rental load\n";
    $state = rental_load();
    echo "RENTAL_LOAD=ok\n";
    echo "categories=" . count($state['categories']) . "\n";
    echo "items=" . count($state['items']) . "\n";
    echo "loans=" . count($state['loans']) . "\n";

} catch (Throwable $e) {
    echo "\nERROR_CLASS=" . get_class($e) . "\n";
    echo "ERROR_MESSAGE=" . $e->getMessage() . "\n";
    echo "ERROR_FILE=" . $e->getFile() . "\n";
    echo "ERROR_LINE=" . $e->getLine() . "\n";
}