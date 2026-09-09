<?php
function rental_seed_state()
{
    $now = now_text();
    $categories = array(
        array('category_id' => 1, 'name' => '니퍼', 'slug' => 'nipper', 'next_serial' => 3, 'description' => '공구류', 'tracking_mode' => 'unique', 'max_per_user' => null, 'due_days' => null, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now),
        array('category_id' => 2, 'name' => '오실로스코프', 'slug' => 'oscilloscope', 'next_serial' => 2, 'description' => '계측 장비', 'tracking_mode' => 'unique', 'max_per_user' => null, 'due_days' => null, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now),
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

// 운영 모드에서는 "개수가 제한적인" 테이블만 미리 읽어둔다.
// kitel_rental_loans / kitel_rental_logs 는 사용할수록 무한히 커지는 테이블이라
// 여기서 통째로 읽지 않는다. 두 테이블은 loans.php / logger.php 의 조회 함수를 통해
// 필요한 만큼만(WHERE + LIMIT) 가져온다. 이 두 키에 배열 대신 rental_unloaded()가
// 들어 있는 이유는, 예전처럼 $state['loans'] 를 직접 훑는 코드가 남아 있으면
// 조용히 빈 배열로 동작하는 대신 명확한 예외로 알려주기 위해서다.
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
        'loans' => rental_unloaded('loans'),
        'logs' => rental_unloaded('logs'),
    );
}

// $state['loans'] / $state['logs'] 를 직접 훑으려는 코드를 잡아내기 위한 표식.
// 배열처럼 순회하려고 하면 그 자리에서 터진다.
function rental_unloaded($name)
{
    return new RentalUnloadedTable($name);
}

class RentalUnloadedTable implements IteratorAggregate, Countable
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    private function fail()
    {
        throw new RuntimeException(
            'kitel_rental_' . $this->name . ' 는 운영 모드에서 통째로 읽지 않습니다. '
            . ($this->name === 'logs' ? 'log_query()/logs_for_item()' : 'loan_query()/loans_for_item()')
            . ' 등 조회 함수를 사용하세요.'
        );
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        $this->fail();
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        $this->fail();
    }
}

// 목록 조회 결과의 공통 형태. 화면은 rows 를 그대로 순회하면 되고,
// 정렬은 항상 이 계층에서 "최신 우선"으로 확정된다 (화면에서 뒤집지 않는다).
function rental_page($rows, $total, $page, $perPage)
{
    $perPage = max(1, (int)$perPage);
    $total = (int)$total;
    $pages = max(1, (int)ceil($total / $perPage));
    return array(
        'rows' => $rows,
        'total' => $total,
        'page' => min(max(1, (int)$page), $pages),
        'per_page' => $perPage,
        'pages' => $pages,
    );
}

// $_GET['page'] 처럼 신뢰할 수 없는 값에서 페이지 번호를 뽑는다.
function rental_page_param($key = 'page')
{
    $value = isset($_GET[$key]) ? (int)$_GET[$key] : 1;
    return max(1, $value);
}

// 현재 쿼리스트링에서 몇 개 키만 갈아끼운 URL을 만든다.
// 페이지 이동 링크와 "지금 화면 그대로 CSV 내보내기" 링크가 같이 쓴다.
function rental_query_url($path, array $overrides = array())
{
    $params = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $query = http_build_query($params);
    return app_url($path . ($query !== '' ? '?' . $query : ''));
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
