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
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
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

            $deleteItems = $pdo->prepare('
                DELETE i
                FROM kitel_rental_items i
                LEFT JOIN kitel_rental_loans l ON l.item_id = i.item_id
                WHERE i.category_id = ?
                  AND l.loan_id IS NULL
            ');
            $deleteItems->execute(array($categoryId));

            $retireHistoricalItems = $pdo->prepare('
                UPDATE kitel_rental_items
                SET status = "retired", is_active = 0, updated_at = ?
                WHERE category_id = ?
            ');
            $retireHistoricalItems->execute(array(db_now(), $categoryId));

            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $deleteCategory = $pdo->prepare('DELETE FROM kitel_rental_categories WHERE category_id = ?');
            $deleteCategory->execute(array($categoryId));
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
            return;
        } catch (Exception $e) {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Exception $ignored) {
            }
            $pdo->rollBack();
            throw $e;
        }
    }

    $itemIds = array();
    $itemsWithLoanHistory = array();
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] === (int)$categoryId) {
            if ($item['status'] === 'borrowed') {
                throw new RuntimeException('대여 중인 기자재가 있는 카테고리는 삭제할 수 없습니다.');
            }
            $itemIds[] = (int)$item['item_id'];
        }
    }
    foreach ($state['loans'] as $loan) {
        if (!in_array((int)$loan['item_id'], $itemIds, true)) {
            continue;
        }
        if ($loan['status'] === 'borrowed') {
            throw new RuntimeException('대여 중인 기자재가 있는 카테고리는 삭제할 수 없습니다.');
        }
        $itemsWithLoanHistory[(int)$loan['item_id']] = true;
    }

    detach_category_bundle_links($state, $categoryId);

    foreach ($state['categories'] as $index => $category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            unset($state['categories'][$index]);
            $state['categories'] = array_values($state['categories']);
            break;
        }
    }
    foreach ($state['items'] as $index => &$item) {
        if ((int)$item['category_id'] !== (int)$categoryId) {
            continue;
        }
        if (isset($itemsWithLoanHistory[(int)$item['item_id']])) {
            $item['status'] = 'retired';
            $item['is_active'] = 0;
            $item['updated_at'] = now_text();
        } else {
            unset($state['items'][$index]);
        }
    }
    unset($item);
    $state['items'] = array_values($state['items']);
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
        1,
        $actor
    );
}

function update_category(&$state, $categoryId, $name, $description, $nextSerial, $isActive, $actor)
{
    $name = trim($name);
    $description = trim($description);
    $nextSerial = max(1, (int)$nextSerial);
    $isActive = 1;

    if ($name === '') {
        throw new RuntimeException('카테고리명을 입력해 주세요.');
    }

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM kitel_rental_categories WHERE category_id = ? FOR UPDATE');
            $stmt->execute(array($categoryId));
            $before = $stmt->fetch();
            if (!$before) {
                throw new RuntimeException('카테고리를 찾을 수 없습니다.');
            }
            if ($nextSerial < (int)$before['next_serial']) {
                throw new RuntimeException('개수는 현재 값보다 작게 줄일 수 없습니다.');
            }
            $update = $pdo->prepare('
                UPDATE kitel_rental_categories
                SET name = ?, slug = ?, next_serial = ?, description = ?, is_active = ?, updated_at = ?
                WHERE category_id = ?
            ');
            $update->execute(array($name, slugify($name), $nextSerial, $description, $isActive, db_now(), $categoryId));
            add_log($state, 'category.update', null, null, null, null, $before['name'] . ' -> ' . $name, $actor);
            $pdo->commit();
            return;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    foreach ($state['categories'] as &$category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            if ($nextSerial < (int)$category['next_serial']) {
                throw new RuntimeException('개수는 현재 값보다 작게 줄일 수 없습니다.');
            }
            $beforeName = $category['name'];
            $category['name'] = $name;
            $category['slug'] = slugify($name);
            $category['next_serial'] = $nextSerial;
            $category['description'] = $description;
            $category['is_active'] = $isActive;
            $category['updated_at'] = now_text();
            add_log($state, 'category.update', null, null, null, null, $beforeName . ' -> ' . $name, $actor);
            break;
        }
    }
    unset($category);
}

// 개별 관리(unique) / 개수 관리(bulk) 전환과 1인당 대여 제한 설정.
// 자주 쓰는 기능이 아니라 관리자 화면 한 켠에 조용히 두는 것을 전제로 만든 함수.
function update_category_tracking(&$state, $categoryId, $trackingMode, $maxPerUser, $actor)
{
    $trackingMode = ($trackingMode === 'bulk') ? 'bulk' : 'unique';
    $maxPerUser = trim((string)$maxPerUser) === '' ? null : max(0, (int)$maxPerUser);
    if ($maxPerUser === 0) {
        $maxPerUser = null;
    }

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT name, tracking_mode FROM kitel_rental_categories WHERE category_id = ? FOR UPDATE');
            $stmt->execute(array($categoryId));
            $before = $stmt->fetch();
            if (!$before) {
                throw new RuntimeException('카테고리를 찾을 수 없습니다.');
            }
            $update = $pdo->prepare('UPDATE kitel_rental_categories SET tracking_mode = ?, max_per_user = ?, updated_at = ? WHERE category_id = ?');
            $update->execute(array($trackingMode, $maxPerUser, db_now(), $categoryId));
            add_log($state, 'category.tracking', null, null, $before['tracking_mode'], $trackingMode, $before['name'] . ' 관리 방식 변경', $actor);
            $pdo->commit();
            return;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    foreach ($state['categories'] as &$category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            $before = category_tracking_mode($category);
            $category['tracking_mode'] = $trackingMode;
            $category['max_per_user'] = $maxPerUser;
            $category['updated_at'] = now_text();
            add_log($state, 'category.tracking', null, null, $before, $trackingMode, $category['name'] . ' 관리 방식 변경', $actor);
            break;
        }
    }
    unset($category);
}
