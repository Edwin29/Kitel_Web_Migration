<?php
function active_loan_for_item($state, $itemId)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('SELECT * FROM kitel_rental_loans WHERE item_id = ? AND status = "borrowed" LIMIT 1');
        $stmt->execute(array((int)$itemId));
        $row = $stmt->fetch();
        return $row ? $row : null;
    }
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
//
// 운영 모드에서는 반드시 create_loan()의 트랜잭션 안에서 호출되며, 개수도 DB에서
// 직접 센다. 예전에는 요청 시작 시점에 읽어둔 $state 배열을 셌기 때문에 바로 위의
// FOR UPDATE가 한도 검사를 전혀 보호하지 못했고, 동시에 대여하면 한도가 뚫렸다.
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
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM kitel_rental_loans l
            JOIN kitel_rental_items i ON i.item_id = l.item_id
            WHERE l.status = "borrowed" AND l.borrower_member_srl = ? AND i.category_id = ?
        ');
        $stmt->execute(array((int)$memberSrl, (int)$categoryId));
        return (int)$stmt->fetchColumn();
    }
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
        $txOwned = db_begin();
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
            db_commit($txOwned);
            return array('loan_id' => $loanId, 'item_id' => $itemId, 'status' => 'borrowed');
        } catch (Exception $e) {
            db_rollback($txOwned);
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

    $dueDate = normalize_due_date($dueDate);
    $reason = null;

    // 운영 모드: 재고 확보부터 대여 생성까지 한 트랜잭션에서 끝낸다.
    // 예전에는 create_loan()을 quantity번 호출하면서 매번 rental_load()로 전 테이블을
    // 다시 읽었다(느림). 게다가 재고를 요청 시작 시점의 $state에서 골라서, 두 사람이
    // 동시에 빌리면 같은 물건을 집었다.
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $txOwned = db_begin();
        try {
            $maxPerUser = category_max_per_user($category);
            if (!$ignoreLimit && $maxPerUser > 0) {
                $current = user_active_loan_count_in_category($state, $borrower['member_srl'], $categoryId);
                $allowance = $maxPerUser - $current;
                if ($allowance <= 0) {
                    throw new RuntimeException(
                        '이 물품은 1인당 최대 ' . $maxPerUser . '개까지 대여할 수 있습니다. 현재 ' . $current . '개 대여 중입니다.'
                    );
                }
                if ($allowance < $quantity) {
                    $quantity = $allowance;
                    $reason = '1인당 제한(' . $maxPerUser . '개)에 걸려 ' . $allowance . '개만 처리했습니다.';
                }
            }

            $pick = $pdo->prepare('
                SELECT item_id FROM kitel_rental_items
                WHERE category_id = ? AND status = "available" AND is_active = 1
                ORDER BY item_id
                LIMIT ' . (int)$quantity . '
                FOR UPDATE
            ');
            $pick->execute(array($categoryId));
            $itemIds = array_map('intval', $pick->fetchAll(PDO::FETCH_COLUMN));
            if (!$itemIds) {
                throw new RuntimeException('재고 부족');
            }
            if (count($itemIds) < $quantity) {
                $reason = '재고 부족으로 ' . count($itemIds) . '개만 처리했습니다.';
            }

            $now = db_now();
            $insert = $pdo->prepare('
                INSERT INTO kitel_rental_loans
                    (item_id, borrower_member_srl, borrower_user_id_snapshot, borrower_name_snapshot, actual_user_name, actual_user_contact, borrowed_at, due_at, returned_at, status, created_by_member_srl, returned_by_member_srl, return_type, admin_note, related_loan_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, "borrowed", ?, NULL, NULL, ?, NULL, ?, ?)
            ');
            $markBorrowed = $pdo->prepare('UPDATE kitel_rental_items SET status = "borrowed", updated_at = ? WHERE item_id = ?');

            $loanIds = array();
            foreach ($itemIds as $itemId) {
                $insert->execute(array(
                    $itemId, $borrower['member_srl'], $borrower['user_id'], $borrower['nick_name'],
                    trim($actualName), trim($contact), $now, $dueDate . ' 23:59:59',
                    $actor['member_srl'], trim($note), $now, $now,
                ));
                $loanId = (int)$pdo->lastInsertId();
                $loanIds[] = $loanId;
                $markBorrowed->execute(array($now, $itemId));
                add_log($state, 'loan.create', $itemId, $loanId, 'available', 'borrowed', $note, $actor);
            }

            // 이 묶음의 첫 loan_id를 전원의 related_loan_id로 심는다. 화면에서 한 줄로
            // 묶는 기준이자, 페이지를 나눠도 묶음이 갈라지지 않게 하는 키다.
            $groupId = $loanIds[0];
            $linkPlaceholders = implode(',', array_fill(0, count($loanIds), '?'));
            $link = $pdo->prepare('UPDATE kitel_rental_loans SET related_loan_id = ? WHERE loan_id IN (' . $linkPlaceholders . ')');
            $link->execute(array_merge(array($groupId), $loanIds));

            db_commit($txOwned);
            return array('created' => count($loanIds), 'requested' => $quantity, 'reason' => $reason);
        } catch (Exception $e) {
            db_rollback($txOwned);
            throw $e;
        }
    }

    $created = 0;
    $reason = null;
    $groupId = null;
    for ($i = 0; $i < $quantity; $i++) {
        $availableItem = find_available_item_in_category($state, $categoryId);
        if (!$availableItem) {
            $reason = '재고 부족';
            break;
        }
        try {
            $loan = create_loan($state, $availableItem['item_id'], $actualName, $contact, $dueDate, $note, $actor, $borrower, $ignoreLimit);
            if ($groupId === null) {
                $groupId = (int)$loan['loan_id'];
            }
            foreach ($state['loans'] as &$stored) {
                if ((int)$stored['loan_id'] === (int)$loan['loan_id']) {
                    $stored['related_loan_id'] = $groupId;
                    break;
                }
            }
            unset($stored);
            $created++;
        } catch (RuntimeException $e) {
            $reason = $e->getMessage();
            break;
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
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('
            SELECT l.loan_id
            FROM kitel_rental_loans l
            JOIN kitel_rental_items i ON i.item_id = l.item_id
            WHERE l.status = "borrowed" AND i.category_id = ?
            ORDER BY l.borrowed_at, l.loan_id
            LIMIT ' . max(0, (int)$limit)
        );
        $stmt->execute(array((int)$categoryId));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
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

// 카테고리 단위로 "N개 반납". 대상 선정과 반납 처리를 한 트랜잭션 안에서 함께
// 수행한다 (예전에는 요청 시작 시점의 $state에서 골라서, 동시에 처리하면 이미
// 반납된 건을 집었다).
function return_bulk_in_category(&$state, $categoryId, $quantity, $actor, $type, $memo, $itemStatus = 'available')
{
    $quantity = max(0, (int)$quantity);
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $txOwned = db_begin();
        try {
            $stmt = $pdo->prepare('
                SELECT l.loan_id
                FROM kitel_rental_loans l
                JOIN kitel_rental_items i ON i.item_id = l.item_id
                WHERE l.status = "borrowed" AND i.category_id = ?
                ORDER BY l.borrowed_at, l.loan_id
                LIMIT ' . $quantity . '
                FOR UPDATE
            ');
            $stmt->execute(array((int)$categoryId));
            $loanIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $returned = 0;
            foreach ($loanIds as $loanId) {
                if (return_loan_in_tx($state, $pdo, $loanId, $actor, $type, $memo, $itemStatus)) {
                    $returned++;
                }
            }
            db_commit($txOwned);
            return array('returned' => $returned, 'requested' => $quantity);
        } catch (Exception $e) {
            db_rollback($txOwned);
            throw $e;
        }
    }

    $loanIds = pick_active_loans_in_category($state, $categoryId, $quantity);
    $result = return_loans_bulk($state, $loanIds, $actor, $type, $memo, $itemStatus);
    $result['requested'] = $quantity;
    return $result;
}

// 여러 건을 한 번에 반납. 운영 모드에서는 전체가 하나의 트랜잭션이라
// 중간에 실패하면 아무것도 반영되지 않는다 (예전에는 건별 트랜잭션이라
// 절반만 처리된 상태로 남을 수 있었다).
function return_loans_bulk(&$state, array $loanIds, $actor, $type, $memo, $itemStatus = 'available')
{
    $loanIds = array_values(array_unique(array_map('intval', $loanIds)));
    $requested = count($loanIds);

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $txOwned = db_begin();
        try {
            $returned = 0;
            foreach ($loanIds as $loanId) {
                if (return_loan_in_tx($state, $pdo, $loanId, $actor, $type, $memo, $itemStatus)) {
                    $returned++;
                }
            }
            db_commit($txOwned);
            return array('returned' => $returned, 'requested' => $requested);
        } catch (Exception $e) {
            db_rollback($txOwned);
            throw $e;
        }
    }

    $returned = 0;
    foreach ($loanIds as $loanId) {
        if (return_loan($state, $loanId, $actor, $type, $memo, $itemStatus)) {
            $returned++;
        }
    }
    return array('returned' => $returned, 'requested' => $requested);
}

// 이미 열려 있는 트랜잭션 안에서 한 건을 반납 처리한다.
// 트랜잭션 관리는 호출한 쪽 책임.
function return_loan_in_tx(&$state, $pdo, $loanId, $actor, $type, $memo, $itemStatus = 'available')
{
    if (!in_array($itemStatus, array('available', 'unavailable', 'broken', 'lost'), true)) {
        throw new RuntimeException('반납 후 상태로 지정할 수 없는 값입니다.');
    }
    $stmt = $pdo->prepare('SELECT * FROM kitel_rental_loans WHERE loan_id = ? AND status = "borrowed" FOR UPDATE');
    $stmt->execute(array((int)$loanId));
    $loan = $stmt->fetch();
    if (!$loan) {
        return false;
    }
    $newStatus = $type === 'force' ? 'force_returned' : 'returned';
    $now = db_now();
    $updateLoan = $pdo->prepare('
        UPDATE kitel_rental_loans
        SET status = ?, returned_at = ?, returned_by_member_srl = ?, return_type = ?, admin_note = ?, updated_at = ?
        WHERE loan_id = ?
    ');
    $updateLoan->execute(array($newStatus, $now, $actor['member_srl'], $type, trim($memo), $now, (int)$loanId));
    $updateItem = $pdo->prepare('UPDATE kitel_rental_items SET status = ?, updated_at = ? WHERE item_id = ?');
    $updateItem->execute(array($itemStatus, $now, $loan['item_id']));
    add_log($state, $type === 'force' ? 'loan.force_return' : 'loan.return', $loan['item_id'], (int)$loanId, 'borrowed', $itemStatus, $memo, $actor);
    return true;
}

// 한 건 반납. $itemStatus로 반납 직후의 기자재 상태를 지정할 수 있다
// (기본은 '대여 가능'. 고장난 채로 돌아온 물건을 반납과 동시에 broken으로
// 넘길 수 있게 하려고 인자로 뺐다. 예전에는 무조건 available로 되돌려서,
// 관리자가 반납 처리 후 상태 변경을 또 해야 했다).
function return_loan(&$state, $loanId, $actor, $type, $memo, $itemStatus = 'available')
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $txOwned = db_begin();
        try {
            $ok = return_loan_in_tx($state, $pdo, $loanId, $actor, $type, $memo, $itemStatus);
            db_commit($txOwned);
            return $ok;
        } catch (Exception $e) {
            db_rollback($txOwned);
            throw $e;
        }
    }
    if (!in_array($itemStatus, array('available', 'unavailable', 'broken', 'lost'), true)) {
        throw new RuntimeException('반납 후 상태로 지정할 수 없는 값입니다.');
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
                $item['status'] = $itemStatus;
                $item['updated_at'] = now_text();
                break;
            }
        }
        unset($item);
        add_log($state, $type === 'force' ? 'loan.force_return' : 'loan.return', $loan['item_id'], $loan['loan_id'], 'borrowed', $itemStatus, $memo, $actor);
        unset($loan);
        return true;
    }
    unset($loan);
    return false;
}

// ---------------------------------------------------------------------------
// 조회 계층
//
// 화면은 여기를 통해서만 대여 기록을 읽는다. 정렬은 항상 "최신 우선"으로 이 안에서
// 확정되므로 화면에서 array_reverse() 하지 않는다. 예전에는 운영 모드가 DESC로
// 읽어오는데 화면이 로컬 모드(시간순 append)를 전제로 한 번 더 뒤집어서, 운영에서만
// 목록이 오래된 순으로 나오는 버그가 있었다.
// ---------------------------------------------------------------------------

// 대여 기록의 그룹 키. 개수 관리(bulk) 카테고리를 한 번에 여러 개 빌리면 낱개마다
// loan 행이 생기지만 화면에는 "니퍼 (10개)" 한 줄로 보여야 한다. create_bulk_loan()이
// 그 묶음의 첫 loan_id를 related_loan_id에 심어두므로, 이 값이 곧 묶음 식별자다.
// 개별 관리 물품은 related_loan_id가 NULL이라 자기 loan_id가 그대로 그룹이 된다.
function loan_group_key($loan)
{
    return (isset($loan['related_loan_id']) && (int)$loan['related_loan_id'] > 0)
        ? (int)$loan['related_loan_id']
        : (int)$loan['loan_id'];
}

// $_GET에서 대여 목록 필터를 뽑아낸다. 화면과 CSV 내보내기가 같은 필터를 쓰도록
// 한 곳에서 해석한다.
function loan_filters_from_request()
{
    return array(
        'q' => isset($_GET['q']) ? trim($_GET['q']) : '',
        'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
        'from' => isset($_GET['from']) ? trim($_GET['from']) : '',
        'to' => isset($_GET['to']) ? trim($_GET['to']) : '',
        'member_srl' => isset($_GET['member_srl']) ? (int)$_GET['member_srl'] : 0,
        'item_id' => isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0,
    );
}

function loan_filters_defaults(array $filters = array())
{
    return $filters + array(
        'q' => '', 'status' => '', 'from' => '', 'to' => '',
        'member_srl' => 0, 'item_id' => 0,
    );
}

// 필터를 SQL 조각과 바인딩 값으로 변환한다.
function loan_filter_sql(array $filters)
{
    $where = array();
    $args = array();

    if ($filters['status'] === 'overdue') {
        $where[] = 'l.status = "borrowed" AND DATE(l.due_at) < CURDATE()';
    } elseif ($filters['status'] !== '') {
        $where[] = 'l.status = ?';
        $args[] = $filters['status'];
    }
    if ($filters['from'] !== '') {
        $where[] = 'DATE(l.borrowed_at) >= ?';
        $args[] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $where[] = 'DATE(l.borrowed_at) <= ?';
        $args[] = $filters['to'];
    }
    if ((int)$filters['member_srl'] > 0) {
        $where[] = 'l.borrower_member_srl = ?';
        $args[] = (int)$filters['member_srl'];
    }
    if ((int)$filters['item_id'] > 0) {
        $where[] = 'l.item_id = ?';
        $args[] = (int)$filters['item_id'];
    }
    if ($filters['q'] !== '') {
        $where[] = '(i.label LIKE ? OR i.public_code LIKE ? OR l.borrower_name_snapshot LIKE ?'
            . ' OR l.borrower_user_id_snapshot LIKE ? OR l.actual_user_name LIKE ? OR l.actual_user_contact LIKE ?)';
        $like = '%' . $filters['q'] . '%';
        for ($i = 0; $i < 6; $i++) {
            $args[] = $like;
        }
    }

    return array($where ? 'WHERE ' . implode(' AND ', $where) : '', $args);
}

function loan_matches_filters($state, $loan, array $filters)
{
    if ($filters['status'] === 'overdue') {
        if (!loan_is_overdue($loan)) {
            return false;
        }
    } elseif ($filters['status'] !== '' && $loan['status'] !== $filters['status']) {
        return false;
    }
    if ($filters['from'] !== '' && date_only($loan['borrowed_at']) < $filters['from']) {
        return false;
    }
    if ($filters['to'] !== '' && date_only($loan['borrowed_at']) > $filters['to']) {
        return false;
    }
    if ((int)$filters['member_srl'] > 0 && (int)$loan['borrower_member_srl'] !== (int)$filters['member_srl']) {
        return false;
    }
    if ((int)$filters['item_id'] > 0 && (int)$loan['item_id'] !== (int)$filters['item_id']) {
        return false;
    }
    if ($filters['q'] !== '') {
        $item = find_item($state, $loan['item_id']);
        $haystack = implode(' ', array(
            $item ? $item['label'] : '',
            $item ? $item['public_code'] : '',
            $loan['borrower_name_snapshot'],
            $loan['borrower_user_id_snapshot'],
            $loan['actual_user_name'],
            $loan['actual_user_contact'],
        ));
        if (stripos($haystack, $filters['q']) === false) {
            return false;
        }
    }
    return true;
}

// 로컬 모드에서 조건에 맞는 대여를 최신순으로 모은다.
function loan_rows_local($state, array $filters)
{
    $rows = array();
    foreach ($state['loans'] as $loan) {
        if (loan_matches_filters($state, $loan, $filters)) {
            $rows[] = $loan;
        }
    }
    usort($rows, function ($a, $b) {
        $order = strcmp($b['borrowed_at'], $a['borrowed_at']);
        return $order !== 0 ? $order : ((int)$b['loan_id'] <=> (int)$a['loan_id']);
    });
    return $rows;
}

// 화면용 대여 목록. 페이지 단위는 "행"이 아니라 "묶음"이다. 묶음이 페이지 경계에
// 걸쳐 잘리면 "니퍼 (7개)"와 "니퍼 (3개)"로 갈라져 보이므로, 먼저 묶음 키를
// 페이지만큼 고른 뒤 그 묶음에 속한 행을 전부 가져온다.
function loan_query($state, array $filters, $page = 1, $perPage = 50)
{
    $filters = loan_filters_defaults($filters);
    $page = max(1, (int)$page);
    $perPage = max(1, (int)$perPage);

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        list($whereSql, $args) = loan_filter_sql($filters);

        $countStmt = $pdo->prepare('
            SELECT COUNT(DISTINCT COALESCE(l.related_loan_id, l.loan_id))
            FROM kitel_rental_loans l
            JOIN kitel_rental_items i ON i.item_id = l.item_id
            ' . $whereSql
        );
        $countStmt->execute($args);
        $total = (int)$countStmt->fetchColumn();
        if ($total === 0) {
            return rental_page(array(), 0, $page, $perPage);
        }

        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $keyStmt = $pdo->prepare('
            SELECT COALESCE(l.related_loan_id, l.loan_id) AS group_key
            FROM kitel_rental_loans l
            JOIN kitel_rental_items i ON i.item_id = l.item_id
            ' . $whereSql . '
            GROUP BY group_key
            ORDER BY MAX(l.borrowed_at) DESC, group_key DESC
            LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
        );
        $keyStmt->execute($args);
        $keys = array_map('intval', $keyStmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$keys) {
            return rental_page(array(), $total, $page, $perPage);
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rowStmt = $pdo->prepare('
            SELECT l.*
            FROM kitel_rental_loans l
            WHERE COALESCE(l.related_loan_id, l.loan_id) IN (' . $placeholders . ')
            ORDER BY l.borrowed_at DESC, l.loan_id DESC
        ');
        $rowStmt->execute($keys);
        return rental_page($rowStmt->fetchAll(), $total, $page, $perPage);
    }

    $rows = loan_rows_local($state, $filters);
    $groups = array();
    foreach ($rows as $row) {
        $groups[loan_group_key($row)][] = $row;
    }
    $total = count($groups);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $slice = array_slice($groups, ($page - 1) * $perPage, $perPage, true);
    $out = array();
    foreach ($slice as $group) {
        foreach ($group as $row) {
            $out[] = $row;
        }
    }
    return rental_page($out, $total, $page, $perPage);
}

// CSV 내보내기용. 화면과 같은 필터를 쓰되 페이지를 나누지 않는다.
// 메모리를 한 번에 채우지 않도록 청크 단위로 콜백에 넘긴다.
function loan_each_filtered($state, array $filters, callable $callback, $chunk = 500)
{
    $filters = loan_filters_defaults($filters);

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        list($whereSql, $args) = loan_filter_sql($filters);
        $offset = 0;
        do {
            $stmt = $pdo->prepare('
                SELECT l.*
                FROM kitel_rental_loans l
                JOIN kitel_rental_items i ON i.item_id = l.item_id
                ' . $whereSql . '
                ORDER BY l.borrowed_at DESC, l.loan_id DESC
                LIMIT ' . (int)$chunk . ' OFFSET ' . (int)$offset
            );
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $callback($row);
            }
            $offset += $chunk;
        } while (count($rows) === $chunk);
        return;
    }

    foreach (loan_rows_local($state, $filters) as $row) {
        $callback($row);
    }
}

function loan_find($state, $loanId)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('SELECT * FROM kitel_rental_loans WHERE loan_id = ?');
        $stmt->execute(array((int)$loanId));
        $row = $stmt->fetch();
        return $row ? $row : null;
    }
    foreach ($state['loans'] as $loan) {
        if ((int)$loan['loan_id'] === (int)$loanId) {
            return $loan;
        }
    }
    return null;
}

// 대시보드 지표. 예전에는 전체 loans 배열을 메모리에 올려 count()로 셌다.
function loan_dashboard_counts($state)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $row = $pdo->query('
            SELECT
              COUNT(*) AS borrowed,
              SUM(CASE WHEN DATE(due_at) < CURDATE() THEN 1 ELSE 0 END) AS overdue,
              SUM(CASE WHEN DATE(due_at) = CURDATE() THEN 1 ELSE 0 END) AS due_today
            FROM kitel_rental_loans
            WHERE status = "borrowed"
        ')->fetch();
        return array(
            'borrowed' => (int)$row['borrowed'],
            'overdue' => (int)$row['overdue'],
            'due_today' => (int)$row['due_today'],
        );
    }
    $borrowed = 0;
    $overdue = 0;
    $dueToday = 0;
    foreach ($state['loans'] as $loan) {
        if ($loan['status'] !== 'borrowed') {
            continue;
        }
        $borrowed++;
        if (loan_is_overdue($loan)) {
            $overdue++;
        } elseif (date_only($loan['due_at']) === date('Y-m-d')) {
            $dueToday++;
        }
    }
    return array('borrowed' => $borrowed, 'overdue' => $overdue, 'due_today' => $dueToday);
}

function user_active_loans($state, $memberSrl, $page = 1, $perPage = 100)
{
    return loan_query($state, array('member_srl' => (int)$memberSrl, 'status' => 'borrowed'), $page, $perPage);
}

function user_all_loans($state, $memberSrl, $page = 1, $perPage = 50)
{
    return loan_query($state, array('member_srl' => (int)$memberSrl), $page, $perPage);
}

function item_all_loans($state, $itemId, $page = 1, $perPage = 50)
{
    return loan_query($state, array('item_id' => (int)$itemId), $page, $perPage);
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
            // 묶음 식별자는 related_loan_id(= 그 묶음의 첫 loan_id). 예전에는 초 단위
            // borrowed_at을 키에 넣어서, 대여 처리가 초 경계를 넘으면 한 묶음이
            // "니퍼 (7개)" + "니퍼 (3개)"로 갈라져 보였다.
            $key = 'grp:' . loan_group_key($loan);
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
