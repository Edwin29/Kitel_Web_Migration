<?php
$appDir = getenv('KITEL_RENTAL_APP_DIR');
$candidates = array();
if ($appDir) {
    $candidates[] = rtrim($appDir, '/\\');
}
$candidates[] = __DIR__ . '/../app';
$candidates[] = __DIR__ . '/_app';
$candidates[] = dirname(__DIR__) . '/app';

$resolvedAppDir = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate . '/bootstrap.php')) {
        $resolvedAppDir = $candidate;
        break;
    }
}

if ($resolvedAppDir === null) {
    http_response_code(500);
    exit('KITEL rental app path is not configured.');
}

require_once $resolvedAppDir . '/bootstrap.php';
require_once $resolvedAppDir . '/layout.php';
