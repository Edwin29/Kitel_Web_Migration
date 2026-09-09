<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();
$code = isset($_GET['code']) ? $_GET['code'] : '';
$item = find_item_by_code($state, $code);
if (!$item) {
    http_response_code(404);
    exit('기자재를 찾을 수 없습니다.');
}
$category = item_category($state, $item);
$activeLoan = active_loan_for_item($state, $item['item_id']);

// QR을 스캔하는 행위 자체가 "목록에 담기"다. 상태에 따라 자동으로
// 대여목록 또는 반납목록 중 하나에 담는다. 누가 빌렸는지는 안 따진다 -
// 준회원이 빌리고 다른 정회원이 반납 처리하는 경우가 있기 때문.
$listType = null;
$added = false;
if ($item['status'] === 'available') {
    $listType = 'rent';
    $added = cart_add_item('rent_list', $item['item_id'], $item['label']);
} elseif ($activeLoan) {
    $listType = 'return';
    $added = cart_add_item('return_list', $item['item_id'], $item['label']);
}

render_header($item['label']);
?>
<h1><?php echo e($item['label']); ?></h1>
<div class="two">
  <section class="card">
    <h2>기자재 정보</h2>
    <p><strong>카테고리</strong><br><?php echo e($category ? $category['name'] : '-'); ?></p>
    <p><strong>현재 상태</strong><br><span class="badge <?php echo e($item['status']); ?>"><?php echo e(status_label($item['status'])); ?></span></p>
    <p><strong>보관 위치</strong><br><?php echo e($item['location']); ?></p>
    <p><strong>대여 기간</strong><br><?php echo (int)effective_due_days($state, $item['category_id']); ?>일 (지금 빌리면 <?php echo e(item_due_date($state, $item)); ?>까지)</p>
    <p><strong>특이사항</strong><br><?php echo nl2br(e($item['condition_note'])); ?></p>
  </section>

  <section class="card">
    <?php if ($listType === 'rent'): ?>
      <h2><?php echo $added ? '대여 목록에 담았습니다' : '이미 대여 목록에 있습니다'; ?></h2>
      <p class="muted">다른 물품도 스캔해서 계속 담거나, 다 담았으면 대여 목록에서 신청을 완료하세요.</p>
      <p><a class="button primary" href="<?php echo e(app_url('rent_list.php')); ?>">대여 목록 보기</a></p>
    <?php elseif ($listType === 'return'): ?>
      <h2><?php echo $added ? '반납 목록에 담았습니다' : '이미 반납 목록에 있습니다'; ?></h2>
      <p class="muted">다른 물품도 스캔해서 계속 담거나, 다 담았으면 반납 목록에서 반납을 확정하세요.</p>
      <p><a class="button primary" href="<?php echo e(app_url('return_list.php')); ?>">반납 목록 보기</a></p>
    <?php else: ?>
      <h2>지금은 담을 수 없음</h2>
      <p class="muted">현재 상태(<?php echo e(status_label($item['status'])); ?>)에서는 대여/반납 목록에 담을 수 없습니다.</p>
    <?php endif; ?>
  </section>
</div>
<?php render_footer(); ?>
