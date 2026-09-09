<?php
function render_header($title, $admin = false)
{
    $user = current_user();
    $flash = flash();
    ?><!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($title); ?> | KITEL 기자재 대여</title>
  <link rel="stylesheet" href="<?php echo e(app_url('assets/style.css')); ?>">
</head>
<body class="<?php echo $admin ? 'admin-page' : ''; ?>">
<div class="shell">
	<header class="top">
	  <a class="brand" href="<?php echo e(app_url('')); ?>">KITEL 기자재 대여</a>
	  <nav>
	    <?php if ($user && user_is_admin($user)): ?>
	      <a href="<?php echo e(app_url('admin/')); ?>">관리자</a>
	    <?php endif; ?>
	  </nav>
	  <span class="user">
	    <?php if ($user): ?>
	      <?php echo e($user['nick_name']); ?>
	      <a href="<?php echo e(xe_logout_url()); ?>">로그아웃</a>
	    <?php else: ?>
	      비로그인
	    <?php endif; ?>
	  </span>
	</header>
  <?php if ($flash): ?><div class="flash"><?php echo e($flash); ?></div><?php endif; ?>
  <main>
<?php
}

function render_footer()
{
    if (_admin_layout_is_open()) {
        echo '</div></div>';
        _admin_layout_is_open(false);
    }
    ?></main>
</div>
</body>
</html><?php
}

// 목록 화면 하단의 페이지 이동. $result는 loan_query()/log_query()가 돌려주는 형태.
// 현재 쿼리스트링(필터)을 유지한 채 page만 갈아끼운다.
function render_pagination($result, $path, $param = 'page')
{
    if ($result['pages'] <= 1) {
        echo '<p class="muted pager-summary">전체 ' . (int)$result['total'] . '건</p>';
        return;
    }
    $page = (int)$result['page'];
    $pages = (int)$result['pages'];
    $window = 2;
    $start = max(1, $page - $window);
    $end = min($pages, $page + $window);
    ?>
    <nav class="pager no-print" aria-label="페이지 이동">
      <span class="muted pager-summary">전체 <?php echo (int)$result['total']; ?>건 · <?php echo $page; ?>/<?php echo $pages; ?> 페이지</span>
      <span class="pager-links">
        <?php if ($page > 1): ?>
          <a class="button" href="<?php echo e(rental_query_url($path, array($param => 1))); ?>">« 처음</a>
          <a class="button" href="<?php echo e(rental_query_url($path, array($param => $page - 1))); ?>">‹ 이전</a>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <?php if ($i === $page): ?>
            <strong class="pager-current" aria-current="page"><?php echo $i; ?></strong>
          <?php else: ?>
            <a class="button" href="<?php echo e(rental_query_url($path, array($param => $i))); ?>"><?php echo $i; ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <a class="button" href="<?php echo e(rental_query_url($path, array($param => $page + 1))); ?>">다음 ›</a>
          <a class="button" href="<?php echo e(rental_query_url($path, array($param => $pages))); ?>">마지막 »</a>
        <?php endif; ?>
      </span>
    </nav>
    <?php
}

// admin_nav()가 사이드바 래퍼를 열었는지 기억해서 render_footer()에서 자동으로 닫아주기 위한 내부 상태.
// 이렇게 하면 admin 하위 화면 파일들을 개별 수정하지 않아도 된다.
function _admin_layout_is_open($set = null)
{
    static $open = false;
    if ($set !== null) {
        $open = $set;
    }
    return $open;
}

function admin_nav_groups()
{
    return array(
        '개요' => array(
            array('index.php', '대시보드'),
        ),
        '기자재' => array(
            array('items.php', '기자재 관리'),
        ),
        '대여' => array(
            array('loans.php', '대여 현황'),
            array('member_history.php', '사용자 기록'),
        ),
        '기록' => array(
            array('logs.php', '처리 로그'),
        ),
    );
}

// 자주 쓰지 않는 설정성 화면은 구분선 아래 톤 다운된 섹션으로 분리한다.
function admin_nav_settings_groups()
{
    return array(
        '설정' => array(
            array('permissions.php', '권한 그룹'),
            array('network_check.php', '네트워크 점검'),
        ),
    );
}

function admin_nav()
{
    $current = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
    _admin_layout_is_open(true);
    ?>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <?php foreach (admin_nav_groups() as $label => $links): ?>
      <div class="admin-nav-group">
        <p class="admin-nav-label"><?php echo e($label); ?></p>
        <?php foreach ($links as $link): $file = $link[0]; $text = $link[1]; $isActive = $current === $file; ?>
          <a class="admin-nav-link<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo e(app_url('admin/' . $file)); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo e($text); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <hr class="admin-nav-divider">
    <?php foreach (admin_nav_settings_groups() as $label => $links): ?>
      <div class="admin-nav-group admin-nav-group-settings">
        <p class="admin-nav-label"><?php echo e($label); ?></p>
        <?php foreach ($links as $link): $file = $link[0]; $text = $link[1]; $isActive = $current === $file; ?>
          <a class="admin-nav-link<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo e(app_url('admin/' . $file)); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo e($text); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </aside>
  <div class="admin-content">
<?php
}
