<?php
require_once __DIR__ . '/_bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

function scan_json_response($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_post()) {
    http_response_code(405);
    scan_json_response(array('ok' => false, 'message' => 'Method Not Allowed'));
}

$user = current_user();
if (!$user || !user_can_borrow($user)) {
    http_response_code(403);
    scan_json_response(array('ok' => false, 'message' => '로그인이 필요합니다.'));
}

$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!hash_equals(csrf_token(), $token)) {
    http_response_code(419);
    scan_json_response(array('ok' => false, 'message' => '세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'));
}

$mode = isset($_POST['mode']) ? $_POST['mode'] : '';
if (!in_array($mode, array('rent', 'return'), true)) {
    scan_json_response(array('ok' => false, 'message' => '잘못된 요청입니다.'));
}

$state = rental_load();
$targetType = isset($_POST['target_type']) ? $_POST['target_type'] : '';

if ($targetType === 'bundle_options') {
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    try {
        $options = bundle_scan_options($state, $code, $mode);
        scan_json_response(array(
            'ok' => true,
            'bundle' => $options['bundle'],
            'categories' => $options['categories'],
        ));
    } catch (RuntimeException $e) {
        scan_json_response(array('ok' => false, 'message' => $e->getMessage()));
    }
}

if ($targetType === 'bundle_item') {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $item = find_item($state, $itemId);
    if (!$item || (int)$item['is_active'] !== 1) {
        scan_json_response(array('ok' => false, 'message' => '선택한 물품을 찾을 수 없습니다.'));
    }
    if ($mode === 'rent') {
        if ($item['status'] !== 'available') {
            scan_json_response(array('ok' => false, 'message' => $item['label'] . ': 지금은 대여할 수 없습니다 (' . status_label($item['status']) . ').'));
        }
        $added = cart_add_item('rent_list', $item['item_id'], $item['label']);
        scan_json_response(array(
            'ok' => true,
            'message' => $item['label'] . ($added ? ' 담았습니다.' : ' 이미 담겨 있습니다.'),
            'count' => cart_count('rent_list'),
        ));
    }
    $activeLoan = active_loan_for_item($state, $item['item_id']);
    if (!$activeLoan) {
        scan_json_response(array('ok' => false, 'message' => $item['label'] . ': 지금 대여 중이 아닙니다.'));
    }
    $added = cart_add_item('return_list', $item['item_id'], $item['label']);
    scan_json_response(array(
        'ok' => true,
        'message' => $item['label'] . ($added ? ' 담았습니다.' : ' 이미 담겨 있습니다.'),
        'count' => cart_count('return_list'),
    ));
}

if ($targetType === 'item') {
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    $item = find_item_by_code($state, $code);
    if (!$item) {
        scan_json_response(array('ok' => false, 'message' => '등록되지 않은 QR입니다.'));
    }
    if ($mode === 'rent') {
        if ($item['status'] !== 'available') {
            scan_json_response(array('ok' => false, 'message' => $item['label'] . ': 지금은 대여할 수 없습니다 (' . status_label($item['status']) . ').'));
        }
        $added = cart_add_item('rent_list', $item['item_id'], $item['label']);
        scan_json_response(array(
            'ok' => true,
            'message' => $item['label'] . ($added ? ' 담았습니다.' : ' 이미 담겨 있습니다.'),
            'count' => cart_count('rent_list'),
        ));
    }
    $activeLoan = active_loan_for_item($state, $item['item_id']);
    if (!$activeLoan) {
        scan_json_response(array('ok' => false, 'message' => $item['label'] . ': 지금 대여 중이 아닙니다.'));
    }
    $added = cart_add_item('return_list', $item['item_id'], $item['label']);
    scan_json_response(array(
        'ok' => true,
        'message' => $item['label'] . ($added ? ' 담았습니다.' : ' 이미 담겨 있습니다.'),
        'count' => cart_count('return_list'),
    ));
}

if ($targetType === 'category') {
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $category = find_category($state, $categoryId);
    if (!$category || category_tracking_mode($category) !== 'bulk') {
        scan_json_response(array('ok' => false, 'message' => '등록되지 않은 카테고리 QR입니다.'));
    }
    if ($quantity <= 0) {
        scan_json_response(array('ok' => false, 'message' => '수량을 1개 이상 입력해 주세요.'));
    }
    $counts = category_item_counts($state, $categoryId);
    if ($mode === 'rent') {
        if ($quantity > $counts['available']) {
            scan_json_response(array('ok' => false, 'message' => $category['name'] . ': 가용 재고(' . $counts['available'] . '개)보다 많습니다.'));
        }
        cart_set_category_quantity('rent_list', $categoryId, $category['name'], $quantity);
        scan_json_response(array(
            'ok' => true,
            'message' => $category['name'] . ' ' . $quantity . '개 담았습니다.',
            'count' => cart_count('rent_list'),
        ));
    }
    if ($quantity > $counts['borrowed']) {
        scan_json_response(array('ok' => false, 'message' => $category['name'] . ': 현재 대여 중인 개수(' . $counts['borrowed'] . '개)보다 많습니다.'));
    }
    cart_set_category_quantity('return_list', $categoryId, $category['name'], $quantity);
    scan_json_response(array(
        'ok' => true,
        'message' => $category['name'] . ' ' . $quantity . '개 담았습니다.',
        'count' => cart_count('return_list'),
    ));
}

scan_json_response(array('ok' => false, 'message' => '알 수 없는 QR입니다.'));
