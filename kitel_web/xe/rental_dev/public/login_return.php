<?php
require_once __DIR__ . '/_bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = current_user();

if (!$user) {
    header('Location: ' . app_url('login.php'));
    exit;
}

$return = app_url('');

if (!empty($_SESSION['KITEL_RENTAL_RETURN_URL'])) {
    $return = $_SESSION['KITEL_RENTAL_RETURN_URL'];
} elseif (!empty($_COOKIE['KITEL_RENTAL_RETURN_URL'])) {
    $return = $_COOKIE['KITEL_RENTAL_RETURN_URL'];
}

unset($_SESSION['KITEL_RENTAL_RETURN_URL']);

setcookie(
    'KITEL_RENTAL_RETURN_URL',
    '',
    time() - 3600,
    '/',
    '',
    !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    true
);

if ($return === '' || $return[0] !== '/') {
    $return = app_url('');
}

header('Location: ' . $return);
exit;