<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$item = find_item($state, $itemId);

if (!$item) {
    http_response_code(404);
    exit('기자재를 찾을 수 없습니다.');
}

$category = item_category($state, $item);
$activeLoan = active_loan_for_item($state, $itemId);

render_header('기자재 상세', true);
admin_nav();
?>
<h1>기자재 상세</h1>
<section class="card">
  <h2><?php echo e($item['label']); ?></h2>
  <p class="muted"><?php echo e($item['public_code']); ?></p>
  <div class="two">
    <div>
      <p><strong>카테고리</strong><br><?php echo e($category ? $category['name'] : '-'); ?></p>
      <p><strong>현재 상태</strong><br><span class="badge <?php echo e($item['status']); ?>"><?php echo e(status_label($item['status'])); ?></span></p>
      <p><strong>보관 위치</strong><br><?php echo e($item['location'] !== '' ? $item['location'] : '-'); ?></p>
    </div>
    <div>
      <p><strong>특이사항</strong><br><?php echo $item['condition_note'] !== '' ? nl2br(e($item['condition_note'])) : '-'; ?></p>
      <p><strong>관리자 메모</strong><br><?php echo $item['admin_memo'] !== '' ? nl2br(e($item['admin_memo'])) : '-'; ?></p>
      <?php if ($activeLoan): ?>
        <p><strong>현재 대여 번호</strong><br><?php echo e($activeLoan['loan_id']); ?></p>
      <?php endif; ?>
    </div>
  </div>
  <p class="actions no-print">
    <a class="button" href="<?php echo e(app_url('admin/items.php')); ?>">목록</a>
    <a class="button" href="<?php echo e(app_url('admin/item_history.php?item_id=' . $item['item_id'])); ?>">이력</a>
    <a class="button" href="<?php echo e(app_url('admin/item_edit.php?item_id=' . $item['item_id'])); ?>">수정</a>
    <a class="button" href="<?php echo e(app_url('admin/qr.php?item_id=' . $item['item_id'])); ?>">QR</a>
  </p>
</section>
<?php render_footer(); ?>
