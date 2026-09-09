<?php
// CLI 도구(tools/check.php 등)는 세션이 필요 없고, 이미 출력을 시작한 뒤에
// session_start()를 부르면 "headers already sent" 경고가 난다.
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/bundles.php';
require_once __DIR__ . '/items.php';
require_once __DIR__ . '/loans.php';
require_once __DIR__ . '/export.php';
