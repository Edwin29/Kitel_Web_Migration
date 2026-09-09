<?php
require_once __DIR__ . '/_bootstrap.php';
require_post();
$user = require_borrow_permission();
$state = rental_load();
$categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
require_trusted_network_or_redirect('category.php?id=' . $categoryId);
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$dueDate = isset($_POST['due_date']) ? $_POST['due_date'] : default_due_date();
try {
    $result = create_bulk_loan(
        $state,
        $categoryId,
        $quantity,
        isset($_POST['actual_user_name']) ? $_POST['actual_user_name'] : '',
        isset($_POST['actual_user_contact']) ? $_POST['actual_user_contact'] : '',
        $dueDate,
        isset($_POST['note']) ? $_POST['note'] : '',
        $user,
        $user
    );
    rental_save($state);
    $message = $result['created'] . '개 대여가 등록되었습니다.';
    if ($result['created'] < $result['requested']) {
        $message .= ' (' . ($result['requested'] - $result['created']) . '개는 처리하지 못함: ' . $result['reason'] . ')';
    }
    flash($message);
} catch (RuntimeException $e) {
    flash($e->getMessage());
}
redirect_to('category.php?id=' . $categoryId);
