<?php
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/_bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 대여 시스템 복귀 정보 정리
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

$loginUrl = app_url('login.php?return=' . rawurlencode(app_url('')));
$xeLogoutUrl = '/index.php?act=dispMemberLogout';
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>로그아웃</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.location.href = <?php echo json_encode($loginUrl); ?>;
      }, 1000);
    });
  </script>
</head>
<body style="font-family: sans-serif; background:#eef3f9; margin:0;">
  <main style="max-width:420px; margin:80px auto; background:white; padding:32px; border-radius:16px;">
    <h1>로그아웃 중입니다</h1>
    <p>잠시 후 로그인 화면으로 이동합니다.</p>
  </main>

  <iframe
    src="<?php echo e($xeLogoutUrl); ?>"
    style="width:0;height:0;border:0;position:absolute;left:-9999px;"
    aria-hidden="true"
  ></iframe>
</body>
</html>