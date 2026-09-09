<?php
function active_loan_for_item($state, $itemId)
{
    foreach ($state['loans'] as $loan) {
        if ((int)$loan['item_id'] === (int)$itemId && $loan['status'] === 'borrowed') {
            return $loan;
        }
    }
    return null;
}

function loan_is_overdue($loan)
{
    return $loan['status'] === 'borrowed' && date_only($loan['due_at']) < date('Y-m-d');
}

// 카테고리에 1인당 제한(max_per_user)이 설정되어 있으면 이를 검사한다.
// $ignoreLimit이 true면 건너뛴다 (관리자가 예외적으로 등록할 때 사용).
function enforce_category_limit($state, $categoryId, $memberSrl, $ignoreLimit)
{
    if ($ignoreLimit) {
        return;
    }
    $category = find_category($state, $categoryId);
    $maxPerUser = $category ? category_max_per_user($category) : 0;
    if ($maxPerUser <= 0) {
        return;
    }
    $current = user_active_loan_count_in_category($state, $memberSrl, $categoryId);
    if ($current + 1 > $maxPerUser) {
        throw new RuntimeException(
            '이 물품은 1인당 최대 ' . $maxPerUser . '개까지 대여할 수 있습니다. 현재 ' . $current . '개 대여 중입니다.'
        );
    }
}

function user_active_loan_count_in_category($state, $memberSrl, $categoryId)
{
    $count = 0;
    foreach ($state['loans'] as $loan) {
        if ($loan['status'] !== 'borrowed' || (int)$loan['borrower_member_srl'] !== (int)$memberSrl) {
            continue;
        }
        $item = find_item($state, $loan['item_id']);
        if ($item && (int)$item['category_id'] === (int)$categoryId) {
            $count++;
        }
    }
    return $count;
}

function create_loan(&$state, $itemId, $actualName, $contact, $dueDate, $note, $actor, $borrower, $ignoreLimit = false)
{
    $dueDate = normalize_due_date($dueDate);
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT status, category_id FROM kitel_rental_items WHERE item_id = ? AND is_active = 1 FOR UPDATE');
            $stmt->execute(array($itemId));
            $row = $stmt->fetch();
            if (!$row || $row['status'] !== 'available') {
                throw new RuntimeException('이미 대여 중이거나 대여할 수 없는 기자재입니다.');
            }
            enforce_category_limit($state, (int)$row['category_id'], $borrower['member_srl'], $ignoreLimit);
            $now = db_now();
            $insert = $pdo->prepare('
                INSERT INTO kitel_rental_loans
                    (item_id, borrower_member_srl, borrower_user_id_snapshot, borrower_name_snapshot, actual_user_name, actual_user_contact, borrowed_at, due_at, returned_at, status, created_by_member_srl, returned_by_member_srl, return_type, admin_note, related_loan_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, "borrowed", ?, NULL, NULL, ?, NULL, ?, ?)
            ');
            $insert->execute(array(
                $itemId,
                $borrower['member_srl'],
                $borrower['user_id'],
                $borrower['nick_name'],
                trim($actualName),
                trim($contact),
                $now,
                $dueDate . ' 23:59:59',
                $actor['member_srl'],
                trim($note),
                $now,
                $now,
            ));
            $loanId = (int)$pdo->lastInsertId();
            $update = $pdo->prepare('UPDATE kitel_rental_items SET status = "borrowed", updated_at = ? WHERE item_id = ? AND status = "available"');
            $update->execute(array($now, $itemId));
            add_log($state, 'loan.create', $itemId, $loanId, 'available', 'borrowed', $note, $actor);
            $pdo->commit();
            return array('loan_id' => $loanId, 'item_id' => $itemId, 'status' => 'borrowed');
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    foreach ($state['items'] as &$item) {
        if ((int)$item['item_id'] !== (int)$itemId) {
            continue;
        }
        if ($item['status'] !== 'available' || active_loan_for_item($state, $itemId)) {
            throw new RuntimeException('이미 대여 중이거나 대여할 수 없는 기자재입니다.');
        }
        enforce_category_limit($state, (int)$item['category_id'], $borrower['member_srl'], $ignoreLimit);
        $loan = array(
            'loan_id' => next_id($state, 'loan_id'),
            'item_id' => (int)$itemId,
            'borrower_member_srl' => (int)$borrower['member_srl'],
            'borrower_user_id_snapshot' => $borrower['user_id'],
            'borrower_name_snapshot' => $borrower['nick_name'],
            'actual_user_name' => trim($actualName),
            'actual_user_contact' => trim($contact),
            'borrowed_at' => now_text(),
            'due_at' => $dueDate . ' 23:59:59',
            'returned_at' => null,
            'status' => 'borrowed',
            'created_by_member_srl' => (int)$actor['member_srl'],
            'returned_by_member_srl' => null,
            'return_type' => null,
            'admin_note' => trim($note),
            'related_loan_id' => null,
            'created_at' => now_text(),
            'updated_at' => now_text(),
        );
        $item['status'] = 'borrowed';
        $item['updated_at'] = now_text();
        $state['loans'][] = $loan;
        add_log($state, 'loan.create', $itemId, $loan['loan_id'], 'available', 'borrowed', $note, $actor);
        unset($item);
        return $loan;
    }
    unset($item);
    throw new RuntimeException('기자재를 찾을 수 없습니다.');
}

// 개수 관리(bulk) 카테고리 전용 대여: item_id 대신 category_id + quantity를 받아서
// 내부적으로 create_loan()을 quantity번 호출한다. 재고 부족/개인 한도 초과로
// 중간에 막히면 그때까지 성공한 만큼만 반영하고 이유를 함께 돌려준다.
function create_bulk_loan(&$state, $categoryId, $quantity, $actualName, $contact, $dueDate, $note, $actor, $borrower, $ignoreLimit = false)
{
    $quantity = max(1, (int)$quantity);
    $category = find_category($state, $categoryId);
    if (!$category) {
        throw new RuntimeException('카테고리를 찾을 수 없습니다.');
    }
    if (category_tracking_mode($category) !== 'bulk') {
        throw new RuntimeException('개수 관리 카테고리가 아닙니다.');
    }

    $created = 0;
    $reason = null;
    for ($i = 0; $i < $quantity; $i++) {
        $availableItem = find_available_item_in_category($state, $categoryId);
        if (!$availableItem) {
            $reason = '재고 부족';
            break;
        }
        try {
            create_loan($state, $availableItem['item_id'], $actualName, $contact, $dueDate, $note, $actor, $borrower, $ignoreLimit);
            $created++;
        } catch (RuntimeException $e) {
            $reason = $e->getMessage();
            break;
        }
        if (config('mode') !== 'local') {
            $state = rental_load();
        }
    }
    if ($created === 0) {
        throw new RuntimeException($reason ? $reason : '대여할 수 없습니다.');
    }
    return array('created' => $created, 'requested' => $quantity, 'reason' => $reason);
}

// 체크박스로 선택한 여러 대여 건을 한 번에 반납 처리. 개수 관리 카테고리에서
// "몇 개 반납할지" 낱개로 골라 체크하는 흐름을 그대로 지원한다.
// 카테고리 안에서 현재 대여 중인 loan을 N개 골라낸다 (누가 빌렸는지는 안 따짐 -
// 준회원이 빌리고 다른 정회원이 반납 처리하는 경우가 있어서 대여자 일치 여부는 조건이 아니다).
function pick_active_loans_in_category($state, $categoryId, $limit)
{
    $picked = array();
    foreach ($state['loans'] as $loan) {
        if ($loan['status'] !== 'borrowed') {
            continue;
        }
        $item = find_item($state, $loan['item_id']);
        if ($item && (int)$item['category_id'] === (int)$categoryId) {
            $picked[] = (int)$loan['loan_id'];
            if (count($picked) >= $limit) {
                break;
            }
        }
    }
    return $picked;
}

// 카테고리 단위로 "N개 반납" - 실제로는 활성 대여 중 N개를 골라 return_loan()에 넘긴다.
function return_bulk_in_category(&$state, $categoryId, $quantity, $actor, $type, $memo)
{
    $loanIds = pick_active_loans_in_category($state, $categoryId, (int)$quantity);
    $result = return_loans_bulk($state, $loanIds, $actor, $type, $memo);
    $result['requested'] = (int)$quantity;
    return $result;
}

function return_loans_bulk(&$state, array $loanIds, $actor, $type, $memo)
{
    $returned = 0;
    foreach (array_unique(array_map('intval', $loanIds)) as $loanId) {
        if (return_loan($state, $loanId, $actor, $type, $memo)) {
            $returned++;
        }
    }
    return array('returned' => $returned, 'requested' => count($loanIds));
}

function return_loan(&$state, $loanId, $actor, $type, $memo)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM kitel_rental_loans WHERE loan_id = ? AND status = "borrowed" FOR UPDATE');
            $stmt->execute(array($loanId));
            $loan = $stmt->fetch();
            if (!$loan) {
                $pdo->commit();
                return false;
            }
            $newStatus = $type === 'force' ? 'force_returned' : 'returned';
            $now = db_now();
            $updateLoan = $pdo->prepare('
                UPDATE kitel_rental_loans
                SET status = ?, returned_at = ?, returned_by_member_srl = ?, return_type = ?, admin_note = ?, updated_at = ?
                WHERE loan_id = ?
            ');
            $updateLoan->execute(array($newStatus, $now, $actor['member_srl'], $type, trim($memo), $now, $loanId));
            $updateItem = $pdo->prepare('UPDATE kitel_rental_items SET status = "available", updated_at = ? WHERE item_id = ?');
            $updateItem->execute(array($now, $loan['item_id']));
            add_log($state, $type === 'force' ? 'loan.force_return' : 'loan.return', $loan['item_id'], $loanId, 'borrowed', 'available', $memo, $actor);
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    foreach ($state['loans'] as &$loan) {
        if ((int)$loan['loan_id'] !== (int)$loanId || $loan['status'] !== 'borrowed') {
            continue;
        }
        $loan['status'] = $type === 'force' ? 'force_returned' : 'returned';
        $loan['returned_at'] = now_text();
        $loan['returned_by_member_srl'] = (int)$actor['member_srl'];
        $loan['return_type'] = $type;
        $loan['admin_note'] = trim($memo);
        $loan['updated_at'] = now_text();
        foreach ($state['items'] as &$item) {
            if ((int)$item['item_id'] === (int)$loan['item_id']) {
                $item['status'] = 'available';
                $item['updated_at'] = now_text();
                break;
            }
        }
        unset($item);
        add_log($state, $type === 'force' ? 'loan.force_return' : 'loan.return', $loan['item_id'], $loan['loan_id'], 'borrowed', 'available', $memo, $actor);
        unset($loan);
        return true;
    }
    unset($loan);
    return false;
}

function user_active_loans($state, $memberSrl)
{
    return array_values(array_filter($state['loans'], function ($loan) use ($memberSrl) {
        return (int)$loan['borrower_member_srl'] === (int)$memberSrl && $loan['status'] === 'borrowed';
    }));
}

function user_all_loans($state, $memberSrl)
{
    return array_values(array_filter($state['loans'], function ($loan) use ($memberSrl) {
        return (int)$loan['borrower_member_srl'] === (int)$memberSrl;
    }));
}

function item_all_loans($state, $itemId)
{
    return array_values(array_filter($state['loans'], function ($loan) use ($itemId) {
        return (int)$loan['item_id'] === (int)$itemId;
    }));
}

// 개수 관리(bulk) 카테고리는 내부적으로 낱개 item마다 별도 loan 행을 갖고 있지만,
// 화면(내 대여/내 기록/관리자 대여 현황)에는 "카테고리명 (N개)"로 하나만 보여준다.
// 개별 관리 물품은 그대로 한 줄씩 보여준다. 대여자가 다르거나 같은 카테고리라도
// 반납예정일/상태가 다르면(예: 다른 사람, 다른 날 빌린 묶음) 별도 줄로 나눈다.
function group_loans_for_display($state, $loans)
{
    $groups = array();
    $order = array();
    foreach ($loans as $loan) {
        $item = find_item($state, $loan['item_id']);
        $category = $item ? item_category($state, $item) : null;
        if ($category && category_tracking_mode($category) === 'bulk') {
            $key = 'cat:' . $category['category_id'] . ':' . $loan['borrower_member_srl'] . ':' . $loan['status'] . ':' . $loan['due_at'] . ':' . $loan['borrowed_at'];
            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'label' => $category['name'],
                    'count' => 0,
                    'borrowed_at' => $loan['borrowed_at'],
                    'due_at' => $loan['due_at'],
                    'returned_at' => $loan['returned_at'],
                    'status' => $loan['status'],
                    'borrower_name' => $loan['borrower_name_snapshot'],
                    'borrower_member_srl' => (int)$loan['borrower_member_srl'],
                    'actual_user_name' => $loan['actual_user_name'],
                    'actual_user_contact' => $loan['actual_user_contact'],
                    'admin_note' => $loan['admin_note'],
                    'category_id' => (int)$category['category_id'],
                    'loan_ids' => array(),
                );
                $order[] = $key;
            }
            $groups[$key]['count']++;
            $groups[$key]['loan_ids'][] = (int)$loan['loan_id'];
        } else {
            $key = 'item:' . $loan['loan_id'];
            $groups[$key] = array(
                'label' => $item ? $item['label'] : '삭제된 기자재',
                'count' => 1,
                'borrowed_at' => $loan['borrowed_at'],
                'due_at' => $loan['due_at'],
                'returned_at' => $loan['returned_at'],
                'status' => $loan['status'],
                'borrower_name' => $loan['borrower_name_snapshot'],
                'borrower_member_srl' => (int)$loan['borrower_member_srl'],
                'actual_user_name' => $loan['actual_user_name'],
                'actual_user_contact' => $loan['actual_user_contact'],
                'admin_note' => $loan['admin_note'],
                'loan_id' => (int)$loan['loan_id'],
                'loan_ids' => array((int)$loan['loan_id']),
                'item_id' => (int)$loan['item_id'],
            );
            $order[] = $key;
        }
    }
    $result = array();
    foreach ($order as $key) {
        $result[] = $groups[$key];
    }
    return $result;
}
