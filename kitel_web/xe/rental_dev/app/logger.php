<?php
function add_log(&$state, $action, $itemId, $loanId, $before, $after, $memo, $actor)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('
            INSERT INTO kitel_rental_logs
                (item_id, loan_id, actor_member_srl, action, before_status, after_status, memo, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $itemId,
            $loanId,
            $actor ? $actor['member_srl'] : null,
            $action,
            $before,
            $after,
            $memo,
            db_now(),
        ));
        return;
    }
    $state['logs'][] = array(
        'log_id' => next_id($state, 'log_id'),
        'item_id' => $itemId,
        'loan_id' => $loanId,
        'actor_member_srl' => $actor ? $actor['member_srl'] : null,
        'action' => $action,
        'before_status' => $before,
        'after_status' => $after,
        'memo' => $memo,
        'created_at' => now_text(),
    );
}
