<?php
function db_connect()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $configFile = rtrim(config('xe_root'), '/') . '/files/config/db.config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('XE DB 설정 파일을 찾을 수 없습니다.');
    }

    if (!defined('__XE__')) {
        define('__XE__', true);
    }
    $loaded = include $configFile;
    $dbInfo = isset($db_info) ? $db_info : $loaded;
    if (is_array($dbInfo) && isset($dbInfo['master_db'])) {
        $dbInfo = $dbInfo['master_db'];
    }
    if (is_object($dbInfo) && isset($dbInfo->master_db)) {
        $dbInfo = $dbInfo->master_db;
    }
    if (is_array($dbInfo) && isset($dbInfo[0])) {
        $dbInfo = $dbInfo[0];
    }
    if (!is_array($dbInfo) && !is_object($dbInfo)) {
        throw new RuntimeException('XE DB 설정 형식을 읽을 수 없습니다.');
    }

    $host = db_config_value($dbInfo, array('db_hostname', 'host'), 'localhost');
    $port = db_config_value($dbInfo, array('db_port', 'port'), '3306');
    $name = db_config_value($dbInfo, array('db_database', 'database', 'db_name'), '');
    $user = db_config_value($dbInfo, array('db_userid', 'user', 'username'), '');
    $pass = db_config_value($dbInfo, array('db_password', 'password'), '');

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));
    return $pdo;
}

// 콜백을 트랜잭션 안에서 실행한다. 이미 트랜잭션이 열려 있으면 새로 열지 않고
// 호출한 쪽의 트랜잭션에 그대로 합류한다 (PDO는 중첩 beginTransaction을 지원하지 않는다).
//
// 이 덕분에 update_item_status() 같은 단일 작업 함수를 그대로 둔 채,
// bulk_update_item_status()나 CSV 가져오기가 전체를 하나의 트랜잭션으로 감쌀 수 있다.
// 예전에는 건별로 트랜잭션이 따로 열려서, 중간에 실패하면 절반만 반영된 채 남았다.
function db_transaction(callable $fn)
{
    $pdo = db_connect();
    if ($pdo->inTransaction()) {
        return $fn($pdo);
    }
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// begin/commit/rollback 을 직접 쓰는 기존 함수들을 중첩 안전하게 만들기 위한 짝.
// 이미 트랜잭션이 열려 있으면 db_begin()이 false를 돌려주고, 그 경우
// db_commit()/db_rollback()은 아무것도 하지 않는다 — 트랜잭션의 소유권은
// 가장 바깥에서 연 쪽에 있고, 커밋/롤백도 거기서 한 번만 일어난다.
function db_begin()
{
    $pdo = db_connect();
    if ($pdo->inTransaction()) {
        return false;
    }
    $pdo->beginTransaction();
    return true;
}

function db_commit($owned)
{
    if ($owned) {
        db_connect()->commit();
    }
}

function db_rollback($owned)
{
    if ($owned && db_connect()->inTransaction()) {
        db_connect()->rollBack();
    }
}

// db_transaction()의 모드 인식 버전. 로컬 모드(파일 기반 fake DB)에서는
// 트랜잭션 개념이 없으므로 콜백을 그대로 실행한다.
// 일괄 처리 함수들이 "운영이면 한 트랜잭션, 로컬이면 그냥 실행"을 표현할 때 쓴다.
function db_run_atomically(callable $fn)
{
    if (config('mode') === 'local') {
        return $fn(null);
    }
    return db_transaction($fn);
}

function db_config_value($source, $keys, $default)
{
    foreach ($keys as $key) {
        if (is_array($source) && isset($source[$key])) {
            return $source[$key];
        }
        if (is_object($source) && isset($source->{$key})) {
            return $source->{$key};
        }
    }
    return $default;
}

function xe_db_config()
{
    static $dbInfo = null;
    if ($dbInfo !== null) {
        return $dbInfo;
    }
    $configFile = rtrim(config('xe_root'), '/') . '/files/config/db.config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('XE DB 설정 파일을 찾을 수 없습니다.');
    }
    if (!defined('__XE__')) {
        define('__XE__', true);
    }
    $loaded = include $configFile;
    $dbInfo = isset($db_info) ? $db_info : $loaded;
    if (is_array($dbInfo) && isset($dbInfo['master_db'])) {
        $dbInfo = $dbInfo['master_db'];
    }
    if (is_object($dbInfo) && isset($dbInfo->master_db)) {
        $dbInfo = $dbInfo->master_db;
    }
    if (is_array($dbInfo) && isset($dbInfo[0])) {
        $dbInfo = $dbInfo[0];
    }
    return $dbInfo;
}

function xe_db_prefix()
{
    $dbInfo = xe_db_config();
    return preg_replace('/[^A-Za-z0-9_]/', '', db_config_value($dbInfo, array('db_table_prefix', 'table_prefix', 'prefix'), 'xe_'));
}

function xe_table($name)
{
    return xe_db_prefix() . $name;
}
