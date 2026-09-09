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

function mark($name) {
    echo "\n--- {$name} ---\n";
    if (function_exists('flush')) flush();
}

try {
    mark('1 require bootstrap');
    require_once __DIR__ . '/_bootstrap.php';
    echo "bootstrap ok\n";

    mark('2 config');
    echo "mode=" . config('mode') . "\n";
    echo "base_url=" . config('base_url') . "\n";

    mark('3 current user');
    $u = current_user();
    if (!$u) {
        echo "current_user=no\n";
    } else {
        echo "current_user=yes\n";
        echo "member_srl=" . $u['member_srl'] . "\n";
        echo "nick_name=" . $u['nick_name'] . "\n";
        echo "is_admin=" . $u['is_admin'] . "\n";
        echo "groups=" . implode(',', $u['groups']) . "\n";
    }

    mark('4 require_admin');
    $user = require_admin();
    echo "require_admin ok\n";

    mark('5 rental_load');
    $state = rental_load();
    echo "rental_load ok\n";
    echo "categories=" . count($state['categories']) . "\n";
    echo "items=" . count($state['items']) . "\n";
    echo "loans=" . loan_query($state, array(), 1, 1)['total'] . "\n";
    echo "logs=" . log_query($state, array(), 1, 1)['total'] . "\n";

    mark('6 active_categories');
    $categories = active_categories($state);
    echo "active_categories=" . count($categories) . "\n";
    foreach ($categories as $c) {
        echo "- " . $c['category_id'] . " / " . $c['name'] . "\n";
    }

    mark('7 active_items');
    $items = active_items($state);
    echo "active_items=" . count($items) . "\n";

    mark('8 render_header test');
    ob_start();
    render_header('기자재 관리', true);
    admin_nav();
    echo "<h1>렌더 테스트</h1>";
    render_footer();
    $html = ob_get_clean();
    echo "render ok\n";
    echo "html length=" . strlen($html) . "\n";
    echo "html preview=\n";
    echo substr(strip_tags($html), 0, 500) . "\n";

    mark('DONE');

} catch (Throwable $e) {
    echo "\n[EXCEPTION]\n";
    echo "class=" . get_class($e) . "\n";
    echo "message=" . $e->getMessage() . "\n";
    echo "file=" . $e->getFile() . "\n";
    echo "line=" . $e->getLine() . "\n";
}