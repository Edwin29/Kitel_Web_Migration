<?php
function active_bundles($state)
{
    $bundles = isset($state['bundles']) && is_array($state['bundles']) ? $state['bundles'] : array();
    return array_values(array_filter($bundles, function ($bundle) {
        return (int)$bundle['is_active'] === 1;
    }));
}

function bundle_category_links($state)
{
    return isset($state['bundle_categories']) && is_array($state['bundle_categories'])
        ? $state['bundle_categories']
        : array();
}

function find_bundle($state, $bundleId)
{
    foreach (active_bundles($state) as $bundle) {
        if ((int)$bundle['bundle_id'] === (int)$bundleId) {
            return $bundle;
        }
    }
    return null;
}

function find_bundle_by_code($state, $code)
{
    $code = trim((string)$code);
    foreach (active_bundles($state) as $bundle) {
        if (isset($bundle['public_code']) && $bundle['public_code'] === $code) {
            return $bundle;
        }
    }
    return null;
}

function bundle_id_for_category($state, $categoryId)
{
    foreach (bundle_category_links($state) as $link) {
        if ((int)$link['category_id'] === (int)$categoryId) {
            return (int)$link['bundle_id'];
        }
    }
    return 0;
}

function bundle_categories($state, $bundleId)
{
    $links = array();
    foreach (bundle_category_links($state) as $link) {
        if ((int)$link['bundle_id'] === (int)$bundleId) {
            $links[(int)$link['category_id']] = (int)$link['sort_order'];
        }
    }
    $categories = array();
    foreach (active_categories($state) as $category) {
        $categoryId = (int)$category['category_id'];
        if (isset($links[$categoryId])) {
            $category['_bundle_sort_order'] = $links[$categoryId];
            $categories[] = $category;
        }
    }
    usort($categories, function ($a, $b) {
        if ((int)$a['_bundle_sort_order'] === (int)$b['_bundle_sort_order']) {
            return strcmp($a['name'], $b['name']);
        }
        return (int)$a['_bundle_sort_order'] - (int)$b['_bundle_sort_order'];
    });
    return $categories;
}

function unassigned_categories($state)
{
    $assigned = array();
    foreach (bundle_category_links($state) as $link) {
        $assigned[(int)$link['category_id']] = true;
    }
    return array_values(array_filter(active_categories($state), function ($category) use ($assigned) {
        return !isset($assigned[(int)$category['category_id']]);
    }));
}

function unique_bundle_public_code($state)
{
    do {
        $code = 'BND-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));
        $exists = false;
        foreach (active_bundles($state) as $bundle) {
            if (isset($bundle['public_code']) && $bundle['public_code'] === $code) {
                $exists = true;
                break;
            }
        }
    } while ($exists);
    return $code;
}

function unique_bundle_public_code_db($pdo)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM kitel_rental_bundles WHERE public_code = ?');
    do {
        $code = 'BND-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));
        $stmt->execute(array($code));
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists);
    return $code;
}

function next_bundle_sort_order($state)
{
    $max = 0;
    foreach (active_bundles($state) as $bundle) {
        $max = max($max, isset($bundle['sort_order']) ? (int)$bundle['sort_order'] : 0);
    }
    return $max + 10;
}

function next_bundle_category_sort_order($state, $bundleId)
{
    $max = 0;
    foreach (bundle_category_links($state) as $link) {
        if ((int)$link['bundle_id'] === (int)$bundleId) {
            $max = max($max, isset($link['sort_order']) ? (int)$link['sort_order'] : 0);
        }
    }
    return $max + 10;
}

function create_bundle(&$state, $name, $description, $actor)
{
    $name = trim($name);
    $description = trim($description);
    if ($name === '') {
        throw new RuntimeException('묶음명을 입력해 주세요.');
    }

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $now = db_now();
        $stmt = $pdo->prepare('
            INSERT INTO kitel_rental_bundles
                (name, public_code, description, sort_order, is_active, created_by_member_srl, created_at, updated_at)
            VALUES (?, ?, ?, COALESCE((SELECT max_sort + 10 FROM (SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM kitel_rental_bundles) s), 10), 1, ?, ?, ?)
        ');
        $publicCode = unique_bundle_public_code_db($pdo);
        $stmt->execute(array($name, $publicCode, $description, $actor['member_srl'], $now, $now));
        add_log($state, 'bundle.create', null, null, null, null, $name, $actor);
        return (int)$pdo->lastInsertId();
    }

    $now = now_text();
    $bundle = array(
        'bundle_id' => next_id($state, 'bundle_id'),
        'name' => $name,
        'public_code' => unique_bundle_public_code($state),
        'description' => $description,
        'sort_order' => next_bundle_sort_order($state),
        'is_active' => 1,
        'created_by_member_srl' => $actor['member_srl'],
        'created_at' => $now,
        'updated_at' => $now,
    );
    $state['bundles'][] = $bundle;
    add_log($state, 'bundle.create', null, null, null, null, $name, $actor);
    return (int)$bundle['bundle_id'];
}

function update_bundle_name(&$state, $bundleId, $name, $actor)
{
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException('묶음명을 입력해 주세요.');
    }

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('SELECT name FROM kitel_rental_bundles WHERE bundle_id = ? AND is_active = 1');
        $stmt->execute(array($bundleId));
        $before = $stmt->fetchColumn();
        if ($before === false) {
            throw new RuntimeException('묶음을 찾을 수 없습니다.');
        }
        $update = $pdo->prepare('UPDATE kitel_rental_bundles SET name = ?, updated_at = ? WHERE bundle_id = ?');
        $update->execute(array($name, db_now(), $bundleId));
        add_log($state, 'bundle.update', null, null, null, null, $before . ' -> ' . $name, $actor);
        return;
    }

    foreach ($state['bundles'] as &$bundle) {
        if ((int)$bundle['bundle_id'] === (int)$bundleId && (int)$bundle['is_active'] === 1) {
            $before = $bundle['name'];
            $bundle['name'] = $name;
            $bundle['updated_at'] = now_text();
            add_log($state, 'bundle.update', null, null, null, null, $before . ' -> ' . $name, $actor);
            return;
        }
    }
    unset($bundle);
    throw new RuntimeException('묶음을 찾을 수 없습니다.');
}

function delete_bundle(&$state, $bundleId, $actor)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT name FROM kitel_rental_bundles WHERE bundle_id = ? AND is_active = 1 FOR UPDATE');
            $stmt->execute(array($bundleId));
            $name = $stmt->fetchColumn();
            if ($name === false) {
                throw new RuntimeException('묶음을 찾을 수 없습니다.');
            }
            $deleteLinks = $pdo->prepare('DELETE FROM kitel_rental_bundle_categories WHERE bundle_id = ?');
            $deleteLinks->execute(array($bundleId));
            $deleteBundle = $pdo->prepare('DELETE FROM kitel_rental_bundles WHERE bundle_id = ?');
            $deleteBundle->execute(array($bundleId));
            add_log($state, 'bundle.delete', null, null, null, null, $name, $actor);
            $pdo->commit();
            return;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    $name = '';
    foreach ($state['bundles'] as $index => $bundle) {
        if ((int)$bundle['bundle_id'] === (int)$bundleId) {
            $name = $bundle['name'];
            unset($state['bundles'][$index]);
            break;
        }
    }
    $state['bundles'] = array_values($state['bundles']);
    $state['bundle_categories'] = array_values(array_filter(bundle_category_links($state), function ($link) use ($bundleId) {
        return (int)$link['bundle_id'] !== (int)$bundleId;
    }));
    if ($name === '') {
        throw new RuntimeException('묶음을 찾을 수 없습니다.');
    }
    add_log($state, 'bundle.delete', null, null, null, null, $name, $actor);
}

function move_category_to_bundle(&$state, $categoryId, $bundleId, $actor)
{
    $category = find_category($state, $categoryId);
    if (!$category || (int)$category['is_active'] !== 1) {
        throw new RuntimeException('카테고리를 찾을 수 없습니다.');
    }
    if ($bundleId > 0 && !find_bundle($state, $bundleId)) {
        throw new RuntimeException('묶음을 찾을 수 없습니다.');
    }

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM kitel_rental_bundle_categories WHERE category_id = ?');
            $delete->execute(array($categoryId));
            if ($bundleId > 0) {
                $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM kitel_rental_bundle_categories WHERE bundle_id = ?');
                $sortStmt->execute(array($bundleId));
                $sortOrder = (int)$sortStmt->fetchColumn();
                $insert = $pdo->prepare('
                    INSERT INTO kitel_rental_bundle_categories
                        (bundle_id, category_id, sort_order, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $insert->execute(array($bundleId, $categoryId, $sortOrder, db_now(), db_now()));
            }
            add_log($state, 'bundle.category.move', null, null, null, null, $category['name'], $actor);
            $pdo->commit();
            return;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    $state['bundle_categories'] = array_values(array_filter(bundle_category_links($state), function ($link) use ($categoryId) {
        return (int)$link['category_id'] !== (int)$categoryId;
    }));
    if ($bundleId > 0) {
        $now = now_text();
        $state['bundle_categories'][] = array(
            'bundle_id' => (int)$bundleId,
            'category_id' => (int)$categoryId,
            'sort_order' => next_bundle_category_sort_order($state, $bundleId),
            'created_at' => $now,
            'updated_at' => $now,
        );
    }
    add_log($state, 'bundle.category.move', null, null, null, null, $category['name'], $actor);
}

function move_categories_to_bundle(&$state, array $categoryIds, $bundleId, $actor)
{
    $moved = 0;
    foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
        move_category_to_bundle($state, $categoryId, (int)$bundleId, $actor);
        $moved++;
    }
    return $moved;
}

function detach_category_bundle_links(&$state, $categoryId)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('DELETE FROM kitel_rental_bundle_categories WHERE category_id = ?');
        $stmt->execute(array($categoryId));
        return;
    }
    $state['bundle_categories'] = array_values(array_filter(bundle_category_links($state), function ($link) use ($categoryId) {
        return (int)$link['category_id'] !== (int)$categoryId;
    }));
}

function bulk_update_category_tracking(&$state, array $categoryIds, $trackingMode, $maxPerUser, $actor)
{
    $updated = 0;
    foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
        update_category_tracking($state, $categoryId, $trackingMode, $maxPerUser, $actor);
        $updated++;
    }
    return $updated;
}

function bulk_delete_categories(&$state, array $categoryIds, $actor)
{
    $deleted = 0;
    $ids = array_unique(array_map('intval', $categoryIds));
    foreach ($ids as $categoryId) {
        if (category_has_borrowed_items($state, $categoryId)) {
            $category = find_category($state, $categoryId);
            $name = $category ? $category['name'] : '선택한 카테고리';
            throw new RuntimeException($name . ': 대여 중인 기자재가 있어 삭제할 수 없습니다.');
        }
    }
    foreach ($ids as $categoryId) {
        delete_category($state, $categoryId, $actor);
        $deleted++;
    }
    return $deleted;
}

function category_has_borrowed_items($state, $categoryId)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM kitel_rental_items i
            LEFT JOIN kitel_rental_loans l ON l.item_id = i.item_id
            WHERE i.category_id = ?
              AND (i.status = "borrowed" OR l.status = "borrowed")
        ');
        $stmt->execute(array($categoryId));
        return (int)$stmt->fetchColumn() > 0;
    }

    $itemIds = array();
    foreach ($state['items'] as $item) {
        if ((int)$item['category_id'] === (int)$categoryId) {
            if ($item['status'] === 'borrowed') {
                return true;
            }
            $itemIds[(int)$item['item_id']] = true;
        }
    }
    foreach ($state['loans'] as $loan) {
        if (isset($itemIds[(int)$loan['item_id']]) && $loan['status'] === 'borrowed') {
            return true;
        }
    }
    return false;
}

function scan_items_for_category($state, $categoryId, $mode)
{
    $items = array();
    foreach (active_items($state) as $item) {
        if ((int)$item['category_id'] !== (int)$categoryId) {
            continue;
        }
        if ($mode === 'rent' && $item['status'] !== 'available') {
            continue;
        }
        if ($mode === 'return' && $item['status'] !== 'borrowed') {
            continue;
        }
        $items[] = array(
            'item_id' => (int)$item['item_id'],
            'label' => $item['label'],
            'public_code' => $item['public_code'],
            'location' => $item['location'],
            'status' => $item['status'],
        );
    }
    return $items;
}

function bundle_scan_options($state, $code, $mode)
{
    $bundle = find_bundle_by_code($state, $code);
    if (!$bundle) {
        throw new RuntimeException('등록되지 않은 묶음 QR입니다.');
    }
    $categories = array();
    foreach (bundle_categories($state, $bundle['bundle_id']) as $category) {
        $counts = category_item_counts($state, $category['category_id']);
        $modeName = category_tracking_mode($category);
        $row = array(
            'category_id' => (int)$category['category_id'],
            'name' => $category['name'],
            'tracking_mode' => $modeName,
            'tracking_label' => $modeName === 'bulk' ? '개수' : '개별',
            'counts' => $counts,
            'max_per_user' => category_max_per_user($category),
            'items' => array(),
        );
        if ($modeName === 'unique') {
            $row['items'] = scan_items_for_category($state, $category['category_id'], $mode);
        }
        $categories[] = $row;
    }
    return array(
        'bundle' => array(
            'bundle_id' => (int)$bundle['bundle_id'],
            'name' => $bundle['name'],
            'public_code' => $bundle['public_code'],
        ),
        'categories' => $categories,
    );
}
