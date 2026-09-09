<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
if (is_post()) {
    require_post();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $memo = isset($_POST['memo']) ? $_POST['memo'] : '';
    try {
        if ($action === 'status') {
            $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            update_item_status($state, $itemId, isset($_POST['status']) ? $_POST['status'] : '', $memo, $user);
            flash('처리되었습니다.');
        } elseif ($action === 'retire') {
            $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            retire_item($state, $itemId, $memo, $user);
            flash('처리되었습니다.');
        } elseif ($action === 'bulk_status' || $action === 'bulk_retire') {
            $itemIds = (isset($_POST['item_ids']) && is_array($_POST['item_ids'])) ? $_POST['item_ids'] : array();
            if (!$itemIds) {
                flash('선택된 기자재가 없습니다.');
            } elseif ($action === 'bulk_status') {
                $result = bulk_update_item_status($state, $itemIds, isset($_POST['status']) ? $_POST['status'] : '', $memo, $user);
                $message = $result['updated'] . '개 기자재의 상태를 변경했습니다.';
                if ($result['skipped'] > 0) {
                    $message .= ' (대여 중이거나 존재하지 않아 ' . $result['skipped'] . '개 건너뜀)';
                }
                flash($message);
            } else {
                $result = bulk_retire_items($state, $itemIds, $memo, $user);
                $message = $result['retired'] . '개 기자재를 폐기 처리했습니다.';
                if ($result['skipped'] > 0) {
                    $message .= ' (대여 중이거나 존재하지 않아 ' . $result['skipped'] . '개 건너뜀)';
                }
                flash($message);
            }
        } elseif ($action === 'category_status' || $action === 'category_retire') {
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
            if ($quantity <= 0) {
                flash('수량을 입력해 주세요.');
            } elseif ($action === 'category_status') {
                $result = bulk_adjust_category_status($state, $categoryId, $quantity, isset($_POST['status']) ? $_POST['status'] : '', $memo, $user);
                $message = $result['updated'] . '개 상태를 변경했습니다.';
                if ($result['updated'] < $result['requested']) {
                    $message .= ' (재고 부족으로 ' . ($result['requested'] - $result['updated']) . '개는 처리하지 못함)';
                }
                flash($message);
            } else {
                $result = bulk_retire_in_category($state, $categoryId, $quantity, $memo, $user);
                $message = $result['retired'] . '개를 폐기 처리했습니다.';
                if ($result['retired'] < $result['requested']) {
                    $message .= ' (재고 부족으로 ' . ($result['requested'] - $result['retired']) . '개는 처리하지 못함)';
                }
                flash($message);
            }
        }
        rental_save($state);
    } catch (RuntimeException $e) {
        flash($e->getMessage());
    }
    redirect_to('admin/items.php');
}
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$categoryFilter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$items = active_items($state);
$bulkCategoryRows = array_values(array_filter(active_categories($state), function ($category) use ($categoryFilter, $query) {
    if (category_tracking_mode($category) !== 'bulk') {
        return false;
    }
    if ($categoryFilter > 0 && (int)$category['category_id'] !== $categoryFilter) {
        return false;
    }
    if ($query !== '' && stripos($category['name'], $query) === false) {
        return false;
    }
    return true;
}));
render_header('기자재 관리', true);
admin_nav();
?>
<div class="page-head">
  <h1>기자재 관리</h1>
  <div class="page-head-actions">
    <a class="button primary" href="<?php echo e(app_url('admin/item_new.php')); ?>">+ 물품 추가</a>
    <a class="button no-print" href="<?php echo e(app_url('admin/export.php?type=items')); ?>">CSV 내보내기</a>
  </div>
</div>
<section class="card bulk-bar no-print">
  <form id="bulk-status-form" class="bulk-inline" method="post">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="bulk_status">
    <select name="status">
      <option value="available">대여 가능</option>
      <option value="unavailable">대여 불가</option>
      <option value="broken">고장</option>
      <option value="lost">분실</option>
    </select>
    <input name="memo" placeholder="상태 변경 사유" required>
    <button type="submit">선택 항목 상태 변경</button>
  </form>
  <form id="bulk-retire-form" class="bulk-inline" method="post">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="bulk_retire">
    <input name="memo" placeholder="폐기 사유" required>
    <button class="danger" type="submit">선택 항목 폐기</button>
  </form>
  <form id="qr-bulk-form" class="bulk-inline" method="get" action="<?php echo e(app_url('admin/qr_print.php')); ?>">
    <button type="submit">선택 항목 QR 인쇄</button>
  </form>
</section>
<form class="card actions no-print" method="get">
  <input name="q" value="<?php echo e($query); ?>" placeholder="라벨, 위치, 코드 검색">
  <select name="status">
    <option value="">전체 상태</option>
    <?php foreach (array('available','borrowed','unavailable','broken','lost','retired') as $s): ?>
      <option value="<?php echo e($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo e(status_label($s)); ?></option>
    <?php endforeach; ?>
  </select>
  <select name="category_id">
    <option value="0">전체 카테고리</option>
    <?php foreach (active_categories($state) as $category): ?>
      <option value="<?php echo e($category['category_id']); ?>" <?php echo $categoryFilter === (int)$category['category_id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">필터</button>
  <a class="muted" href="<?php echo e(app_url('admin/categories.php')); ?>">카테고리 관리</a>
</form>
<?php if ($bulkCategoryRows): ?>
<section class="card table-wrap">
  <h2>개수 관리 카테고리</h2>
  <p class="muted">라벨을 붙일 수 없는 부품은 개별 항목 대신 개수로만 관리합니다.</p>
  <table class="bulk-category-table"><thead><tr><th>이름</th><th>총 개수</th><th>가용</th><th>대여 중</th><th>관리</th></tr></thead><tbody>
  <?php foreach ($bulkCategoryRows as $category): $counts = category_item_counts($state, $category['category_id']); $maxQty = max(1, $counts['available']); ?>
    <tr>
      <td><strong><?php echo e($category['name']); ?></strong></td>
      <td><?php echo (int)$counts['total']; ?></td>
      <td><?php echo (int)$counts['available']; ?></td>
      <td><?php echo (int)$counts['borrowed']; ?></td>
      <td class="actions">
        <a class="button" href="<?php echo e(app_url('admin/item_new.php?category_id=' . $category['category_id'])); ?>">재고 추가</a>
        <a class="button" href="<?php echo e(app_url('admin/category_qr.php?category_id=' . $category['category_id'])); ?>">QR</a>
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="category_status">
          <input type="hidden" name="category_id" value="<?php echo e($category['category_id']); ?>">
          <input class="qty-input" type="number" name="quantity" min="1" max="<?php echo $maxQty; ?>" placeholder="개수">
          <select name="status">
            <option value="unavailable">대여 불가</option>
            <option value="broken">고장</option>
            <option value="lost">분실</option>
          </select>
          <input name="memo" placeholder="사유" required>
          <button type="submit">상태 변경</button>
        </form>
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="category_retire">
          <input type="hidden" name="category_id" value="<?php echo e($category['category_id']); ?>">
          <input class="qty-input" type="number" name="quantity" min="1" max="<?php echo $maxQty; ?>" placeholder="개수">
          <input name="memo" placeholder="폐기 사유" required>
          <button class="danger" type="submit">폐기</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</section>
<?php endif; ?>
<section class="card table-wrap">
  <div class="item-list-toolbar no-print">
    <span class="bulk-label muted" id="bulk-selected-count">선택된 기자재 없음</span>
  </div>
  <table><thead><tr><th class="checkbox-col"><input type="checkbox" id="select-all-items" title="전체 선택"></th><th>라벨</th><th>카테고리</th><th class="status-col">상태</th><th>위치</th><th>관리</th></tr></thead><tbody>
  <?php foreach ($items as $item):
    $category = item_category($state, $item);
    $haystack = $item['label'] . ' ' . $item['location'] . ' ' . $item['public_code'];
    if ($category && category_tracking_mode($category) === 'bulk') continue;
    if ($query !== '' && stripos($haystack, $query) === false) continue;
    if ($status !== '' && $item['status'] !== $status) continue;
    if ($categoryFilter > 0 && (int)$item['category_id'] !== $categoryFilter) continue;
  ?>
    <tr>
      <td class="checkbox-col"><input type="checkbox" class="item-select" value="<?php echo e($item['item_id']); ?>"></td>
      <td><strong><?php echo e($item['label']); ?></strong><br><span class="muted"><?php echo e($item['public_code']); ?></span></td>
      <td><?php echo e($category ? $category['name'] : '-'); ?></td>
      <td class="status-col"><span class="badge <?php echo e($item['status']); ?>"><?php echo e(status_label($item['status'])); ?></span></td>
      <td><?php echo e($item['location']); ?></td>
      <td class="actions">
        <a class="button" href="<?php echo e(app_url('admin/item_detail.php?item_id=' . $item['item_id'])); ?>">상세</a>
        <a class="button" href="<?php echo e(app_url('admin/item_history.php?item_id=' . $item['item_id'])); ?>">이력</a>
        <a class="button" href="<?php echo e(app_url('admin/item_edit.php?item_id=' . $item['item_id'])); ?>">수정</a>
        <a class="button" href="<?php echo e(app_url('admin/qr.php?item_id=' . $item['item_id'])); ?>">QR</a>
        <?php if ($item['status'] !== 'borrowed'): ?>
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
            <select name="status">
              <option value="available">대여 가능</option>
              <option value="unavailable">대여 불가</option>
              <option value="broken">고장</option>
              <option value="lost">분실</option>
            </select>
            <input name="memo" placeholder="상태 변경 사유" required>
            <button type="submit">변경</button>
          </form>
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="retire">
            <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
            <input name="memo" placeholder="폐기 사유" required>
            <button class="danger" type="submit">폐기</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</section>
<script>
(function () {
  var selectAll = document.getElementById('select-all-items');
  var countLabel = document.getElementById('bulk-selected-count');
  var bulkForms = [
    document.getElementById('bulk-status-form'),
    document.getElementById('bulk-retire-form'),
    document.getElementById('qr-bulk-form')
  ];

  function checkedBoxes() {
    return Array.prototype.slice.call(document.querySelectorAll('input.item-select:checked'));
  }

  function updateCountLabel() {
    if (!countLabel) { return; }
    var n = checkedBoxes().length;
    countLabel.textContent = n > 0 ? n + '개 선택됨' : '선택된 기자재 없음';
  }

  document.querySelectorAll('input.item-select').forEach(function (cb) {
    cb.addEventListener('change', updateCountLabel);
  });

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      var checked = this.checked;
      document.querySelectorAll('input.item-select').forEach(function (cb) {
        cb.checked = checked;
      });
      updateCountLabel();
    });
  }

  bulkForms.forEach(function (form) {
    if (!form) { return; }
    form.addEventListener('submit', function (e) {
      var selected = checkedBoxes();
      if (selected.length === 0 && form.id !== 'qr-bulk-form') {
        e.preventDefault();
        alert('먼저 표에서 기자재를 선택해 주세요.');
        return;
      }
      form.querySelectorAll('input[name="item_ids[]"]').forEach(function (el) { el.remove(); });
      selected.forEach(function (cb) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'item_ids[]';
        hidden.value = cb.value;
        form.appendChild(hidden);
      });
    });
  });

  updateCountLabel();
})();
</script>
<?php render_footer(); ?>
