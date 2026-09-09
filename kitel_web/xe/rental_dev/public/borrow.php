<?php
require_once __DIR__ . '/_bootstrap.php';
require_post();
$user = require_borrow_permission();
$state = rental_load();
$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$item = find_item($state, $itemId);
$backTo = $item ? 'item.php?code=' . rawurlencode($item['public_code']) : '';
require_trusted_network_or_redirect($backTo);
$dueDate = isset($_POST['due_date']) ? $_POST['due_date'] : default_due_date();
try {
    create_loan(
        $state,
        $itemId,
        isset($_POST['actual_user_name']) ? $_POST['actual_user_name'] : '',
        isset($_POST['actual_user_contact']) ? $_POST['actual_user_contact'] : '',
        $dueDate,
        isset($_POST['note']) ? $_POST['note'] : '',
        $user,
        $user
    );
    rental_save($state);
    flash('대여가 등록되었습니다.');
} catch (RuntimeException $e) {
    flash($e->getMessage());
}
redirect_to('');
