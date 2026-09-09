<?php
// CSV 내보내기.
//
// 예전에는 export_rows()가 전체 행을 배열로 만들어 돌려줬고, 대여/로그는
// $state에 통째로 올라와 있던 배열을 그대로 썼다. 지금은 화면과 동일한 필터를
// 받아서 조회 계층을 통해 청크 단위로 흘려보낸다. 메모리도 안 쌓이고,
// "화면에서 필터를 걸고 CSV를 눌렀는데 전체가 내려오는" 문제도 없어진다.

function export_definition($type)
{
    $definitions = array(
        'items' => array(
            'filename' => 'kitel-rental-items',
            'header' => array('item_id', 'label', 'category', 'status', 'location', 'condition_note', 'public_code', 'created_at'),
        ),
        'loans' => array(
            'filename' => 'kitel-rental-loans',
            'header' => array('loan_id', 'label', 'public_code', 'borrower_member_srl', 'borrower_user_id', 'borrower_name', 'actual_user_name', 'actual_user_contact', 'borrowed_at', 'due_at', 'returned_at', 'status', 'note'),
        ),
        'logs' => array(
            'filename' => 'kitel-rental-logs',
            'header' => array('log_id', 'created_at', 'actor_member_srl', 'actor_name', 'action', 'item_id', 'item_label', 'loan_id', 'before_status', 'after_status', 'memo'),
        ),
    );
    return isset($definitions[$type]) ? $definitions[$type] : null;
}

// 예전 export.php는 type으로 loans/active/overdue를 나눠 받았다. 이제는 loans 하나로
// 받고 status 필터로 구분하므로, 옛 링크가 남아 있어도 동작하게 매핑해 둔다.
function export_normalize_type($type, array $filters)
{
    if ($type === 'active') {
        $filters['status'] = 'borrowed';
        return array('loans', $filters);
    }
    if ($type === 'overdue') {
        $filters['status'] = 'overdue';
        return array('loans', $filters);
    }
    return array($type, $filters);
}

// 행을 하나씩 만들어 $emit 콜백에 넘긴다.
function export_each_row($state, $type, array $filters, callable $emit)
{
    if ($type === 'items') {
        foreach (item_rows_filtered($state, $filters) as $item) {
            $category = item_category($state, $item);
            $emit(array(
                $item['item_id'],
                $item['label'],
                $category ? $category['name'] : '',
                status_label($item['status']),
                $item['location'],
                $item['condition_note'],
                $item['public_code'],
                $item['created_at'],
            ));
        }
        return;
    }

    if ($type === 'loans') {
        loan_each_filtered($state, $filters, function ($loan) use ($state, $emit) {
            $item = find_item($state, $loan['item_id']);
            $emit(array(
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
            ));
        });
        return;
    }

    if ($type === 'logs') {
        log_each_filtered($state, $filters, function ($log) use ($state, $emit) {
            $item = $log['item_id'] ? find_item($state, $log['item_id']) : null;
            $emit(array(
                $log['log_id'],
                $log['created_at'],
                $log['actor_member_srl'],
                actor_display_name($log['actor_member_srl']),
                $log['action'],
                $log['item_id'],
                $item ? $item['label'] : '',
                $log['loan_id'],
                $log['before_status'],
                $log['after_status'],
                $log['memo'],
            ));
        });
        return;
    }
}
