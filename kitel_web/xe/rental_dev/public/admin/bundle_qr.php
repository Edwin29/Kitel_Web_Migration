<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$bundleId = isset($_GET['bundle_id']) ? (int)$_GET['bundle_id'] : 0;
$bundle = find_bundle($state, $bundleId);
if (!$bundle) {
    http_response_code(404);
    exit('묶음을 찾을 수 없습니다.');
}
$url = canonical_url('bundle.php?code=' . rawurlencode($bundle['public_code']));
render_header('묶음 QR', true);
admin_nav();
?>
<h1>묶음 QR 보기</h1>
<section class="card qr-label">
  <strong><?php echo e($bundle['name']); ?></strong>
  <?php echo qr_svg($url, 5); ?>
  <p class="muted">묶음에 포함된 카테고리 선택 화면으로 연결됩니다.</p>
  <p><?php echo e($url); ?></p>
  <p class="no-print"><a class="button" href="<?php echo e(app_url('admin/categories.php')); ?>">← 카테고리 관리로</a></p>
  <button class="no-print" onclick="window.print()">인쇄</button>
</section>
<?php render_footer(); ?>
