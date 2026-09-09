<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$item = find_item($state, isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0);
if (!$item) {
    http_response_code(404);
    exit('기자재를 찾을 수 없습니다.');
}
render_header('QR 보기', true);
admin_nav();
?>
<h1>QR 보기</h1>
<section class="card qr-label">
  <strong><?php echo e($item['label']); ?></strong>
  <?php echo qr_svg(canonical_url('item.php?code=' . $item['public_code']), 5); ?>
  <p class="muted"><?php echo e($item['public_code']); ?></p>
  <p><?php echo e(canonical_url('item.php?code=' . $item['public_code'])); ?></p>
  <button class="no-print" onclick="window.print()">인쇄</button>
</section>
<?php render_footer(); ?>
