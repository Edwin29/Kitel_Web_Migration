<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();

function posted_category_ids()
{
    return (isset($_POST['category_ids']) && is_array($_POST['category_ids']))
        ? array_values(array_filter(array_map('intval', $_POST['category_ids'])))
        : array();
}

if (is_post()) {
    require_post();
    try {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'create_bundle') {
            create_bundle($state, isset($_POST['name']) ? $_POST['name'] : '', '', $user);
            flash('묶음을 만들었습니다.');
        } elseif ($action === 'rename_bundle') {
            update_bundle_name($state, isset($_POST['bundle_id']) ? (int)$_POST['bundle_id'] : 0, isset($_POST['name']) ? $_POST['name'] : '', $user);
            flash('묶음명을 변경했습니다.');
        } elseif ($action === 'delete_bundle') {
            delete_bundle($state, isset($_POST['bundle_id']) ? (int)$_POST['bundle_id'] : 0, $user);
            flash('묶음을 삭제했습니다. 포함된 카테고리는 미분류로 이동했습니다.');
        } elseif ($action === 'create_category') {
            $category = create_category($state, isset($_POST['name']) ? $_POST['name'] : '', '', $user);
            $bundleId = isset($_POST['bundle_id']) ? (int)$_POST['bundle_id'] : 0;
            if (config('mode') !== 'local') {
                $state = rental_load();
            }
            if ($bundleId > 0 && isset($category['category_id'])) {
                move_category_to_bundle($state, (int)$category['category_id'], $bundleId, $user);
            }
            flash('카테고리를 만들었습니다.');
        } elseif ($action === 'rename_category') {
            update_category_name($state, isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0, isset($_POST['name']) ? $_POST['name'] : '', $user);
            flash('카테고리명을 변경했습니다.');
        } elseif ($action === 'move_category') {
            move_category_to_bundle(
                $state,
                isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0,
                isset($_POST['bundle_id']) ? (int)$_POST['bundle_id'] : 0,
                $user
            );
            flash('카테고리를 이동했습니다.');
        } elseif ($action === 'bulk_move') {
            $count = move_categories_to_bundle($state, posted_category_ids(), isset($_POST['bundle_id']) ? (int)$_POST['bundle_id'] : 0, $user);
            flash($count . '개 카테고리를 이동했습니다.');
        } elseif ($action === 'bulk_tracking') {
            $ids = posted_category_ids();
            if (!$ids) {
                throw new RuntimeException('선택된 카테고리가 없습니다.');
            }
            // 입력한 항목만 변경한다. 비워 둔 칸은 건드리지 않는다.
            $changes = array();
            $mode = isset($_POST['tracking_mode']) ? $_POST['tracking_mode'] : '';
            if ($mode === 'unique' || $mode === 'bulk') {
                $changes['tracking_mode'] = $mode;
            }
            list($hasLimit, $limit) = parse_optional_number_change(isset($_POST['max_per_user']) ? $_POST['max_per_user'] : '');
            if ($hasLimit) {
                $changes['max_per_user'] = $limit;
            }
            list($hasDue, $dueDays) = parse_optional_number_change(isset($_POST['due_days']) ? $_POST['due_days'] : '');
            if ($hasDue) {
                $changes['due_days'] = $dueDays;
            }
            if (!$changes) {
                throw new RuntimeException('변경할 항목을 하나 이상 입력해 주세요.');
            }
            $count = bulk_update_category_options($state, $ids, $changes, $user);
            flash($count . '개 카테고리의 관리 옵션을 변경했습니다.');
        } elseif ($action === 'bulk_delete') {
            $ids = posted_category_ids();
            if (!$ids) {
                throw new RuntimeException('선택된 카테고리가 없습니다.');
            }
            $count = bulk_delete_categories($state, $ids, $user);
            flash($count . '개 카테고리를 삭제했습니다.');
        }
        rental_save($state);
    } catch (RuntimeException $e) {
        flash($e->getMessage());
    }
    redirect_to('admin/categories.php');
}

$bundles = active_bundles($state);
$unassigned = unassigned_categories($state);

function render_category_node($state, $category)
{
    $counts = category_item_counts($state, $category['category_id']);
    $mode = category_tracking_mode($category);
    ?>
    <li class="explorer-item category-node" draggable="true" data-category-id="<?php echo (int)$category['category_id']; ?>">
      <input type="checkbox" class="category-select" value="<?php echo (int)$category['category_id']; ?>" aria-label="<?php echo e($category['name']); ?> 선택">
      <?php // 저장 버튼이 없으면 실수로 글자를 건드리고 Enter 만 눌러도 즉시 반영된다.
            // 값이 실제로 바뀌었을 때만 버튼이 나타나고, 제출 전에 한 번 확인한다.
            // 카테고리명을 바꾸면 그 카테고리 기자재의 라벨도 함께 다시 만들어진다. ?>
      <form method="post" class="inline-rename category-rename" data-original="<?php echo e($category['name']); ?>">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="rename_category">
        <input type="hidden" name="category_id" value="<?php echo (int)$category['category_id']; ?>">
        <input name="name" value="<?php echo e($category['name']); ?>" aria-label="카테고리명" autocomplete="off">
        <button type="submit" class="rename-save" hidden>이름 저장</button>
        <button type="button" class="rename-cancel" hidden>취소</button>
      </form>
      <span class="badge <?php echo $mode === 'bulk' ? 'available' : ''; ?>"><?php echo $mode === 'bulk' ? '개수' : '개별'; ?></span>
      <span class="muted count-text"><?php echo (int)$counts['total']; ?>개</span>
      <?php $dueDays = category_due_days($category); $limit = category_max_per_user($category); ?>
      <span class="muted count-text" title="대여 기간">
        <?php if ($dueDays > 0): ?>
          <strong><?php echo $dueDays; ?>일</strong>
        <?php else: ?>
          기본 <?php echo (int)config('default_due_days'); ?>일
        <?php endif; ?>
      </span>
      <?php if ($limit > 0): ?>
        <span class="muted count-text" title="1인당 대여 제한">1인 <?php echo $limit; ?>개</span>
      <?php endif; ?>
    </li>
    <?php
}

render_header('카테고리 관리', true);
admin_nav();
?>
<div class="page-head">
  <h1>카테고리 관리</h1>
  <div class="page-head-actions">
    <a class="button" href="<?php echo e(app_url('admin/items.php')); ?>">기자재 관리</a>
  </div>
</div>

<section class="card bulk-bar category-bulk-bar no-print">
  <form id="category-move-form" class="bulk-inline" method="post">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="bulk_move">
    <select name="bundle_id">
      <option value="0">미분류로 이동</option>
      <?php foreach ($bundles as $bundle): ?>
        <option value="<?php echo (int)$bundle['bundle_id']; ?>"><?php echo e($bundle['name']); ?>로 이동</option>
      <?php endforeach; ?>
    </select>
    <button type="submit">선택 항목 이동</button>
  </form>
  <?php // 비워 둔 칸은 건드리지 않는다. 0을 넣으면 그 설정을 해제한다.
        // 예전에는 관리 방식이 항상 함께 덮어써져서, 1인당 제한만 바꾸려 해도
        // 개별/개수 관리가 같이 바뀌었다. ?>
  <form id="category-tracking-form" class="bulk-inline" method="post">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="bulk_tracking">
    <select name="tracking_mode" title="관리 방식">
      <option value="">관리 방식: 변경 안 함</option>
      <option value="unique">개별 관리로</option>
      <option value="bulk">개수 관리로</option>
    </select>
    <input class="qty-input" type="number" name="max_per_user" min="0" placeholder="1인당 제한"
           title="1인당 대여 제한(개). 비우면 변경 안 함, 0이면 제한 해제">
    <input class="qty-input" type="number" name="due_days" min="0" placeholder="대여 기간(일)"
           title="대여 기간(일). 비우면 변경 안 함, 0이면 기본값(<?php echo (int)config('default_due_days'); ?>일) 사용">
    <button type="submit">선택 항목에 적용</button>
    <span class="muted bulk-hint">비운 칸은 그대로 두고, <strong>0</strong>을 넣으면 해제됩니다</span>
  </form>
  <form id="category-delete-form" class="bulk-inline" method="post" onsubmit="return confirm('선택한 카테고리를 영구 삭제할까요? 대여 중인 기자재가 있으면 삭제되지 않습니다.');">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="bulk_delete">
    <button class="danger" type="submit">선택 항목 삭제</button>
  </form>
</section>

<section class="card explorer-create no-print">
  <form method="post" class="bulk-inline">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="create_bundle">
    <input name="name" placeholder="새 묶음명" required>
    <button type="submit">묶음 생성</button>
  </form>
  <form method="post" class="bulk-inline">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="create_category">
    <input name="name" placeholder="새 카테고리명" required>
    <select name="bundle_id">
      <option value="0">미분류</option>
      <?php foreach ($bundles as $bundle): ?>
        <option value="<?php echo (int)$bundle['bundle_id']; ?>"><?php echo e($bundle['name']); ?></option>
      <?php endforeach; ?>
    </select>
    <button class="primary" type="submit">카테고리 생성</button>
  </form>
</section>

<section class="card explorer-card">
  <div class="explorer-toolbar">
    <span class="bulk-label muted" id="category-selected-count">선택된 카테고리 없음</span>
    <label class="inline"><input type="checkbox" id="select-all-categories"> 전체 선택</label>
  </div>
  <div class="explorer-tree">
    <details class="explorer-folder" open data-bundle-id="0">
      <summary>
        <span class="folder-caret">▾</span>
        <input type="checkbox" class="bundle-select" aria-label="미분류 전체 선택">
        <strong>미분류</strong>
        <span class="muted"><?php echo count($unassigned); ?>개</span>
      </summary>
      <ul class="explorer-list">
        <?php foreach ($unassigned as $category): ?>
          <?php render_category_node($state, $category); ?>
        <?php endforeach; ?>
      </ul>
    </details>

    <?php foreach ($bundles as $bundle): $categories = bundle_categories($state, $bundle['bundle_id']); ?>
      <details class="explorer-folder" open data-bundle-id="<?php echo (int)$bundle['bundle_id']; ?>">
        <summary>
          <span class="folder-caret">▾</span>
          <input type="checkbox" class="bundle-select" aria-label="<?php echo e($bundle['name']); ?> 전체 선택">
          <form method="post" class="inline-rename bundle-rename">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="rename_bundle">
            <input type="hidden" name="bundle_id" value="<?php echo (int)$bundle['bundle_id']; ?>">
            <input name="name" value="<?php echo e($bundle['name']); ?>" aria-label="묶음명">
          </form>
          <span class="muted"><?php echo count($categories); ?>개</span>
          <a class="button compact-button no-print" href="<?php echo e(app_url('admin/bundle_qr.php?bundle_id=' . (int)$bundle['bundle_id'])); ?>">QR</a>
          <form method="post" class="inline-form no-print" onsubmit="return confirm('묶음을 삭제할까요? 포함된 카테고리는 미분류로 이동합니다.');">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="delete_bundle">
            <input type="hidden" name="bundle_id" value="<?php echo (int)$bundle['bundle_id']; ?>">
            <button class="danger compact-button" type="submit">삭제</button>
          </form>
        </summary>
        <ul class="explorer-list">
          <?php foreach ($categories as $category): ?>
            <?php render_category_node($state, $category); ?>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<script>
(function () {
  var csrfToken = <?php echo json_encode(csrf_token()); ?>;
  var selectedLabel = document.getElementById('category-selected-count');
  var selectAll = document.getElementById('select-all-categories');
  var actionForms = [
    document.getElementById('category-move-form'),
    document.getElementById('category-tracking-form'),
    document.getElementById('category-delete-form')
  ];

  function checkedBoxes() {
    return Array.prototype.slice.call(document.querySelectorAll('.category-select:checked'));
  }

  function updateSelectedLabel() {
    var checked = checkedBoxes();
    var all = Array.prototype.slice.call(document.querySelectorAll('.category-select'));
    var count = checked.length;
    selectedLabel.textContent = count > 0 ? count + '개 카테고리 선택됨' : '선택된 카테고리 없음';
    [selectAll].forEach(function (box) {
      if (!box) { return; }
      box.checked = all.length > 0 && count === all.length;
      box.indeterminate = count > 0 && count < all.length;
    });
    document.querySelectorAll('.bundle-select').forEach(function (box) {
      var folder = box.closest('.explorer-folder');
      var children = Array.prototype.slice.call(folder.querySelectorAll('.category-select'));
      var childChecked = children.filter(function (child) { return child.checked; }).length;
      box.checked = children.length > 0 && childChecked === children.length;
      box.indeterminate = childChecked > 0 && childChecked < children.length;
    });
  }

  document.querySelectorAll('.category-select').forEach(function (box) {
    box.addEventListener('change', updateSelectedLabel);
  });

  function setAllCategories(checked) {
    document.querySelectorAll('.category-select').forEach(function (box) {
      box.checked = checked;
    });
    updateSelectedLabel();
  }

  [selectAll].forEach(function (box) {
    if (!box) { return; }
    box.addEventListener('change', function () {
      setAllCategories(box.checked);
    });
  });

  document.querySelectorAll('.bundle-select').forEach(function (box) {
    box.addEventListener('click', function (event) {
      event.stopPropagation();
    });
    box.addEventListener('change', function () {
      var folder = box.closest('.explorer-folder');
      folder.querySelectorAll('.category-select').forEach(function (child) {
        child.checked = box.checked;
      });
      updateSelectedLabel();
    });
  });

  actionForms.forEach(function (form) {
    if (!form) { return; }
    form.addEventListener('submit', function (event) {
      var selected = checkedBoxes();
      if (selected.length === 0) {
        event.preventDefault();
        alert('먼저 카테고리를 선택해 주세요.');
        return;
      }
      form.querySelectorAll('input[name="category_ids[]"]').forEach(function (input) { input.remove(); });
      selected.forEach(function (box) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'category_ids[]';
        hidden.value = box.value;
        form.appendChild(hidden);
      });
    });
  });

  document.querySelectorAll('.inline-rename input[name="name"]').forEach(function (input) {
    var original = input.value;
    input.addEventListener('focus', function () {
      original = input.value;
      input.select();
    });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        input.form.submit();
      }
      if (event.key === 'Escape') {
        input.value = original;
        input.blur();
      }
    });
    input.addEventListener('blur', function () {
      if (input.value !== original && input.value.trim() !== '') {
        input.form.submit();
      }
    });
  });

  document.querySelectorAll('.category-node').forEach(function (node) {
    node.addEventListener('dragstart', function (event) {
      event.dataTransfer.setData('text/plain', node.getAttribute('data-category-id'));
      event.dataTransfer.effectAllowed = 'move';
    });
    node.addEventListener('dragend', function () {
      stopAutoScroll();
    });
  });

  var autoScrollTimer = null;
  var autoScrollDirection = 0;

  function stopAutoScroll() {
    if (autoScrollTimer) {
      window.clearInterval(autoScrollTimer);
      autoScrollTimer = null;
    }
    autoScrollDirection = 0;
  }

  function startAutoScroll(direction) {
    if (autoScrollDirection === direction && autoScrollTimer) {
      return;
    }
    stopAutoScroll();
    autoScrollDirection = direction;
    autoScrollTimer = window.setInterval(function () {
      window.scrollBy(0, direction * 18);
    }, 16);
  }

  document.addEventListener('dragover', function (event) {
    var edge = 90;
    if (event.clientY < edge) {
      startAutoScroll(-1);
    } else if (window.innerHeight - event.clientY < edge) {
      startAutoScroll(1);
    } else {
      stopAutoScroll();
    }
  });

  document.addEventListener('drop', stopAutoScroll);

  document.querySelectorAll('.explorer-folder').forEach(function (folder) {
    folder.addEventListener('dragover', function (event) {
      event.preventDefault();
      folder.classList.add('drag-over');
    });
    folder.addEventListener('dragleave', function () {
      folder.classList.remove('drag-over');
    });
    folder.addEventListener('drop', function (event) {
      event.preventDefault();
      folder.classList.remove('drag-over');
      var categoryId = event.dataTransfer.getData('text/plain');
      var bundleId = folder.getAttribute('data-bundle-id') || '0';
      if (!categoryId) { return; }
      var form = document.createElement('form');
      form.method = 'post';
      form.style.display = 'none';
      [
        ['csrf_token', csrfToken],
        ['action', 'move_category'],
        ['category_id', categoryId],
        ['bundle_id', bundleId]
      ].forEach(function (pair) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = pair[0];
        input.value = pair[1];
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
    });
  });

  updateSelectedLabel();

  // 카테고리명 인라인 수정: 값이 실제로 바뀐 폼에만 저장/취소 버튼을 띄우고,
  // 제출 전에 확인을 받는다. Enter 만으로 조용히 반영되던 동작을 막는다.
  document.querySelectorAll('form.category-rename').forEach(function (form) {
    var input = form.querySelector('input[name="name"]');
    var save = form.querySelector('.rename-save');
    var cancel = form.querySelector('.rename-cancel');
    if (!input || !save || !cancel) { return; }
    var original = form.getAttribute('data-original') || '';

    function sync() {
      var changed = input.value !== original;
      save.hidden = !changed;
      cancel.hidden = !changed;
      form.classList.toggle('is-dirty', changed);
    }

    input.addEventListener('input', sync);
    cancel.addEventListener('click', function () {
      input.value = original;
      sync();
    });
    form.addEventListener('submit', function (e) {
      var next = input.value.trim();
      if (next === '' || next === original) {
        e.preventDefault();
        input.value = original;
        sync();
        return;
      }
      if (!confirm('카테고리명을 "' + original + '" → "' + next + '" 으로 바꿉니다.\n\n이 카테고리에 속한 기자재의 라벨도 모두 새 이름으로 다시 만들어집니다. 계속할까요?')) {
        e.preventDefault();
      }
    });
    sync();
  });
})();
</script>
<?php render_footer(); ?>
