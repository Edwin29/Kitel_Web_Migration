<?php
function active_items($state)
{
    $items = array_values(array_filter($state['items'], function ($item) {
        return (int)$item['is_active'] === 1;
    }));
    usort($items, function ($a, $b) use ($state) {
        $categoryA = item_category($state, $a);
        $categoryB = item_category($state, $b);
        $nameA = $categoryA ? $categoryA['name'] : $a['label'];
        $nameB = $categoryB ? $categoryB['name'] : $b['label'];
        $nameOrder = strnatcasecmp($nameA, $nameB);
        if ($nameOrder !== 0) {
            return $nameOrder;
        }
        $numberOrder = item_display_no($a) <=> item_display_no($b);
        if ($numberOrder !== 0) {
            return $numberOrder;
        }
        return (int)$a['item_id'] <=> (int)$b['item_id'];
    });
    return $items;
}

function item_category($state, $item)
{
    return find_category($state, $item['category_id']);
}

// 정렬 기준은 active_items()와 같다. 다만 폐기(is_active=0) 항목까지 포함한다.
function sort_items($state, array $items)
{
    usort($items, function ($a, $b) use ($state) {
        $categoryA = item_category($state, $a);
        $categoryB = item_category($state, $b);
        $nameA = $categoryA ? $categoryA['name'] : $a['label'];
        $nameB = $categoryB ? $categoryB['name'] : $b['label'];
        $nameOrder = strnatcasecmp($nameA, $nameB);
        if ($nameOrder !== 0) {
            return $nameOrder;
        }
        $numberOrder = item_display_no($a) <=> item_display_no($b);
        if ($numberOrder !== 0) {
            return $numberOrder;
        }
        return (int)$a['item_id'] <=> (int)$b['item_id'];
    });
    return $items;
}

function item_filters_from_request()
{
    return array(
        'q' => isset($_GET['q']) ? trim($_GET['q']) : '',
        'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
        'category_id' => isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0,
    );
}

function item_filters_defaults(array $filters = array())
{
    return $filters + array('q' => '', 'status' => '', 'category_id' => 0);
}

// 기자재 목록 필터. 상태 필터로 'retired'를 고르면 폐기 항목을 보여준다.
//
// 예전에는 목록을 active_items()(is_active=1)로만 만들면서 상태 드롭다운에는
// 'retired'가 선택지로 들어 있었다. 폐기 항목은 정의상 is_active=0이라
// 그 옵션은 항상 빈 결과만 냈다.
function item_rows_filtered($state, array $filters, $includeBulkCategories = true)
{
    $filters = item_filters_defaults($filters);
    $wantsRetired = ($filters['status'] === 'retired');

    $rows = array();
    foreach ($state['items'] as $item) {
        $isActive = ((int)$item['is_active'] === 1);
        if ($wantsRetired) {
            if ($isActive) {
                continue;
            }
        } elseif (!$isActive) {
            continue;
        }

        $category = item_category($state, $item);
        if (!$includeBulkCategories && $category && category_tracking_mode($category) === 'bulk') {
            continue;
        }
        if ($filters['category_id'] > 0 && (int)$item['category_id'] !== $filters['category_id']) {
            continue;
        }
        if ($filters['status'] !== '' && !$wantsRetired && $item['status'] !== $filters['status']) {
            continue;
        }
        if ($filters['q'] !== '') {
            $haystack = $item['label'] . ' ' . $item['location'] . ' ' . $item['public_code'];
            if (stripos($haystack, $filters['q']) === false) {
                continue;
            }
        }
        $rows[] = $item;
    }

    return sort_items($state, $rows);
}

function item_display_no($item)
{
    return (isset($item['display_no']) && (int)$item['display_no'] > 0)
        ? (int)$item['display_no']
        : (int)$item['serial_no'];
}

function item_label_text($categoryName, $displayNo)
{
    return $categoryName . '-' . (int)$displayNo;
}

function category_next_serial_from_items($state, $categoryId)
{
    $used = array();
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] === (int)$categoryId && (int)$item['is_active'] === 1) {
            $used[] = item_display_no($item);
        }
    }
    $serials = next_available_serials($used, 1);
    return $serials[0];
}

function next_internal_serial_from_items($state, $categoryId)
{
    $maxSerial = 0;
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] === (int)$categoryId) {
            $maxSerial = max($maxSerial, (int)$item['serial_no']);
        }
    }
    return $maxSerial + 1;
}

function next_available_serials(array $usedSerials, $quantity)
{
    $used = array();
    foreach ($usedSerials as $serial) {
        $serial = (int)$serial;
        if ($serial > 0) {
            $used[$serial] = true;
        }
    }

    $serials = array();
    $candidate = 1;
    while (count($serials) < (int)$quantity) {
        if (!isset($used[$candidate])) {
            $serials[] = $candidate;
            $used[$candidate] = true;
        }
        $candidate++;
    }
    return $serials;
}

function sync_category_next_serial(&$state, $categoryId)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('
            SELECT COALESCE(display_no, serial_no)
            FROM kitel_rental_items
            WHERE category_id = ? AND is_active = 1
            ORDER BY COALESCE(display_no, serial_no)
        ');
        $stmt->execute(array($categoryId));
        $nextSerials = next_available_serials($stmt->fetchAll(PDO::FETCH_COLUMN), 1);
        $nextSerial = $nextSerials[0];
        $update = $pdo->prepare('UPDATE kitel_rental_categories SET next_serial = ?, updated_at = ? WHERE category_id = ?');
        $update->execute(array($nextSerial, db_now(), $categoryId));
        return $nextSerial;
    }

    $nextSerial = category_next_serial_from_items($state, $categoryId);
    foreach ($state['categories'] as &$category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            $category['next_serial'] = $nextSerial;
            $category['updated_at'] = now_text();
            break;
        }
    }
    unset($category);
    return $nextSerial;
}

function add_items_to_category(&$state, $categoryId, $quantity, $location, $note, $actor)
{
    if (config('mode') !== 'local') {
        return db_transaction(function ($pdo) use (&$state, $categoryId, $quantity, $location, $note, $actor) {
            $created = array();
            $stmt = $pdo->prepare('SELECT * FROM kitel_rental_categories WHERE category_id = ? AND is_active = 1 FOR UPDATE');
            $stmt->execute(array($categoryId));
            $category = $stmt->fetch();
            if (!$category) {
                throw new RuntimeException('카테고리를 찾을 수 없습니다.');
            }
            $internalSerialStmt = $pdo->prepare('SELECT COALESCE(MAX(serial_no), 0) + 1 FROM kitel_rental_items WHERE category_id = ?');
            $internalSerialStmt->execute(array($categoryId));
            $internalSerial = max(1, (int)$internalSerialStmt->fetchColumn());
            $displayStmt = $pdo->prepare('
                SELECT COALESCE(display_no, serial_no)
                FROM kitel_rental_items
                WHERE category_id = ? AND is_active = 1
                ORDER BY COALESCE(display_no, serial_no)
            ');
            $displayStmt->execute(array($categoryId));
            $displayNos = next_available_serials($displayStmt->fetchAll(PDO::FETCH_COLUMN), $quantity);
            foreach ($displayNos as $displayNo) {
                $currentSerial = $internalSerial++;
                $label = item_label_text($category['name'], $displayNo);
                $publicCode = unique_public_code_db($pdo);
                $now = db_now();
                $insert = $pdo->prepare('
                    INSERT INTO kitel_rental_items
                        (category_id, serial_no, display_no, label, public_code, status, location, condition_note, admin_memo, is_active, created_by_member_srl, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, "available", ?, ?, "", 1, ?, ?, ?)
                ');
                $insert->execute(array($categoryId, $currentSerial, $displayNo, $label, $publicCode, trim($location), trim($note), $actor['member_srl'], $now, $now));
                $itemId = (int)$pdo->lastInsertId();
                $item = array(
                    'item_id' => $itemId,
                    'category_id' => (int)$categoryId,
                    'serial_no' => $currentSerial,
                    'display_no' => $displayNo,
                    'label' => $label,
                    'public_code' => $publicCode,
                    'status' => 'available',
                    'location' => trim($location),
                    'condition_note' => trim($note),
                    'admin_memo' => '',
                    'is_active' => 1,
                    'created_by_member_srl' => $actor['member_srl'],
                    'created_at' => $now,
                    'updated_at' => $now,
                );
                $created[] = $item;
                add_log($state, 'item.create', $itemId, null, null, 'available', $label, $actor);
            }
            $displayStmt->execute(array($categoryId));
            $nextSerials = next_available_serials($displayStmt->fetchAll(PDO::FETCH_COLUMN), 1);
            $next = $nextSerials[0];
            $update = $pdo->prepare('UPDATE kitel_rental_categories SET next_serial = ?, updated_at = ? WHERE category_id = ?');
            $update->execute(array($next, db_now(), $categoryId));
            return $created;
        });
    }
    $created = array();
    foreach ($state['categories'] as &$category) {
        if ((int)$category['category_id'] !== (int)$categoryId) {
            continue;
        }
        $usedDisplayNos = array();
        foreach ($state['items'] as $existingItem) {
            if ((int)$existingItem['category_id'] === (int)$categoryId && (int)$existingItem['is_active'] === 1) {
                $usedDisplayNos[] = item_display_no($existingItem);
            }
        }
        $displayNos = next_available_serials($usedDisplayNos, $quantity);
        $internalSerial = next_internal_serial_from_items($state, $categoryId);
        foreach ($displayNos as $displayNo) {
            $serial = $internalSerial++;
            $item = array(
                'item_id' => next_id($state, 'item_id'),
                'category_id' => (int)$categoryId,
                'serial_no' => $serial,
                'display_no' => $displayNo,
                'label' => item_label_text($category['name'], $displayNo),
                'public_code' => unique_public_code($state),
                'status' => 'available',
                'location' => trim($location),
                'condition_note' => trim($note),
                'admin_memo' => '',
                'is_active' => 1,
                'created_by_member_srl' => $actor['member_srl'],
                'created_at' => now_text(),
                'updated_at' => now_text(),
            );
            $state['items'][] = $item;
            $created[] = $item;
            add_log($state, 'item.create', $item['item_id'], null, null, 'available', $item['label'], $actor);
        }
        $usedDisplayNos = array();
        foreach ($state['items'] as $existingItem) {
            if ((int)$existingItem['category_id'] === (int)$categoryId && (int)$existingItem['is_active'] === 1) {
                $usedDisplayNos[] = item_display_no($existingItem);
            }
        }
        $nextSerials = next_available_serials($usedDisplayNos, 1);
        $category['next_serial'] = $nextSerials[0];
        $category['updated_at'] = now_text();
        break;
    }
    unset($category);
    return $created;
}

function unique_public_code($state)
{
    do {
        $code = generate_public_code();
        $exists = false;
        foreach ($state['items'] as $item) {
            if ($item['public_code'] === $code) {
                $exists = true;
                break;
            }
        }
    } while ($exists);
    return $code;
}

function unique_public_code_db($pdo)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM kitel_rental_items WHERE public_code = ?');
    do {
        $code = generate_public_code();
        $stmt->execute(array($code));
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists);
    return $code;
}

function update_item_status(&$state, $itemId, $status, $memo, $actor)
{
    $memo = trim($memo);
    if ($memo === '') {
        throw new RuntimeException('상태 변경 사유를 입력해 주세요.');
    }
    if (!in_array($status, array('available', 'unavailable', 'broken', 'lost'), true)) {
        throw new RuntimeException('변경할 수 없는 상태입니다.');
    }
    if (config('mode') !== 'local') {
        db_transaction(function ($pdo) use (&$state, $itemId, $status, $memo, $actor) {
            $stmt = $pdo->prepare('SELECT category_id, status FROM kitel_rental_items WHERE item_id = ? FOR UPDATE');
            $stmt->execute(array($itemId));
            $row = $stmt->fetch();
            if (!$row) {
                throw new RuntimeException('기자재를 찾을 수 없습니다.');
            }
            $before = $row['status'];
            if ($before === 'borrowed') {
                throw new RuntimeException('대여 중인 기자재는 직접 상태를 바꿀 수 없습니다. 먼저 반납 처리해 주세요.');
            }
            $update = $pdo->prepare('UPDATE kitel_rental_items SET status = ?, updated_at = ? WHERE item_id = ?');
            $update->execute(array($status, db_now(), $itemId));
            add_log($state, 'item.status', $itemId, null, $before, $status, $memo, $actor);
        });
        return;
    }
    foreach ($state['items'] as &$item) {
        if ((int)$item['item_id'] === (int)$itemId) {
            $before = $item['status'];
            if ($before === 'borrowed') {
                throw new RuntimeException('대여 중인 기자재는 직접 상태를 바꿀 수 없습니다. 먼저 반납 처리해 주세요.');
            }
            $item['status'] = $status;
            $item['updated_at'] = now_text();
            add_log($state, 'item.status', $itemId, null, $before, $status, $memo, $actor);
            break;
        }
    }
    unset($item);
}

function update_item_details(&$state, $itemId, $location, $conditionNote, $adminMemo, $actor)
{
    if (config('mode') !== 'local') {
        db_transaction(function ($pdo) use (&$state, $itemId, $location, $conditionNote, $adminMemo, $actor) {
            $stmt = $pdo->prepare('SELECT location, condition_note, admin_memo FROM kitel_rental_items WHERE item_id = ? FOR UPDATE');
            $stmt->execute(array($itemId));
            $before = $stmt->fetch();
            if (!$before) {
                throw new RuntimeException('기자재를 찾을 수 없습니다.');
            }
            $update = $pdo->prepare('
                UPDATE kitel_rental_items
                SET location = ?, condition_note = ?, admin_memo = ?, updated_at = ?
                WHERE item_id = ?
            ');
            $update->execute(array(trim($location), trim($conditionNote), trim($adminMemo), db_now(), $itemId));
            add_log($state, 'item.update', $itemId, null, null, null, '기자재 정보 수정', $actor);
        });
        return;
    }
    foreach ($state['items'] as &$item) {
        if ((int)$item['item_id'] === (int)$itemId) {
            $item['location'] = trim($location);
            $item['condition_note'] = trim($conditionNote);
            $item['admin_memo'] = trim($adminMemo);
            $item['updated_at'] = now_text();
            add_log($state, 'item.update', $itemId, null, null, null, '기자재 정보 수정', $actor);
            break;
        }
    }
    unset($item);
}

function find_category_by_name($state, $name)
{
    foreach ($state['categories'] as $category) {
        if ((int)$category['is_active'] === 1 && trim($category['name']) === trim($name)) {
            return $category;
        }
    }
    return null;
}

// 개수 관리(bulk) 카테고리용 헬퍼들.
// "라벨이 없는 물건"은 어떤 실물이 어떤 로그에 남았는지 사람이 구분할 수 없으므로,
// 화면에는 항상 개수만 보여주고 내부적으로만 개별 item 행을 골라 기존 함수(update_item_status,
// retire_item, create_loan)를 그대로 재사용한다.

function category_item_counts($state, $categoryId)
{
    $total = 0;
    $available = 0;
    $borrowed = 0;
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] !== (int)$categoryId || (int)$item['is_active'] !== 1) {
            continue;
        }
        $total++;
        if ($item['status'] === 'available') {
            $available++;
        } elseif ($item['status'] === 'borrowed') {
            $borrowed++;
        }
    }
    return array('total' => $total, 'available' => $available, 'borrowed' => $borrowed);
}

function find_available_item_in_category($state, $categoryId)
{
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] === (int)$categoryId && $item['status'] === 'available' && (int)$item['is_active'] === 1) {
            return $item;
        }
    }
    return null;
}

// 개수 관리 카테고리에서 상태를 바꿀 후보를 고른다.
// $fromStatus로 "지금 어떤 상태인 것을 고를지"를 지정한다 — 고장 3개를 다시
// 대여 가능으로 되돌리려면 broken 상태인 것을 골라야 하기 때문이다.
// 예전에는 available 만 고를 수 있어서, 화면의 상태 드롭다운에도 available이
// 빠져 있었고 "고쳐서 복구"가 아예 불가능했다.
// $pdo가 넘어오면 이미 열린 트랜잭션 안에서 FOR UPDATE로 잠그고 고른다.
function pick_items_in_category_by_status($state, $categoryId, $fromStatus, $limit, $pdo = null)
{
    if (!in_array($fromStatus, array('available', 'unavailable', 'broken', 'lost'), true)) {
        throw new RuntimeException('선택할 수 없는 상태입니다.');
    }
    if (config('mode') !== 'local') {
        $pdo = $pdo ?: db_connect();
        $stmt = $pdo->prepare('
            SELECT item_id FROM kitel_rental_items
            WHERE category_id = ? AND status = ? AND is_active = 1
            ORDER BY item_id
            LIMIT ' . max(0, (int)$limit) . ($pdo->inTransaction() ? ' FOR UPDATE' : '')
        );
        $stmt->execute(array((int)$categoryId, $fromStatus));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    $picked = array();
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] === (int)$categoryId && $item['status'] === $fromStatus && (int)$item['is_active'] === 1) {
            $picked[] = (int)$item['item_id'];
            if (count($picked) >= $limit) {
                break;
            }
        }
    }
    return $picked;
}

function pick_available_items_in_category($state, $categoryId, $limit, $pdo = null)
{
    return pick_items_in_category_by_status($state, $categoryId, 'available', $limit, $pdo);
}

// 개수 관리 카테고리의 상태별 개수. 화면에서 "어떤 상태의 것이 몇 개 있는지"를
// 보여주고, 수량 입력의 상한으로도 쓴다.
function category_status_counts($state, $categoryId)
{
    $counts = array('available' => 0, 'unavailable' => 0, 'broken' => 0, 'lost' => 0, 'borrowed' => 0, 'total' => 0);
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] !== (int)$categoryId || (int)$item['is_active'] !== 1) {
            continue;
        }
        $counts['total']++;
        if (isset($counts[$item['status']])) {
            $counts[$item['status']]++;
        }
    }
    return $counts;
}

// 카테고리 단위 일괄 처리. 대상 선정과 처리를 같은 트랜잭션 안에서 한다
// (예전에는 요청 시작 시점의 $state에서 골라서 동시 작업 시 경합했다).
function bulk_apply_in_category(&$state, $categoryId, $quantity, callable $apply, $fromStatus = 'available')
{
    $quantity = max(0, (int)$quantity);

    $run = function ($pdo) use (&$state, $categoryId, $quantity, $apply, $fromStatus) {
        $ids = pick_items_in_category_by_status($state, $categoryId, $fromStatus, $quantity, $pdo);
        $done = 0;
        $skipped = 0;
        foreach ($ids as $itemId) {
            try {
                $apply($state, $itemId);
                $done++;
            } catch (RuntimeException $e) {
                $skipped++;
            }
        }
        return array($done, $skipped);
    };

    if (config('mode') !== 'local') {
        return db_transaction($run);
    }
    return $run(null);
}

// 카테고리 단위로 "$fromStatus 인 것 N개를 $status 로 변경"
function bulk_adjust_category_status(&$state, $categoryId, $quantity, $status, $memo, $actor, $fromStatus = 'available')
{
    if ($fromStatus === $status) {
        throw new RuntimeException('바꾸기 전과 후의 상태가 같습니다.');
    }
    list($updated, $skipped) = bulk_apply_in_category($state, $categoryId, $quantity, function (&$state, $itemId) use ($status, $memo, $actor) {
        update_item_status($state, $itemId, $status, $memo, $actor);
    }, $fromStatus);
    return array('updated' => $updated, 'skipped' => $skipped, 'requested' => (int)$quantity);
}

// 카테고리 단위로 "N개 폐기"
function bulk_retire_in_category(&$state, $categoryId, $quantity, $memo, $actor)
{
    list($retired, $skipped) = bulk_apply_in_category($state, $categoryId, $quantity, function (&$state, $itemId) use ($memo, $actor) {
        retire_item($state, $itemId, $memo, $actor);
    });
    return array('retired' => $retired, 'skipped' => $skipped, 'requested' => (int)$quantity);
}

function retire_item(&$state, $itemId, $memo, $actor)
{
    $memo = trim($memo);
    if ($memo === '') {
        throw new RuntimeException('폐기 처리 사유를 입력해 주세요.');
    }
    // 폐기는 항상 소프트 삭제(status=retired, is_active=0)로 처리한다.
    // 예전에는 대여 이력이 없으면 행을 통째로 DELETE 했는데, kitel_rental_logs에는
    // 그 기자재의 item.create / item.retire 로그가 FK 없이 그대로 남아서
    // 로그 화면의 "item 47"을 아무 데서도 조회할 수 없게 됐다.
    if (config('mode') !== 'local') {
        db_transaction(function ($pdo) use (&$state, $itemId, $memo, $actor) {
            $stmt = $pdo->prepare('SELECT category_id, status FROM kitel_rental_items WHERE item_id = ? FOR UPDATE');
            $stmt->execute(array($itemId));
            $row = $stmt->fetch();
            if (!$row) {
                throw new RuntimeException('기자재를 찾을 수 없습니다.');
            }
            $before = $row['status'];
            if ($before === 'borrowed') {
                throw new RuntimeException('대여 중인 기자재는 폐기 처리할 수 없습니다.');
            }
            $update = $pdo->prepare('UPDATE kitel_rental_items SET status = "retired", is_active = 0, updated_at = ? WHERE item_id = ?');
            $update->execute(array(db_now(), $itemId));
            add_log($state, 'item.retire', $itemId, null, $before, 'retired', $memo, $actor);
            sync_category_next_serial($state, (int)$row['category_id']);
        });
        return;
    }
    foreach ($state['items'] as &$item) {
        if ((int)$item['item_id'] === (int)$itemId) {
            if ($item['status'] === 'borrowed') {
                throw new RuntimeException('대여 중인 기자재는 폐기 처리할 수 없습니다.');
            }
            $before = $item['status'];
            $categoryId = (int)$item['category_id'];
            $item['status'] = 'retired';
            $item['is_active'] = 0;
            $item['updated_at'] = now_text();
            add_log($state, 'item.retire', $itemId, null, $before, 'retired', $memo, $actor);
            sync_category_next_serial($state, $categoryId);
            break;
        }
    }
    unset($item);
}

// 체크박스로 선택한 여러 기자재를 한 번에 처리하는 공통 루틴.
//
// 운영 모드에서는 전체가 하나의 트랜잭션이다. 예전에는 건별로 트랜잭션이 따로
// 열려서 중간에 실패하면 절반만 반영된 상태로 남았다. 또 "대여 중이면 건너뛴다"를
// 요청 시작 시점의 $state로 판단했는데, 이제는 각 단일 작업 함수가 DB를 FOR UPDATE로
// 다시 읽고 던지는 예외를 받아서 센다.
function bulk_apply_to_items(&$state, array $itemIds, callable $apply)
{
    $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

    $run = function () use (&$state, $itemIds, $apply) {
        $done = 0;
        $skipped = 0;
        foreach ($itemIds as $itemId) {
            try {
                $apply($state, $itemId);
                $done++;
            } catch (RuntimeException $e) {
                // 대여 중이거나 이미 없는 항목은 건너뛴다 (전체를 되돌리지는 않는다).
                $skipped++;
            }
        }
        return array($done, $skipped);
    };

    if (config('mode') !== 'local') {
        return db_transaction(function () use ($run) {
            return $run();
        });
    }
    return $run();
}

function bulk_update_item_status(&$state, array $itemIds, $status, $memo, $actor)
{
    list($updated, $skipped) = bulk_apply_to_items($state, $itemIds, function (&$state, $itemId) use ($status, $memo, $actor) {
        update_item_status($state, $itemId, $status, $memo, $actor);
    });
    return array('updated' => $updated, 'skipped' => $skipped);
}

// 체크박스로 선택한 여러 기자재를 한 번에 폐기 처리한다. 대여 중인 항목은 건너뛴다.
function bulk_retire_items(&$state, array $itemIds, $memo, $actor)
{
    list($retired, $skipped) = bulk_apply_to_items($state, $itemIds, function (&$state, $itemId) use ($memo, $actor) {
        retire_item($state, $itemId, $memo, $actor);
    });
    return array('retired' => $retired, 'skipped' => $skipped);
}
