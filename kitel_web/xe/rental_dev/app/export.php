<?php
function export_rows($state, $type)
{
    if ($type === 'items') {
        return array(
            'filename' => 'kitel-rental-items',
            'header' => array('item_id', 'label', 'category', 'status', 'location', 'condition_note', 'public_code', 'created_at'),
            'rows' => array_map(function ($item) use ($state) {
                $category = item_category($state, $item);
                return array(
                    $item['item_id'],
                    $item['label'],
                    $category ? $category['name'] : '',
                    status_label($item['status']),
                    $item['location'],
                    $item['condition_note'],
                    $item['public_code'],
                    $item['created_at'],
                );
            }, active_items($state)),
        );
    }

    if ($type === 'loans' || $type === 'active' || $type === 'overdue') {
        $rows = array();
        foreach ($state['loans'] as $loan) {
            if ($type === 'active' && $loan['status'] !== 'borrowed') {
                continue;
            }
            if ($type === 'overdue' && !loan_is_overdue($loan)) {
                continue;
            }
            $item = find_item($state, $loan['item_id']);
            $rows[] = array(
                $loan['loan_id'],
                $item ? $item['label'] : '',
                $item ? $item['public_code'] : '',
                $loan['borrower_member_srl'],
                $loan['borrower_user_id_snapshot'],
                $loan['borrower_name_snapshot'],
                $loan['actual_user_name'],
                $loan['actual_user_contact'],
                $loan['borrowed_at'],
                $loan['due_at'],
                $loan['returned_at'],
                loan_is_overdue($loan) ? '연체' : status_label($loan['status']),
                $loan['admin_note'],
            );
        }
        return array(
            'filename' => 'kitel-rental-' . $type,
            'header' => array('loan_id', 'label', 'public_code', 'borrower_member_srl', 'borrower_user_id', 'borrower_name', 'actual_user_name', 'actual_user_contact', 'borrowed_at', 'due_at', 'returned_at', 'status', 'note'),
            'rows' => $rows,
        );
    }

    if ($type === 'logs') {
        return array(
            'filename' => 'kitel-rental-logs',
            'header' => array('log_id', 'created_at', 'actor_member_srl', 'action', 'item_id', 'loan_id', 'before_status', 'after_status', 'memo'),
            'rows' => array_map(function ($log) {
                return array(
                    $log['log_id'],
                    $log['created_at'],
                    $log['actor_member_srl'],
                    $log['action'],
                    $log['item_id'],
                    $log['loan_id'],
                    $log['before_status'],
                    $log['after_status'],
                    $log['memo'],
                );
            }, $state['logs']),
        );
    }

    return null;
}
