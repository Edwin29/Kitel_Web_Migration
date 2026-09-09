<?php
function rental_seed_state()
{
    $now = now_text();
    $categories = array(
        array('category_id' => 1, 'name' => '니퍼', 'slug' => 'nipper', 'next_serial' => 3, 'description' => '공구류', 'tracking_mode' => 'unique', 'max_per_user' => null, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now),
        array('category_id' => 2, 'name' => '오실로스코프', 'slug' => 'oscilloscope', 'next_serial' => 2, 'description' => '계측 장비', 'tracking_mode' => 'unique', 'max_per_user' => null, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now),
    );
    return array(
        'next_ids' => array('category_id' => 3, 'item_id' => 4, 'loan_id' => 1, 'log_id' => 1, 'bundle_id' => 1),
        'categories' => $categories,
        'bundles' => array(),
        'bundle_categories' => array(),
        'items' => array(
            rental_seed_item(1, 1, '니퍼', 1, '동방 공구함', '기본 공구'),
            rental_seed_item(2, 1, '니퍼', 2, '동방 공구함', '기본 공구'),
            rental_seed_item(3, 2, '오실로스코프', 1, '계측기 선반', '프로브 포함'),
        ),
        'allowed_groups' => config('allowed_groups'),
        'loans' => array(),
        'logs' => array(),
    );
}

function rental_seed_item($id, $categoryId, $categoryName, $serialNo, $location, $note)
{
    $now = now_text();
    return array(
        'item_id' => $id,
        'category_id' => $categoryId,
        'serial_no' => $serialNo,
        'display_no' => $serialNo,
        'label' => $categoryName . '-' . $serialNo,
        'public_code' => generate_public_code(),
        'status' => 'available',
        'location' => $location,
        'condition_note' => $note,
        'admin_memo' => '',
        'is_active' => 1,
        'created_by_member_srl' => 4,
        'created_at' => $now,
        'updated_at' => $now,
    );
}

function rental_load()
{
    if (config('mode') !== 'local') {
        return rental_load_db();
    }
    $file = config('data_file');
    if (!is_file($file)) {
        $state = rental_seed_state();
        rental_save($state);
        return $state;
    }
    $json = file_get_contents($file);
    $state = json_decode($json, true);
    return is_array($state) ? rental_normalize_state($state) : rental_seed_state();
}

function rental_normalize_state($state)
{
    if (!isset($state['next_ids']) || !is_array($state['next_ids'])) {
        $state['next_ids'] = array();
    }
    if (!isset($state['next_ids']['bundle_id'])) {
        $maxBundleId = 0;
        if (isset($state['bundles']) && is_array($state['bundles'])) {
            foreach ($state['bundles'] as $bundle) {
                $maxBundleId = max($maxBundleId, isset($bundle['bundle_id']) ? (int)$bundle['bundle_id'] : 0);
            }
        }
        $state['next_ids']['bundle_id'] = $maxBundleId + 1;
    }
    if (!isset($state['bundles']) || !is_array($state['bundles'])) {
        $state['bundles'] = array();
    }
    if (!isset($state['bundle_categories']) || !is_array($state['bundle_categories'])) {
        $state['bundle_categories'] = array();
    }
    if (isset($state['items']) && is_array($state['items'])) {
        foreach ($state['items'] as &$item) {
            if (!isset($item['display_no']) || (int)$item['display_no'] <= 0) {
                $item['display_no'] = isset($item['serial_no']) ? (int)$item['serial_no'] : 1;
            }
        }
        unset($item);
    }
    return $state;
}

function rental_save($state)
{
    if (config('mode') !== 'local') {
        return;
    }
    $file = config('data_file');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function rental_load_db()
{
    $pdo = db_connect();
    $bundles = array();
    $bundleCategories = array();
    try {
        $bundles = $pdo->query('SELECT * FROM kitel_rental_bundles ORDER BY sort_order, name, bundle_id')->fetchAll();
        $bundleCategories = $pdo->query('SELECT * FROM kitel_rental_bundle_categories ORDER BY sort_order, bundle_id, category_id')->fetchAll();
    } catch (Exception $e) {
        $bundles = array();
        $bundleCategories = array();
    }
    return array(
        'next_ids' => array('category_id' => 1, 'item_id' => 1, 'loan_id' => 1, 'log_id' => 1, 'bundle_id' => 1),
        'categories' => $pdo->query('SELECT * FROM kitel_rental_categories ORDER BY name, category_id')->fetchAll(),
        'bundles' => $bundles,
        'bundle_categories' => $bundleCategories,
        'items' => $pdo->query('SELECT * FROM kitel_rental_items ORDER BY label, item_id')->fetchAll(),
        'allowed_groups' => $pdo->query('SELECT * FROM kitel_rental_allowed_groups ORDER BY permission_type, group_srl')->fetchAll(),
        'loans' => $pdo->query('SELECT * FROM kitel_rental_loans ORDER BY borrowed_at DESC, loan_id DESC')->fetchAll(),
        'logs' => $pdo->query('SELECT * FROM kitel_rental_logs ORDER BY created_at DESC, log_id DESC')->fetchAll(),
    );
}

function db_now()
{
    return date('Y-m-d H:i:s');
}

function next_id(&$state, $key)
{
    $id = isset($state['next_ids'][$key]) ? (int)$state['next_ids'][$key] : 1;
    $state['next_ids'][$key] = $id + 1;
    return $id;
}

function generate_public_code()
{
    return 'EQ-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));
}

function find_category($state, $categoryId)
{
    foreach ($state['categories'] as $category) {
        if ((int)$category['category_id'] === (int)$categoryId) {
            return $category;
        }
    }
    return null;
}

function find_item($state, $itemId)
{
    foreach ($state['items'] as $item) {
        if ((int)$item['item_id'] === (int)$itemId) {
            return $item;
        }
    }
    return null;
}

function find_item_by_code($state, $code)
{
    foreach ($state['items'] as $item) {
        if ($item['public_code'] === $code && (int)$item['is_active'] === 1) {
            return $item;
        }
    }
    return null;
}
