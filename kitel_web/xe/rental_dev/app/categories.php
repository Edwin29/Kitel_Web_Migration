<?php
function active_categories($state)
{
    return array_values(array_filter($state['categories'], function ($category) {
        return (int)$category['is_active'] === 1;
    }));
}

// 마이그레이션 전 데이터(local_data.json 등)에 tracking_mode/max_per_user 필드가
// 아직 없을 수 있으므로, 항상 이 두 헬퍼를 통해서만 읽는다.
function category_tracking_mode($category)
{
    return (isset($category['tracking_mode']) && $category['tracking_mode'] === 'bulk') ? 'bulk' : 'unique';
}

function category_max_per_user($category)
{
    return (isset($category['max_per_user']) && $category['max_per_user'] !== null && $category['max_per_user'] !== '')
        ? (int)$category['max_per_user']
        : 0;
}

// 카테고리에 따로 지정된 대여 기간(일). 지정 안 했으면 0.
// 0이면 config('default_due_days')를 쓴다 — effective_due_days() 참고.
function category_due_days($category)
{
    return (isset($category['due_days']) && $category['due_days'] !== null && $category['due_days'] !== '')
        ? (int)$category['due_days']
        : 0;
}

// 이 카테고리에 실제로 적용되는 대여 기간(일).
function effective_due_days($state, $categoryId)
{
    $category = find_category($state, $categoryId);
    $days = $category ? category_due_days($category) : 0;
    return $days > 0 ? $days : (int)config('default_due_days');
}

// 이 카테고리로 지금 대여하면 반납 예정일이 언제가 되는지 (Y-m-d).
function category_due_date($state, $categoryId)
{
    return date('Y-m-d', strtotime('+' . effective_due_days($state, $categoryId) . ' days'));
}

// 기자재 하나의 반납 예정일. 그 기자재가 속한 카테고리 기준.
function item_due_date($state, $item)
{
    return $item ? category_due_date($state, $item['category_id']) : default_due_date();
}

function create_category(&$state, $name, $description, $actor)
{
    $name = trim($name);
    $description = trim($description);
    if ($name === '') {
        throw new RuntimeException('카테고리명을 입력해 주세요.');
    }

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $now = db_now();
        $stmt = $pdo->prepare('
            INSERT INTO kitel_rental_categories
                (name, slug, next_serial, description, tracking_mode, max_per_user, is_active, created_by_member_srl, created_at, updated_at)
            VALUES (?, ?, 1, ?, "unique", NULL, 1, ?, ?, ?)
        ');
        $stmt->execute(array($name, slugify($name), $description, $actor['member_srl'], $now, $now));
        $categoryId = (int)$pdo->lastInsertId();
        $category = array(
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => slugify($name),
            'next_serial' => 1,
            'description' => $description,
            'tracking_mode' => 'unique',
            'max_per_user' => null,
            'due_days' => null,
            'is_active' => 1,
            'created_by_member_srl' => $actor['member_srl'],
            'created_at' => $now,
            'updated_at' => $now,
        );
        add_log($state, 'category.create', null, null, null, null, $category['name'], $actor);
        return $category;
    }
    $now = now_text();
    $category = array(
        'category_id' => next_id($state, 'category_id'),
        'name' => $name,
        'slug' => slugify($name),
        'next_serial' => 1,
        'description' => $description,
        'tracking_mode' => 'unique',
        'max_per_user' => null,
        'due_days' => null,
        'is_active' => 1,
        'created_by_member_srl' => $actor['member_srl'],
        'created_at' => $now,
        'updated_at' => $now,
    );
    $state['categories'][] = $category;
    add_log($state, 'category.create', null, null, null, null, $category['name'], $actor);
    return $category;
}

function delete_category(&$state, $categoryId, $actor)
{
    // 삭제 규칙:
    //   - 대여 중인 기자재가 있으면 거부 (기존과 동일)
    //   - 기자재가 하나도 없으면 카테고리 행을 실제로 지운다
    //   - 기자재가 있으면 전부 폐기 처리하고 카테고리는 비활성화만 한다 (행은 남긴다)
    //
    // 예전에는 SET FOREIGN_KEY_CHECKS=0 으로 FK를 꺼놓고 카테고리 행을 지웠다.
    // 그러면 남아 있는 retired 기자재의 category_id가 존재하지 않는 행을 가리켜서
    // 이력 화면의 카테고리가 영구히 '-'가 되고, 같은 이름으로 다시 만들어도
    // 새 id가 발급되므로 재연결되지 않았다.
    if (config('mode') !== 'local') {
        db_transaction(function ($pdo) use (&$state, $categoryId, $actor) {
            $stmt = $pdo->prepare('SELECT name FROM kitel_rental_categories WHERE category_id = ? FOR UPDATE');
            $stmt->execute(array($categoryId));
            $category = $stmt->fetch();
            if (!$category) {
                throw new RuntimeException('카테고리를 찾을 수 없습니다.');
            }

            $borrowedCount = $pdo->prepare('
                SELECT COUNT(*)
                FROM kitel_rental_items i
                LEFT JOIN kitel_rental_loans l ON l.item_id = i.item_id
                WHERE i.category_id = ?
                  AND (i.status = "borrowed" OR l.status = "borrowed")
            ');
            $borrowedCount->execute(array($categoryId));
            if ((int)$borrowedCount->fetchColumn() > 0) {
                throw new RuntimeException('대여 중인 기자재가 있는 카테고리는 삭제할 수 없습니다.');
            }

            $deleteBundleLinks = $pdo->prepare('DELETE FROM kitel_rental_bundle_categories WHERE category_id = ?');
            $deleteBundleLinks->execute(array($categoryId));

            $itemCount = $pdo->prepare('SELECT COUNT(*) FROM kitel_rental_items WHERE category_id = ?');
            $itemCount->execute(array($categoryId));

            if ((int)$itemCount->fetchColumn() === 0) {
                $deleteCategory = $pdo->prepare('DELETE FROM kitel_rental_categories WHERE category_id = ?');
                $deleteCategory->execute(array($categoryId));
                add_log($state, 'category.delete', null, null, null, null, $category['name'], $actor);
                return;
            }

            $retireItems = $pdo->prepare('
                UPDATE kitel_rental_items
                SET status = "retired", is_active = 0, updated_at = ?
                WHERE category_id = ?
            ');
            $retireItems->execute(array(db_now(), $categoryId));

            $deactivate = $pdo->prepare('UPDATE kitel_rental_categories SET is_active = 0, updated_at = ? WHERE category_id = ?');
            $deactivate->execute(array(db_now(), $categoryId));
            add_log($state, 'category.delete', null, null, null, null, $category['name'] . ' (기자재 이력이 있어 비활성 처리)', $actor);
        });
        return;
    }

    $itemIds = array();
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] === (int)$categoryId) {
            if ($item['status'] === 'borrowed') {
                throw new RuntimeException('대여 중인 기자재가 있는 카테고리는 삭제할 수 없습니다.');
            }
            $itemIds[] = (int)$item['item_id'];
        }
    }
    foreach ($state['loans'] as $loan) {
        if (in_array((int)$loan['item_id'], $itemIds, true) && $loan['status'] === 'borrowed') {
            throw new RuntimeException('대여 중인 기자재가 있는 카테고리는 삭제할 수 없습니다.');
        }
    }

    detach_category_bundle_links($state, $categoryId);

    $categoryName = '';
    foreach ($state['categories'] as &$category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            $categoryName = $category['name'];
            break;
        }
    }
    unset($category);

    // 기자재가 하나도 없으면 카테고리 행까지 지우고, 있으면 비활성화만 한다.
    if (!$itemIds) {
        foreach ($state['categories'] as $index => $category) {
            if ((int)$category['category_id'] === (int)$categoryId) {
                unset($state['categories'][$index]);
                $state['categories'] = array_values($state['categories']);
                break;
            }
        }
        add_log($state, 'category.delete', null, null, null, null, $categoryName, $actor);
        return;
    }

    foreach ($state['items'] as &$item) {
        if ((int)$item['category_id'] === (int)$categoryId) {
            $item['status'] = 'retired';
            $item['is_active'] = 0;
            $item['updated_at'] = now_text();
        }
    }
    unset($item);
    foreach ($state['categories'] as &$category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            $category['is_active'] = 0;
            $category['updated_at'] = now_text();
            break;
        }
    }
    unset($category);
    add_log($state, 'category.delete', null, null, null, null, $categoryName . ' (기자재 이력이 있어 비활성 처리)', $actor);
}

function update_category_name(&$state, $categoryId, $name, $actor)
{
    $category = find_category($state, $categoryId);
    if (!$category) {
        throw new RuntimeException('카테고리를 찾을 수 없습니다.');
    }
    update_category(
        $state,
        $categoryId,
        $name,
        isset($category['description']) ? $category['description'] : '',
        isset($category['next_serial']) ? (int)$category['next_serial'] : 1,
        $actor
    );
}

// 카테고리명을 바꾸면 그 카테고리에 속한 기자재의 label("니퍼-2")도 함께 다시 만든다.
// label에는 카테고리명이 비정규화되어 저장되어 있어서, 예전에는 이름을 바꿔도
// 기존 기자재 목록 · 인쇄된 QR 라벨 · 대여 이력이 계속 옛 이름을 달고 있었다.
//
// $isActive 인자는 없앴다. 예전에는 인자로 받은 뒤 함수 첫머리에서 곧바로 1로
// 덮어써서 아무 효과가 없었다. 활성/비활성 전환은 delete_category()가 담당한다.
function update_category(&$state, $categoryId, $name, $description, $nextSerial, $actor)
{
    $name = trim($name);
    $description = trim($description);
    $nextSerial = max(1, (int)$nextSerial);

    if ($name === '') {
        throw new RuntimeException('카테고리명을 입력해 주세요.');
    }

    if (config('mode') !== 'local') {
        db_transaction(function ($pdo) use (&$state, $categoryId, $name, $description, $nextSerial, $actor) {
            $stmt = $pdo->prepare('SELECT * FROM kitel_rental_categories WHERE category_id = ? FOR UPDATE');
            $stmt->execute(array($categoryId));
            $before = $stmt->fetch();
            if (!$before) {
                throw new RuntimeException('카테고리를 찾을 수 없습니다.');
            }
            if ($nextSerial < (int)$before['next_serial']) {
                throw new RuntimeException('다음 라벨 번호는 현재 값보다 작게 줄일 수 없습니다.');
            }
            $update = $pdo->prepare('
                UPDATE kitel_rental_categories
                SET name = ?, slug = ?, next_serial = ?, description = ?, updated_at = ?
                WHERE category_id = ?
            ');
            $update->execute(array($name, slugify($name), $nextSerial, $description, db_now(), $categoryId));

            if ($before['name'] !== $name) {
                $relabel = $pdo->prepare('
                    UPDATE kitel_rental_items
                    SET label = CONCAT(?, "-", COALESCE(display_no, serial_no)), updated_at = ?
                    WHERE category_id = ?
                ');
                $relabel->execute(array($name, db_now(), $categoryId));
            }

            add_log($state, 'category.update', null, null, null, null, $before['name'] . ' -> ' . $name, $actor);
        });
        return;
    }

    $beforeName = null;
    foreach ($state['categories'] as &$category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            if ($nextSerial < (int)$category['next_serial']) {
                throw new RuntimeException('다음 라벨 번호는 현재 값보다 작게 줄일 수 없습니다.');
            }
            $beforeName = $category['name'];
            $category['name'] = $name;
            $category['slug'] = slugify($name);
            $category['next_serial'] = $nextSerial;
            $category['description'] = $description;
            $category['updated_at'] = now_text();
            break;
        }
    }
    unset($category);

    if ($beforeName !== null && $beforeName !== $name) {
        foreach ($state['items'] as &$item) {
            if ((int)$item['category_id'] === (int)$categoryId) {
                $item['label'] = item_label_text($name, item_display_no($item));
                $item['updated_at'] = now_text();
            }
        }
        unset($item);
    }

    if ($beforeName !== null) {
        add_log($state, 'category.update', null, null, null, null, $beforeName . ' -> ' . $name, $actor);
    }
}

// 개별 관리(unique) / 개수 관리(bulk) 전환과 1인당 대여 제한 설정.
// 자주 쓰는 기능이 아니라 관리자 화면 한 켠에 조용히 두는 것을 전제로 만든 함수.
// 화면의 "변경 안 함 / 해제 / 값 지정" 3가지 입력을 하나의 변경 지시로 해석한다.
//   ''(빈 문자열) → 이 항목은 건드리지 않음 (배열에 키를 넣지 않음)
//   '0'           → 설정 해제 (NULL)
//   양의 정수     → 그 값으로 설정
function parse_optional_number_change($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return array(false, null);      // 변경 안 함
    }
    $value = max(0, (int)$raw);
    return array(true, $value > 0 ? $value : null);
}

// 카테고리 관리 옵션(관리 방식 / 1인당 제한 / 대여 기간)을 바꾼다.
//
// $changes 에 들어 있는 키만 반영한다. 예전에는 tracking_mode 를 항상 함께
// 덮어써서, 1인당 제한만 바꾸려 해도 관리 방식이 같이 바뀌었다.
//   tracking_mode : 'unique' | 'bulk'
//   max_per_user  : int | null   (null = 제한 없음)
//   due_days      : int | null   (null = 기본 대여 기간 사용)
function update_category_options(&$state, $categoryId, array $changes, $actor)
{
    $fields = array();
    $memo = array();

    if (array_key_exists('tracking_mode', $changes)) {
        $fields['tracking_mode'] = ($changes['tracking_mode'] === 'bulk') ? 'bulk' : 'unique';
        $memo[] = '관리 방식 → ' . ($fields['tracking_mode'] === 'bulk' ? '개수 관리' : '개별 관리');
    }
    if (array_key_exists('max_per_user', $changes)) {
        $fields['max_per_user'] = $changes['max_per_user'] === null ? null : max(1, (int)$changes['max_per_user']);
        $memo[] = '1인당 제한 → ' . ($fields['max_per_user'] === null ? '없음' : $fields['max_per_user'] . '개');
    }
    if (array_key_exists('due_days', $changes)) {
        $fields['due_days'] = $changes['due_days'] === null ? null : max(1, (int)$changes['due_days']);
        $memo[] = '대여 기간 → ' . ($fields['due_days'] === null ? '기본값(' . (int)config('default_due_days') . '일)' : $fields['due_days'] . '일');
    }

    if (!$fields) {
        return false;   // 바꿀 것이 없음
    }
    $memoText = implode(', ', $memo);

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $txOwned = db_begin();
        try {
            $stmt = $pdo->prepare('SELECT name, tracking_mode FROM kitel_rental_categories WHERE category_id = ? FOR UPDATE');
            $stmt->execute(array($categoryId));
            $before = $stmt->fetch();
            if (!$before) {
                throw new RuntimeException('카테고리를 찾을 수 없습니다.');
            }
            $sets = array();
            $args = array();
            foreach ($fields as $column => $value) {
                $sets[] = $column . ' = ?';
                $args[] = $value;
            }
            $sets[] = 'updated_at = ?';
            $args[] = db_now();
            $args[] = $categoryId;
            $update = $pdo->prepare('UPDATE kitel_rental_categories SET ' . implode(', ', $sets) . ' WHERE category_id = ?');
            $update->execute($args);
            add_log(
                $state,
                'category.tracking',
                null, null,
                $before['tracking_mode'],
                isset($fields['tracking_mode']) ? $fields['tracking_mode'] : $before['tracking_mode'],
                $before['name'] . ': ' . $memoText,
                $actor
            );
            db_commit($txOwned);
            return true;
        } catch (Exception $e) {
            db_rollback($txOwned);
            throw $e;
        }
    }

    $done = false;
    foreach ($state['categories'] as &$category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            $beforeMode = category_tracking_mode($category);
            foreach ($fields as $column => $value) {
                $category[$column] = $value;
            }
            $category['updated_at'] = now_text();
            add_log(
                $state,
                'category.tracking',
                null, null,
                $beforeMode,
                isset($fields['tracking_mode']) ? $fields['tracking_mode'] : $beforeMode,
                $category['name'] . ': ' . $memoText,
                $actor
            );
            $done = true;
            break;
        }
    }
    unset($category);
    return $done;
}

// 예전 진입점 — 관리 방식과 1인당 제한을 항상 함께 지정하는 형태.
function update_category_tracking(&$state, $categoryId, $trackingMode, $maxPerUser, $actor)
{
    $maxPerUser = trim((string)$maxPerUser) === '' ? null : max(0, (int)$maxPerUser);
    return update_category_options($state, $categoryId, array(
        'tracking_mode' => $trackingMode,
        'max_per_user' => $maxPerUser === 0 ? null : $maxPerUser,
    ), $actor);
}
