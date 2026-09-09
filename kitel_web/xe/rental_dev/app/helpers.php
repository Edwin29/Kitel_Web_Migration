<?php
function config($key = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
        enforce_local_mode_safety($config);
    }
    return $key === null ? $config : (isset($config[$key]) ? $config[$key] : null);
}

function enforce_local_mode_safety($config)
{
    if (!isset($config['mode']) || $config['mode'] !== 'local') {
        return;
    }
    if (PHP_SAPI === 'cli') {
        return;
    }
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    $host = preg_replace('/:\d+$/', '', $host);
    $allowed = isset($config['allow_local_http_hosts']) ? $config['allow_local_http_hosts'] : array('localhost', '127.0.0.1', '::1');
    if (!in_array($host, $allowed, true)) {
        http_response_code(500);
        exit('KITEL_RENTAL_MODE is local on a non-local host. Set KITEL_RENTAL_MODE=production.');
    }
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_url($path = '')
{
    return rtrim(config('base_url'), '/') . '/' . ltrim($path, '/');
}

function canonical_url($path = '')
{
    return rtrim(config('canonical_base_url'), '/') . '/' . ltrim($path, '/');
}

function redirect_to($path)
{
    header('Location: ' . app_url($path));
    exit;
}

function is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_post()
{
    if (!is_post()) {
        http_response_code(405);
        exit('Method Not Allowed');
    }
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function flash($message = null)
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $message;
}

function now_text()
{
    return date('Y-m-d H:i:s');
}

function date_only($value)
{
    return substr((string)$value, 0, 10);
}

function default_due_date()
{
    return date('Y-m-d', strtotime('+' . (int)config('default_due_days') . ' days'));
}

function normalize_due_date($value)
{
    $value = trim((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new RuntimeException('반납 예정일 형식이 올바르지 않습니다.');
    }
    if ($value < date('Y-m-d')) {
        throw new RuntimeException('반납 예정일은 오늘 또는 이후로 입력해 주세요.');
    }
    return $value;
}

function slugify($value)
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9가-힣]+/u', '-', $value), '-'));
    return $slug !== '' ? $slug : 'category-' . time();
}

function status_label($status)
{
    $labels = array(
        'available' => '대여 가능',
        'borrowed' => '대여 중',
        'unavailable' => '대여 불가',
        'broken' => '고장',
        'lost' => '분실',
        'retired' => '폐기/사용 종료',
        'returned' => '반납 완료',
        'force_returned' => '강제 반납',
        'cancelled' => '취소',
    );
    return isset($labels[$status]) ? $labels[$status] : $status;
}

// 접속자 IP.
//
// 운영 서버는 Apache(php-fpm) 앞에 프록시가 놓일 수 있는 구성이라, 그 경우
// REMOTE_ADDR이 프록시 주소로 고정되어 동방 와이파이 판별이 항상 실패한다.
// 그렇다고 X-Forwarded-For를 무조건 믿으면 헤더만 위조해서 IP 제한을 우회할 수 있다.
//
// 그래서 "REMOTE_ADDR이 신뢰하는 프록시일 때만" XFF를 본다.
// trusted_proxies 기본값은 비어 있고, 그 상태에서는 예전과 완전히 동일하게
// REMOTE_ADDR만 쓴다 — 실측 전까지 동작이 바뀌지 않는다.
// 실측 결과 프록시를 거치는 게 확인되면 KITEL_RENTAL_TRUSTED_PROXIES 에 그 주소를
// 넣어야 XFF가 반영된다. 관리자 화면의 "네트워크 점검"에서 실제 값을 볼 수 있다.
function client_ip()
{
    $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $trustedProxies = config('trusted_proxies');
    if (!$trustedProxies || $remote === '') {
        return $remote;
    }

    $isTrusted = function ($ip) use ($trustedProxies) {
        foreach ($trustedProxies as $cidr) {
            if ($cidr !== '' && ip_in_cidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    };

    if (!$isTrusted($remote)) {
        return $remote;
    }

    $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
    if ($forwarded === '') {
        return $remote;
    }

    // XFF는 "클라이언트, 프록시1, 프록시2" 순. 오른쪽부터 훑어서 신뢰 프록시가
    // 아닌 첫 주소가 실제 접속자다. 그 왼쪽은 클라이언트가 위조할 수 있으므로 믿지 않는다.
    $hops = array_reverse(array_map('trim', explode(',', $forwarded)));
    foreach ($hops as $hop) {
        if ($hop !== '' && !$isTrusted($hop)) {
            return $hop;
        }
    }
    return $remote;
}

// 카메라 스캔은 모바일에서만 의미가 있어서, User-Agent로 대략 판별한다.
// 완벽한 판별은 아니지만(우회 가능), 데스크톱에서 카메라 없는 상태로
// 스캔 화면을 여는 걸 막는 용도로는 충분하다.
function is_mobile_user_agent()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return (bool)preg_match('/Android|iPhone|iPad|iPod|Mobile|BlackBerry|IEMobile/i', $ua);
}

// $cidr 예: "192.168.1.0/24". IPv4만 지원 (동방 와이파이 확인 결과가 전부 IPv4였음).
function ip_in_cidr($ip, $cidr)
{
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }
    list($subnet, $bits) = explode('/', $cidr, 2);
    $bits = (int)$bits;
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
        return false;
    }
    $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
    return ($ipLong & $mask) === ($subnetLong & $mask);
}

// 대여/반납 액션을 동방 와이파이(신뢰 네트워크)에서만 허용할지 검사한다.
// 기능이 꺼져 있으면(require_trusted_network_for_rental=false) 항상 통과시킨다.
function client_on_trusted_network()
{
    if (!config('require_trusted_network_for_rental')) {
        return true;
    }
    $ip = client_ip();
    foreach (config('trusted_network_cidrs') as $cidr) {
        if ($cidr !== '' && ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

function require_trusted_network_or_redirect($redirectTo)
{
    if (!client_on_trusted_network()) {
        flash('동방 와이파이에 연결된 상태에서만 대여/반납할 수 있습니다.');
        redirect_to($redirectTo);
    }
}

// 대여목록/반납목록 공용 세션 헬퍼. $key는 'rent_list' 또는 'return_list'.
// 개별 항목은 {type:'item', item_id, label}, 개수 관리 카테고리 항목은
// {type:'category', category_id, name, quantity} 형태로 저장한다.
function cart_get($key)
{
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = array();
    }
    $list = $_SESSION[$key];
    usort($list, function ($a, $b) {
        $nameA = isset($a['label']) ? $a['label'] : (isset($a['name']) ? $a['name'] : '');
        $nameB = isset($b['label']) ? $b['label'] : (isset($b['name']) ? $b['name'] : '');
        return strnatcasecmp($nameA, $nameB);
    });
    return $list;
}

function cart_has_item($key, $itemId)
{
    foreach (cart_get($key) as $entry) {
        if ($entry['type'] === 'item' && (int)$entry['item_id'] === (int)$itemId) {
            return true;
        }
    }
    return false;
}

// 이미 담겨 있으면 false를 돌려주고 아무 것도 하지 않는다 (스캔 중복 방지).
function cart_add_item($key, $itemId, $label)
{
    if (cart_has_item($key, $itemId)) {
        return false;
    }
    $list = cart_get($key);
    $list[] = array('type' => 'item', 'item_id' => (int)$itemId, 'label' => $label);
    $_SESSION[$key] = $list;
    return true;
}

// 카테고리 항목은 스캔할 때마다 수량을 새로 입력받아 덮어쓴다 (더하기 아님).
function cart_set_category_quantity($key, $categoryId, $name, $quantity)
{
    $list = cart_get($key);
    $found = false;
    foreach ($list as &$entry) {
        if ($entry['type'] === 'category' && (int)$entry['category_id'] === (int)$categoryId) {
            $entry['quantity'] = (int)$quantity;
            $entry['name'] = $name;
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $list[] = array('type' => 'category', 'category_id' => (int)$categoryId, 'name' => $name, 'quantity' => (int)$quantity);
    }
    $_SESSION[$key] = $list;
}

function cart_category_quantity($key, $categoryId)
{
    foreach (cart_get($key) as $entry) {
        if ($entry['type'] === 'category' && (int)$entry['category_id'] === (int)$categoryId) {
            return (int)$entry['quantity'];
        }
    }
    return 0;
}

function cart_remove($key, $index)
{
    $list = cart_get($key);
    if (isset($list[$index])) {
        unset($list[$index]);
        $_SESSION[$key] = array_values($list);
    }
}

function cart_clear($key)
{
    $_SESSION[$key] = array();
}

function cart_count($key)
{
    return count(cart_get($key));
}
