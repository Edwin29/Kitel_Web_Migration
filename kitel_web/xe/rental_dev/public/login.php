<?php
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/_bootstrap.php';

$return = isset($_GET['return']) ? $_GET['return'] : app_url('');
$loginFailed = isset($_GET['fail']) && $_GET['fail'] === '1';

if ($return === '' || $return[0] !== '/') {
    $return = app_url('');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['KITEL_RENTAL_RETURN_URL'] = $return;

setcookie(
    'KITEL_RENTAL_RETURN_URL',
    $return,
    0,
    '/',
    '',
    !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    true
);

$callback = rtrim(config('canonical_base_url'), '/') . '/login_return.php';
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>KITEL 기자재 대여 로그인</title>
  <link rel="stylesheet" href="<?php echo e(app_url('assets/style.css')); ?>">
</head>
<body>
  <main class="container">
    <section class="card login-card">
      <h1>로그인</h1>
      <p class="muted login-help">KITEL 홈페이지 계정으로 로그인합니다.</p>
      
      <?php if ($loginFailed): ?>
    	<div class="alert error">
		    아이디 또는 비밀번호가 올바르지 않거나, 로그인이 완료되지 않았습니다.
		</div>
	  <?php endif; ?>

      <form method="post" action="/index.php?act=procMemberLogin" class="login-form">
        <input type="hidden" name="act" value="procMemberLogin">
        <input type="hidden" name="success_return_url" value="<?php echo e($callback); ?>">
        <input type="hidden" name="return_url" value="<?php echo e($callback); ?>">
        <input type="hidden" name="error_return_url" value="<?php echo e(app_url('login.php?fail=1&return=' . rawurlencode($return))); ?>">
        <input type="hidden" name="mid" value="mainpage">
        <input type="hidden" name="vid" value="">
        <input type="hidden" name="ruleset" value="@login">

        <div class="form-row">
          <label for="user_id">아이디</label>
          <input type="text" name="user_id" id="user_id" required>
        </div>

        <div class="form-row">
          <label for="password">비밀번호</label>
          <input type="password" name="password" id="password" required>
        </div>

        <div class="form-row checkbox-row">
          <label class="checkbox-inline">
            <input type="checkbox" name="keep_signed" value="Y">
            <span>로그인 유지</span>
          </label>
        </div>

        <div class="form-actions">
          <button type="submit" class="button login-button">로그인</button>
        </div>
      </form>

      <p class="muted login-note">
        로그인 후 기자재 페이지로 돌아갑니다.
      </p>
    </section>
  </main>
</body>
</html>