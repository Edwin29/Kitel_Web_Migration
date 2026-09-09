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
