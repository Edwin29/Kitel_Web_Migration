<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();
$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$bundle = find_bundle_by_code($state, $code);
if (!$bundle) {
    http_response_code(404);
    exit('묶음을 찾을 수 없습니다.');
}
$categories = bundle_categories($state, $bundle['bundle_id']);
render_header($bundle['name']);
?>
<h1><?php echo e($bundle['name']); ?></h1>
<section class="card">
  <p class="muted">카메라 스캔 화면에서는 이 묶음 안의 카테고리를 선택해 대여/반납 목록에 담을 수 있습니다.</p>
  <p>
    <a class="button primary" href="<?php echo e(app_url('scan.php?mode=rent')); ?>">대여 스캔</a>
    <a class="button" href="<?php echo e(app_url('scan.php?mode=return')); ?>">반납 스캔</a>
  </p>
</section>
<section class="card table-wrap">
  <table>
    <thead><tr><th>카테고리</th><th>관리 방식</th><th>현재개수</th><th>대여 가능</th><th>대여 중</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $category): $counts = category_item_counts($state, $category['category_id']); $mode = category_tracking_mode($category); ?>
      <tr>
        <td><?php echo e($category['name']); ?></td>
        <td><?php echo $mode === 'bulk' ? '개수 관리' : '개별 관리'; ?></td>
        <td><?php echo (int)$counts['total']; ?>개</td>
        <td><?php echo (int)$counts['available']; ?>개</td>
        <td><?php echo (int)$counts['borrowed']; ?>개</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
