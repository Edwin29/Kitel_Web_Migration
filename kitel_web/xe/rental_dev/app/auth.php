<?php
function current_user()
{
    static $user = null;
    if ($user !== null) {
        return $user;
    }

    if (config('mode') === 'local') {
        $user = config('fake_user');
        return $user;
    }

    $user = xe_current_user();
    return $user;
}

function xe_current_user()
{
    if (!defined('__XE__')) {
        define('__XE__', true);
    }

    $xeRoot = rtrim(config('xe_root'), '/');
    $common = $xeRoot . '/config/config.inc.php';
    if (is_file($common)) {
        require_once $common;
    }

    if (class_exists('Context')) {
        if (method_exists('Context', 'getInstance')) {
            $context = Context::getInstance();
            if ($context && method_exists($context, 'init')) {
                $context->init();
            }
        }
        $loggedInfo = Context::get('logged_info');
        if ($loggedInfo) {
            return array(
                'member_srl' => isset($loggedInfo->member_srl) ? (int)$loggedInfo->member_srl : 0,
                'user_id' => isset($loggedInfo->user_id) ? $loggedInfo->user_id : '',
                'nick_name' => isset($loggedInfo->nick_name) ? $loggedInfo->nick_name : '',
                'email_address' => isset($loggedInfo->email_address) ? $loggedInfo->email_address : '',
                'is_admin' => isset($loggedInfo->is_admin) ? $loggedInfo->is_admin : 'N',
                'groups' => xe_member_groups(isset($loggedInfo->member_srl) ? (int)$loggedInfo->member_srl : 0),
            );
        }
    }

    return null;
}

function xe_member_groups($memberSrl)
{
    if ($memberSrl <= 0) {
        return array();
    }
    if (!function_exists('db_connect')) {
        return array();
    }
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT group_srl FROM ' . xe_table('member_group_member') . ' WHERE member_srl = ?');
    $stmt->execute(array($memberSrl));
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function find_xe_member($memberSrl)
{
    if ((int)$memberSrl <= 0) {
        return null;
    }
    if (config('mode') === 'local') {
        $fake = config('fake_user');
        if ((int)$fake['member_srl'] === (int)$memberSrl) {
            return $fake;
        }
        return array(
            'member_srl' => (int)$memberSrl,
            'user_id' => 'member' . (int)$memberSrl,
            'nick_name' => '회원' . (int)$memberSrl,
            'email_address' => '',
            'is_admin' => 'N',
            'groups' => array(),
        );
    }
    $pdo = db_connect();
    $stmt = $pdo->prepare('
        SELECT member_srl, user_id, nick_name, email_address, is_admin
        FROM ' . xe_table('member') . '
        WHERE member_srl = ?
    ');
    $stmt->execute(array((int)$memberSrl));
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['member_srl'] = (int)$row['member_srl'];
    $row['groups'] = xe_member_groups($row['member_srl']);
    return $row;
}

// ---------------------------------------------------------------------------
// 처리자(member_srl) 이름 표시
//
// 로그 화면은 예전에 actor_member_srl 숫자를 그대로 찍었다. find_xe_member()가
// 이미 있는데도 안 썼던 것. 다만 한 페이지에 100건이면 100번 조회할 수는 없어서,
// 페이지에 등장하는 member_srl을 한 번에 모아 오는 프리페치를 둔다.
// ---------------------------------------------------------------------------

function member_name_cache(&$store = null)
{
    static $cache = array();
    if ($store !== null) {
        $cache = $store + $cache;
    }
    return $cache;
}

// 한 번의 쿼리로 여러 member_srl의 이름을 미리 채운다.
function prefetch_member_names(array $memberSrls)
{
    $ids = array();
    foreach ($memberSrls as $srl) {
        $id = (int)$srl;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    $cache = member_name_cache();
    $missing = array_values(array_diff(array_keys($ids), array_keys($cache)));
    if (!$missing) {
        return;
    }

    $found = array();
    if (config('mode') === 'local') {
        foreach ($missing as $id) {
            $m = find_xe_member($id);
            $found[$id] = $m ? $m['nick_name'] : '';
        }
    } else {
        $pdo = db_connect();
        $placeholders = implode(',', array_fill(0, count($missing), '?'));
        $stmt = $pdo->prepare('SELECT member_srl, user_id, nick_name FROM ' . xe_table('member') . ' WHERE member_srl IN (' . $placeholders . ')');
        $stmt->execute($missing);
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string)$row['nick_name']);
            $found[(int)$row['member_srl']] = $name !== '' ? $name : (string)$row['user_id'];
        }
        // 탈퇴 등으로 못 찾은 것도 캐시에 남겨 재조회를 막는다.
        foreach ($missing as $id) {
            if (!isset($found[$id])) {
                $found[$id] = '';
            }
        }
    }
    member_name_cache($found);
}

// 화면에 표시할 처리자 이름. 못 찾으면 빈 문자열.
function actor_display_name($memberSrl)
{
    $id = (int)$memberSrl;
    if ($id <= 0) {
        return '';
    }
    $cache = member_name_cache();
    if (!array_key_exists($id, $cache)) {
        prefetch_member_names(array($id));
        $cache = member_name_cache();
    }
    return isset($cache[$id]) ? $cache[$id] : '';
}

// "이름 · #srl" 형태. 이름을 못 찾으면 번호만.
function actor_label($memberSrl)
{
    $id = (int)$memberSrl;
    if ($id <= 0) {
        return '시스템';
    }
    $name = actor_display_name($id);
    return $name !== '' ? $name . ' · #' . $id : '#' . $id;
}

// 로그 필터의 "처리자" 칸에 숫자 대신 이름을 넣을 수 있게 한다.
// 숫자면 그대로 member_srl로, 아니면 이름/아이디로 회원을 찾아 srl 목록을 돌려준다.
function resolve_actor_filter($input)
{
    $input = trim((string)$input);
    if ($input === '') {
        return array();
    }
    if (ctype_digit($input)) {
        return array((int)$input);
    }
    $srls = array();
    foreach (search_xe_members($input, 50) as $member) {
        $srls[] = (int)$member['member_srl'];
    }
    // 검색 결과가 없으면 -1을 넣어 "아무것도 일치하지 않음"이 되게 한다
    // (빈 배열을 돌려주면 필터가 통째로 무시되어 전체가 나온다).
    return $srls ? $srls : array(-1);
}

function search_xe_members($query, $limit = 20)
{
    $query = trim($query);
    if ($query === '') {
        return array();
    }
    if (config('mode') === 'local') {
        $fake = config('fake_user');
        return stripos($fake['nick_name'] . ' ' . $fake['user_id'], $query) !== false ? array($fake) : array();
    }
    $pdo = db_connect();
    $like = '%' . $query . '%';
    $stmt = $pdo->prepare('
        SELECT member_srl, user_id, nick_name, email_address, is_admin
        FROM ' . xe_table('member') . '
        WHERE user_id LIKE ? OR nick_name LIKE ? OR email_address LIKE ?
        ORDER BY nick_name
        LIMIT ' . (int)$limit
    );
    $stmt->execute(array($like, $like, $like));
    return $stmt->fetchAll();
}

function current_return_path()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : app_url('');

    // 안전장치: 같은 사이트 내부 경로만 허용
    if ($uri === '' || $uri[0] !== '/') {
        return app_url('');
    }

    return $uri;
}

function xe_login_url_with_return($returnPath)
{
    $loginUrl = '/index.php?act=dispMemberLoginForm';

    return $loginUrl
        . '&success_return_url=' . rawurlencode($returnPath)
        . '&redirect_url=' . rawurlencode($returnPath)
        . '&return_url=' . rawurlencode($returnPath);
}


function require_login()
{
    $user = current_user();
    if ($user) {
        return $user;
    }

    $returnPath = current_return_path();

    header('Location: ' . app_url('login.php?return=' . rawurlencode($returnPath)));
    exit;
}

function xe_logout_url()
{
    return app_url('logout.php');
}
