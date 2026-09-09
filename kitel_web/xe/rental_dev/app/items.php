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

function item_has_loan_history($state, $itemId)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM kitel_rental_loans WHERE item_id = ?');
        $stmt->execute(array($itemId));
        return (int)$stmt->fetchColumn() > 0;
    }

    foreach ($state['loans'] as $loan) {
        if ((int)$loan['item_id'] === (int)$itemId) {
            return true;
        }
    }
    return false;
}

function add_items_to_category(&$state, $categoryId, $quantity, $location, $note, $actor)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $created = array();
        $pdo->beginTransaction();
        try {
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
            $pdo->commit();
            return $created;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
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
            $pdo->commit();
            return;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
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
            $pdo->commit();
            return;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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

function pick_available_items_in_category($state, $categoryId, $limit)
{
    $picked = array();
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] === (int)$categoryId && $item['status'] === 'available' && (int)$item['is_active'] === 1) {
            $picked[] = (int)$item['item_id'];
            if (count($picked) >= $limit) {
                break;
            }
        }
    }
    return $picked;
}

// 카테고리 단위로 "N개 상태 변경" — 실제로는 available 상태인 아이템을 N개 골라
// 기존 bulk_update_item_status()에 그대로 넘긴다.
function bulk_adjust_category_status(&$state, $categoryId, $quantity, $status, $memo, $actor)
{
    $ids = pick_available_items_in_category($state, $categoryId, (int)$quantity);
    $result = bulk_update_item_status($state, $ids, $status, $memo, $actor);
    $result['requested'] = (int)$quantity;
    return $result;
}

// 카테고리 단위로 "N개 폐기"
function bulk_retire_in_category(&$state, $categoryId, $quantity, $memo, $actor)
{
    $ids = pick_available_items_in_category($state, $categoryId, (int)$quantity);
    $result = bulk_retire_items($state, $ids, $memo, $actor);
    $result['requested'] = (int)$quantity;
    return $result;
}

function import_items_from_csv(&$state, $csvText, $actor)
{
    $lines = preg_split('/\r\n|\r|\n/', trim($csvText));
    $created = 0;
    $createdIds = array();
    $errors = array();
    foreach ($lines as $index => $line) {
        if (trim($line) === '') {
            continue;
        }
        $row = str_getcsv($line);
        if ($index === 0 && isset($row[0]) && trim($row[0]) === 'category') {
            continue;
        }
        $categoryName = isset($row[0]) ? trim($row[0]) : '';
        $categoryName = preg_replace('/^\xEF\xBB\xBF/', '', $categoryName);
        $quantity = isset($row[1]) ? max(1, (int)$row[1]) : 1;
        $location = isset($row[2]) ? $row[2] : '';
        $note = isset($row[3]) ? $row[3] : '';
        if ($categoryName === '') {
            $errors[] = ($index + 1) . '행: 카테고리명이 비어 있습니다.';
            continue;
        }
        $category = find_category_by_name($state, $categoryName);
        if (!$category) {
            $category = create_category($state, $categoryName, 'CSV 가져오기', $actor);
            if (config('mode') !== 'local') {
                $state = rental_load();
            }
        }
        $items = add_items_to_category($state, $category['category_id'], $quantity, $location, $note, $actor);
        $created += count($items);
        foreach ($items as $item) {
            $createdIds[] = (int)$item['item_id'];
        }
        if (config('mode') !== 'local') {
            $state = rental_load();
        }
    }
    return array('created' => $created, 'created_ids' => $createdIds, 'errors' => $errors);
}

function retire_item(&$state, $itemId, $memo, $actor)
{
    $memo = trim($memo);
    if ($memo === '') {
        throw new RuntimeException('폐기 처리 사유를 입력해 주세요.');
    }
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
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
            if (item_has_loan_history($state, $itemId)) {
                $update = $pdo->prepare('UPDATE kitel_rental_items SET status = "retired", is_active = 0, updated_at = ? WHERE item_id = ?');
                $update->execute(array(db_now(), $itemId));
            } else {
                $delete = $pdo->prepare('DELETE FROM kitel_rental_items WHERE item_id = ?');
                $delete->execute(array($itemId));
            }
            add_log($state, 'item.retire', $itemId, null, $before, 'retired', $memo, $actor);
            sync_category_next_serial($state, (int)$row['category_id']);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        return;
    }
    foreach ($state['items'] as $index => &$item) {
        if ((int)$item['item_id'] === (int)$itemId) {
            if ($item['status'] === 'borrowed') {
                throw new RuntimeException('대여 중인 기자재는 폐기 처리할 수 없습니다.');
            }
            $before = $item['status'];
            $categoryId = (int)$item['category_id'];
            if (item_has_loan_history($state, $itemId)) {
                $item['status'] = 'retired';
                $item['is_active'] = 0;
                $item['updated_at'] = now_text();
            } else {
                unset($state['items'][$index]);
                $state['items'] = array_values($state['items']);
            }
            add_log($state, 'item.retire', $itemId, null, $before, 'retired', $memo, $actor);
            sync_category_next_serial($state, $categoryId);
            break;
        }
    }
    unset($item);
}

// 체크박스로 선택한 여러 기자재의 상태를 한 번에 바꾼다.
// 대여 중인 기자재는 개별 화면과 동일하게 건너뛰고 skipped 카운트로 알려준다.
function bulk_update_item_status(&$state, array $itemIds, $status, $memo, $actor)
{
    $updated = 0;
    $skipped = 0;
    foreach (array_unique(array_map('intval', $itemIds)) as $itemId) {
        $item = find_item($state, $itemId);
        if (!$item || $item['status'] === 'borrowed') {
            $skipped++;
            continue;
        }
        update_item_status($state, $itemId, $status, $memo, $actor);
        $updated++;
    }
    return array('updated' => $updated, 'skipped' => $skipped);
}

// 체크박스로 선택한 여러 기자재를 한 번에 폐기 처리한다. 대여 중인 항목은 건너뛴다.
function bulk_retire_items(&$state, array $itemIds, $memo, $actor)
{
    $retired = 0;
    $skipped = 0;
    foreach (array_unique(array_map('intval', $itemIds)) as $itemId) {
        $item = find_item($state, $itemId);
        if (!$item || $item['status'] === 'borrowed') {
            $skipped++;
            continue;
        }
        retire_item($state, $itemId, $memo, $actor);
        $retired++;
    }
    return array('retired' => $retired, 'skipped' => $skipped);
}
