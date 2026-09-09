<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
if (is_post()) {
    require_post();
    try {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $newCategory = isset($_POST['new_category']) ? trim($_POST['new_category']) : '';

        if ($categoryId === 0 && $newCategory !== '') {
            $category = create_category($state, $newCategory, '', $user);
            $categoryId = (int)$category['category_id'];

            // DB 모드에서는 create_category가 현재 $state 배열에는 새 카테고리를 넣지 않는다.
            // 바로 이어서 add_items_to_category를 호출하므로, DB에서 최신 state를 다시 읽어온다.
            if (config('mode') !== 'local') {
                $state = rental_load();
            }
        }

        if ($categoryId <= 0) {
            throw new RuntimeException('카테고리를 선택하거나 새 카테고리명을 입력해 주세요.');
        }

        $quantity = max(1, isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1);
        $createdItems = add_items_to_category(
            $state,
            $categoryId,
            $quantity,
            isset($_POST['location']) ? $_POST['location'] : '',
            isset($_POST['condition_note']) ? $_POST['condition_note'] : '',
            $user
        );
        $_SESSION['last_added_item_ids'] = array_map(function ($item) {
            return (int)$item['item_id'];
        }, $createdItems);
        rental_save($state);
        flash($quantity . '개의 기자재를 추가했습니다.');
        redirect_to('admin/items.php');
    } catch (Exception $e) {
        flash($e->getMessage());
        redirect_to('admin/item_new.php');
    }
}
$tab = (isset($_GET['tab']) && $_GET['tab'] === 'csv') ? 'csv' : 'single';
$preselectedCategoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$csvSample = "category,quantity,location,condition_note\n니퍼,3,동방 공구함,새로 구매\n디지털 캘리퍼스,2,계측기 선반,";
render_header('새 물품 추가', true);
admin_nav();
?>
<h1>새 물품 추가</h1>
<nav class="tabs no-print">
  <a class="tab-link<?php echo $tab === 'single' ? ' active' : ''; ?>" href="<?php echo e(app_url('admin/item_new.php')); ?>">단건/소량 추가</a>
  <a class="tab-link<?php echo $tab === 'csv' ? ' active' : ''; ?>" href="<?php echo e(app_url('admin/item_new.php?tab=csv')); ?>">CSV로 여러 개</a>
</nav>
<?php if ($tab === 'single'): ?>
<section class="card">
  <form class="form" method="post">
    <?php echo csrf_input(); ?>
    <label>기존 카테고리
      <select name="category_id">
        <option value="0">새 카테고리 사용</option>
        <?php foreach (active_categories($state) as $category): ?>
          <option value="<?php echo e($category['category_id']); ?>" <?php echo $preselectedCategoryId === (int)$category['category_id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?><?php echo category_tracking_mode($category) === 'bulk' ? ' (개수 관리)' : ''; ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>새 카테고리명 <input name="new_category" placeholder="기존 카테고리를 쓰면 비워둡니다"></label>
    <label>추가 수량 <input type="number" name="quantity" min="1" value="1" required></label>
    <label>보관 위치 <input name="location"></label>
    <label>특이사항 <textarea name="condition_note" rows="3"></textarea></label>
    <button class="primary" type="submit">자동 라벨로 추가</button>
  </form>
</section>
<?php else: ?>
<section class="card">
  <p class="muted">엑셀(.xlsx) 파일을 끌어다 놓으면 자동으로 표를 읽어 아래 칸을 채워줘요. 동방 물품 현황표 양식(물품명/기존 수량/변동/현재 수량/비고가 반복되는 표)을 그대로 인식합니다.</p>
  <div id="csv-dropzone" class="dropzone" tabindex="0">
    <p class="dropzone-text">여기로 파일을 끌어다 놓거나 <span class="dropzone-browse">클릭해서 선택</span>하세요</p>
    <p class="muted dropzone-hint">.xlsx, .xls, .csv 지원</p>
    <input type="file" id="csv-file-input" accept=".xlsx,.xls,.csv" hidden>
  </div>
  <p id="csv-import-status" class="import-status"></p>
  <form class="form" method="post" action="<?php echo e(app_url('admin/import.php')); ?>">
    <?php echo csrf_input(); ?>
    <label>CSV 내용 (파일을 불러오면 자동으로 채워집니다. 가져오기 전에 한 번 확인해 주세요)
      <textarea name="csv_text" id="csv_text" rows="12" required><?php echo e($csvSample); ?></textarea>
    </label>
    <button class="primary" type="submit">가져오기</button>
  </form>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
(function () {
  var dropzone = document.getElementById('csv-dropzone');
  var fileInput = document.getElementById('csv-file-input');
  var textarea = document.getElementById('csv_text');
  var status = document.getElementById('csv-import-status');
  if (!dropzone || !fileInput || !textarea) { return; }

  function setStatus(message, isError) {
    status.textContent = message;
    status.className = 'import-status' + (isError ? ' error' : ' ok');
  }

  function csvEscape(value) {
    var s = (value === null || value === undefined) ? '' : String(value);
    if (/[",\n\r]/.test(s)) {
      s = '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
  }

  function toNumberOrDefault(value, fallback) {
    var n = parseInt(String(value === null || value === undefined ? '' : value).replace(/[^0-9-]/g, ''), 10);
    return (!isNaN(n) && n > 0) ? n : fallback;
  }

  // 동방 물품 현황표 양식: "물품명 / 기존 수량 / 변동 / 현재 수량 / 비고" 블록이 가로로 반복.
  // 고정 위치가 아니라 헤더 텍스트로 열을 찾기 때문에, 위치 열이 추가되는 등
  // 양식이 조금 바뀌어도 그대로 인식됩니다.
  function tryClubTemplate(rows) {
    var headerRowIndex = -1;
    var headerRow = null;
    for (var r = 0; r < Math.min(rows.length, 10); r++) {
      var row = rows[r] || [];
      if (row.some(function (v) { return String(v || '').trim() === '물품명'; })) {
        headerRowIndex = r;
        headerRow = row;
        break;
      }
    }
    if (headerRowIndex === -1) { return null; }

    var blockStarts = [];
    headerRow.forEach(function (v, c) {
      if (String(v || '').trim() === '물품명') { blockStarts.push(c); }
    });

    var blocks = blockStarts.map(function (start, i) {
      var end = (i + 1 < blockStarts.length) ? blockStarts[i + 1] : headerRow.length;
      var cols = { name: start, qty: null, note: null, location: null };
      for (var c = start; c < end; c++) {
        var label = String(headerRow[c] || '').trim();
        if (label === '현재 수량') { cols.qty = c; }
        else if (label === '비고') { cols.note = c; }
        else if (label === '위치' || label === '보관 위치') { cols.location = c; }
      }
      return cols;
    });

    var items = [];
    for (var r2 = headerRowIndex + 1; r2 < rows.length; r2++) {
      var dataRow = rows[r2] || [];
      blocks.forEach(function (cols) {
        var name = dataRow[cols.name];
        if (name === null || name === undefined || String(name).trim() === '') { return; }
        items.push({
          category: String(name).trim(),
          quantity: toNumberOrDefault(cols.qty !== null ? dataRow[cols.qty] : null, 1),
          location: (cols.location !== null && dataRow[cols.location] != null) ? String(dataRow[cols.location]).trim() : '',
          note: (cols.note !== null && dataRow[cols.note] != null) ? String(dataRow[cols.note]).trim() : '',
        });
      });
    }
    return items.length > 0 ? items : null;
  }

  // 이미 category,quantity,location,condition_note 형태인 일반 표
  function tryGenericTable(rows) {
    if (rows.length === 0) { return null; }
    var header = (rows[0] || []).map(function (v) { return String(v || '').trim().toLowerCase(); });
    var idx = {
      category: header.indexOf('category'),
      quantity: header.indexOf('quantity'),
      location: header.indexOf('location'),
      note: header.indexOf('condition_note'),
    };
    if (idx.category === -1 || idx.quantity === -1) { return null; }
    var items = [];
    for (var r = 1; r < rows.length; r++) {
      var row = rows[r] || [];
      var name = row[idx.category];
      if (name === null || name === undefined || String(name).trim() === '') { continue; }
      items.push({
        category: String(name).trim(),
        quantity: toNumberOrDefault(row[idx.quantity], 1),
        location: (idx.location > -1 && row[idx.location] != null) ? String(row[idx.location]).trim() : '',
        note: (idx.note > -1 && row[idx.note] != null) ? String(row[idx.note]).trim() : '',
      });
    }
    return items.length > 0 ? items : null;
  }

  function itemsToCsv(items) {
    var lines = ['category,quantity,location,condition_note'];
    items.forEach(function (item) {
      lines.push([
        csvEscape(item.category),
        csvEscape(item.quantity),
        csvEscape(item.location),
        csvEscape(item.note),
      ].join(','));
    });
    return lines.join('\n');
  }

  function handleFile(file) {
    if (!file) { return; }
    setStatus(file.name + ' 읽는 중...', false);
    var reader = new FileReader();
    reader.onerror = function () {
      setStatus('파일을 읽지 못했습니다.', true);
    };
    reader.onload = function (e) {
      try {
        var workbook = XLSX.read(e.target.result, { type: 'array' });
        var sheet = workbook.Sheets[workbook.SheetNames[0]];
        var rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: null, blankrows: false });
        var items = tryClubTemplate(rows) || tryGenericTable(rows);
        if (!items) {
          setStatus('표 형식을 인식하지 못했습니다. 열 순서를 확인하거나 직접 입력해 주세요.', true);
          return;
        }
        textarea.value = itemsToCsv(items);
        setStatus(items.length + '개 항목을 인식했습니다. 가져오기 전에 아래 내용을 확인해 주세요.', false);
      } catch (err) {
        setStatus('파일을 분석하지 못했습니다: ' + err.message, true);
      }
    };
    reader.readAsArrayBuffer(file);
  }

  dropzone.addEventListener('click', function () { fileInput.click(); });
  dropzone.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { fileInput.click(); }
  });
  fileInput.addEventListener('change', function () {
    handleFile(fileInput.files[0]);
  });
  ['dragenter', 'dragover'].forEach(function (evt) {
    dropzone.addEventListener(evt, function (e) {
      e.preventDefault();
      dropzone.classList.add('dragover');
    });
  });
  ['dragleave', 'drop'].forEach(function (evt) {
    dropzone.addEventListener(evt, function (e) {
      e.preventDefault();
      dropzone.classList.remove('dragover');
    });
  });
  dropzone.addEventListener('drop', function (e) {
    var file = e.dataTransfer.files && e.dataTransfer.files[0];
    handleFile(file);
  });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
