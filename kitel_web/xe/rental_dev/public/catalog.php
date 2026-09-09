<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$items = active_items($state);
$bulkCategories = array_values(array_filter(active_categories($state), function ($category) {
    return category_tracking_mode($category) === 'bulk';
}));
render_header('기자재 현황');
?>
<h1>기자재 현황</h1>
<p class="muted">동방에 가지 않고도 지금 대여 가능한 기자재를 확인할 수 있어요. 실제로 빌리려면 동방 와이파이에서 QR을 스캔해야 합니다.</p>
<form class="card actions no-print" method="get">
  <input name="q" value="<?php echo e($query); ?>" placeholder="라벨, 카테고리, 위치 검색">
  <button type="submit">검색</button>
</form>

<?php if ($bulkCategories): ?>
<section class="card table-wrap">
  <h2>개수 관리 기자재</h2>
  <table>
    <thead><tr><th>이름</th><th>대여 가능</th><th>총 개수</th></tr></thead>
    <tbody>
    <?php foreach ($bulkCategories as $category):
      if ($query !== '' && stripos($category['name'], $query) === false) continue;
      $counts = category_item_counts($state, $category['category_id']);
    ?>
      <tr>
        <td><?php echo e($category['name']); ?></td>
        <td><span class="badge <?php echo $counts['available'] > 0 ? 'available' : 'unavailable'; ?>"><?php echo (int)$counts['available']; ?>개 가능</span></td>
        <td><?php echo (int)$counts['total']; ?>개</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<section class="card table-wrap">
  <h2>개별 관리 기자재</h2>
  <table>
    <thead><tr><th>라벨</th><th>카테고리</th><th>상태</th><th>보관 위치</th></tr></thead>
    <tbody>
    <?php foreach ($items as $item):
      $category = item_category($state, $item);
      if ($category && category_tracking_mode($category) === 'bulk') continue;
      $haystack = $item['label'] . ' ' . $item['location'] . ' ' . ($category ? $category['name'] : '');
      if ($query !== '' && stripos($haystack, $query) === false) continue;
    ?>
      <tr>
        <td><?php echo e($item['label']); ?></td>
        <td><?php echo e($category ? $category['name'] : '-'); ?></td>
        <td><span class="badge <?php echo e($item['status']); ?>"><?php echo e(status_label($item['status'])); ?></span></td>
        <td><?php echo e($item['location']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<p><a class="button" href="<?php echo e(app_url('')); ?>">← 홈으로</a></p>
<?php render_footer(); ?>
