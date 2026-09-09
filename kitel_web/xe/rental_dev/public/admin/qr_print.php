<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$categoryFilter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$rawItemIds = isset($_GET['item_ids']) ? $_GET['item_ids'] : '';
if (is_array($rawItemIds)) {
    $selectedIds = array_filter(array_map('intval', $rawItemIds));
    $itemIdsFieldValue = implode(',', $selectedIds);
} else {
    $selectedIds = array_filter(array_map('intval', explode(',', $rawItemIds)));
    $itemIdsFieldValue = $rawItemIds;
}
$recentOnly = isset($_GET['recent']) && $_GET['recent'] === '1';
if ($recentOnly) {
    $selectedIds = isset($_SESSION['last_added_item_ids']) ? array_map('intval', $_SESSION['last_added_item_ids']) : array();
}
// 라벨 한 장마다 서버에서 qrencode 프로세스를 하나씩 띄우므로 매수에 상한을 둔다.
// 예전에는 아무것도 선택하지 않고 들어오면 전체 활성 기자재를 인쇄했다. 기자재가
// 수백 개면 프로세스도 수백 개라 저사양 NAS에서는 그 자체가 자해성 부하였다.
define('QR_PRINT_MAX', 200);

$overLimit = false;
$noSelection = false;

if (!$selectedIds && $categoryFilter <= 0) {
    // 선택도 없고 카테고리 필터도 없으면 전체 인쇄가 아니라 안내를 보여준다.
    $items = array();
    $noSelection = true;
} else {
    $items = array_values(array_filter(active_items($state), function ($item) use ($categoryFilter, $selectedIds) {
        if ($categoryFilter > 0 && (int)$item['category_id'] !== $categoryFilter) {
            return false;
        }
        if ($selectedIds && !in_array((int)$item['item_id'], $selectedIds, true)) {
            return false;
        }
        return true;
    }));
    if (count($items) > QR_PRINT_MAX) {
        $overLimit = count($items);
        $items = array_slice($items, 0, QR_PRINT_MAX);
    }
}
$printCount = max(1, count($items));
$printColumns = 1;
$printRows = $printCount;
$printQrSize = 0.0;
for ($columns = 1; $columns <= $printCount; $columns++) {
    $rows = (int)ceil($printCount / $columns);
    $candidateSize = max(8, min(38, (190 / $columns) - 4, (277 / $rows) - 7));
    if ($candidateSize > $printQrSize || ($candidateSize === $printQrSize && $rows < $printRows)) {
        $printColumns = $columns;
        $printRows = $rows;
        $printQrSize = $candidateSize;
    }
}
render_header('QR 일괄 인쇄', true);
admin_nav();
?>
<div class="qr-print-page">
<div class="page-head">
  <h1>QR 일괄 인쇄</h1>
  <a class="button no-print" href="<?php echo e(app_url('admin/items.php')); ?>">← 기자재 관리로</a>
</div>
<form class="card actions no-print" method="get">
  <select name="category_id">
    <option value="0">전체 카테고리</option>
    <?php foreach (active_categories($state) as $category): ?>
      <option value="<?php echo e($category['category_id']); ?>" <?php echo $categoryFilter === (int)$category['category_id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?></option>
    <?php endforeach; ?>
  </select>
  <input name="item_ids" value="<?php echo e($itemIdsFieldValue); ?>" placeholder="선택 item_id: 1,2,3">
  <label class="inline"><input type="checkbox" name="recent" value="1" <?php echo $recentOnly ? 'checked' : ''; ?>> 방금 추가</label>
  <button type="submit">필터</button>
  <button type="button" onclick="window.print()">인쇄</button>
</form>
<?php if ($overLimit): ?>
  <p class="card no-print">조건에 맞는 기자재가 <?php echo (int)$overLimit; ?>개라 앞의 <?php echo QR_PRINT_MAX; ?>장만 출력합니다. 나머지는 카테고리나 item_id로 나눠서 인쇄해 주세요.</p>
<?php endif; ?>
<section class="qr-grid" style="--qr-columns: <?php echo (int)$printColumns; ?>; --qr-rows: <?php echo (int)$printRows; ?>; --qr-size: <?php echo e(number_format($printQrSize, 2, '.', '')); ?>mm;">
  <?php foreach ($items as $item): ?>
    <article class="qr-label">
      <strong><?php echo e($item['label']); ?></strong>
      <?php echo qr_svg(canonical_url('item.php?code=' . $item['public_code']), 4); ?>
      <span class="muted"><?php echo e($item['public_code']); ?></span>
    </article>
  <?php endforeach; ?>
  <?php if ($noSelection): ?>
    <p class="card no-print">인쇄할 기자재를 먼저 고르세요 — 기자재 관리 화면에서 체크박스로 선택하거나, 위에서 카테고리를 고르거나, item_id를 직접 입력하면 됩니다. (한 번에 최대 <?php echo QR_PRINT_MAX; ?>장)</p>
  <?php elseif (!$items): ?>
    <p class="card">출력할 QR이 없습니다.</p>
  <?php endif; ?>
</section>
</div>
<?php render_footer(); ?>
