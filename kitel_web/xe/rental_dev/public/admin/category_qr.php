<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$category = find_category($state, $categoryId);
if (!$category) {
    http_response_code(404);
    exit('카테고리를 찾을 수 없습니다.');
}
render_header('카테고리 QR', true);
admin_nav();
?>
<h1>카테고리 QR 보기</h1>
<section class="card qr-label">
  <strong><?php echo e($category['name']); ?></strong>
  <?php echo qr_svg(canonical_url('category.php?id=' . $categoryId), 5); ?>
  <p class="muted">개수 관리 카테고리 대여 페이지로 연결됩니다.</p>
  <p><?php echo e(canonical_url('category.php?id=' . $categoryId)); ?></p>
  <p class="no-print"><a class="button" href="<?php echo e(app_url('admin/categories.php')); ?>">← 카테고리 관리로</a></p>
  <button class="no-print" onclick="window.print()">인쇄</button>
</section>
<?php render_footer(); ?>
