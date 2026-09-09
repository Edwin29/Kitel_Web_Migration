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
