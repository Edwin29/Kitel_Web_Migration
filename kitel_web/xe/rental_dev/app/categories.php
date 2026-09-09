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
function update_category_tracking(&$state, $categoryId, $trackingMode, $maxPerUser, $actor)
{
    $trackingMode = ($trackingMode === 'bulk') ? 'bulk' : 'unique';
    $maxPerUser = trim((string)$maxPerUser) === '' ? null : max(0, (int)$maxPerUser);
    if ($maxPerUser === 0) {
        $maxPerUser = null;
    }

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
            $update = $pdo->prepare('UPDATE kitel_rental_categories SET tracking_mode = ?, max_per_user = ?, updated_at = ? WHERE category_id = ?');
            $update->execute(array($trackingMode, $maxPerUser, db_now(), $categoryId));
            add_log($state, 'category.tracking', null, null, $before['tracking_mode'], $trackingMode, $before['name'] . ' 관리 방식 변경', $actor);
            db_commit($txOwned);
            return;
        } catch (Exception $e) {
            db_rollback($txOwned);
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
