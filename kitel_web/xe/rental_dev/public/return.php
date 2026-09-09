<?php
require_once __DIR__ . '/_bootstrap.php';
require_post();
$user = require_borrow_permission();
$state = rental_load();
require_trusted_network_or_redirect('my.php');

$loanIds = (isset($_POST['loan_ids']) && is_array($_POST['loan_ids'])) ? array_map('intval', $_POST['loan_ids']) : array();

if ($loanIds) {
    if (config('require_qr_on_return')) {
        flash('이 모드에서는 기자재 QR 상세 화면에서만 반납할 수 있습니다.');
        redirect_to('my.php');
    }
    $returned = 0;
    foreach ($loanIds as $loanId) {
        $loan = null;
        foreach ($state['loans'] as $row) {
            if ((int)$row['loan_id'] === $loanId) {
                $loan = $row;
                break;
            }
        }
        if (!$loan || ((int)$loan['borrower_member_srl'] !== (int)$user['member_srl'] && !user_is_admin($user))) {
            continue;
        }
        if (return_loan($state, $loanId, $user, 'user', '사용자 반납')) {
            $returned++;
        }
    }
    rental_save($state);
    $message = $returned . '개 반납 처리되었습니다.';
    if ($returned < count($loanIds)) {
        $message .= ' (' . (count($loanIds) - $returned) . '개는 처리하지 못함)';
    }
    flash($message);
    redirect_to('my.php');
}

$loanId = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0;
$returnCode = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
$loan = null;
foreach ($state['loans'] as $row) {
    if ((int)$row['loan_id'] === $loanId) {
        $loan = $row;
        break;
    }
}
if (!$loan || ((int)$loan['borrower_member_srl'] !== (int)$user['member_srl'] && !user_is_admin($user))) {
    flash('반납할 수 없는 대여 건입니다.');
    redirect_to('my.php');
}
if (config('require_qr_on_return')) {
    $item = find_item($state, $loan['item_id']);
    if (!$item || !hash_equals($item['public_code'], $returnCode)) {
        flash('반납 QR 확인이 필요합니다.');
        redirect_to('my.php');
    }
}
return_loan($state, $loanId, $user, 'user', '사용자 반납');
rental_save($state);
flash('반납 처리되었습니다.');
redirect_to('my.php');
