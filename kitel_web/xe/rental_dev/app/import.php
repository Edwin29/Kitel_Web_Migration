<?php
// CSV 가져오기 — 해석 / 계획 수립 / 적용
//
// 예전 구현의 문제 두 가지를 여기서 해결한다.
//
// 1. 시트를 갱신해 다시 넣으면 "동기화"가 아니라 "중복 추가"가 됐다.
//    동방 물품현황표의 "현재 수량"은 목표 상태를 뜻하는데, 시스템은 그 숫자만큼
//    새로 만들었다. 니퍼 8개가 16개가 되고, 되돌리려면 8개를 일일이 폐기해야 했다.
//    → 수량 CSV에 "추가(add)" / "맞추기(sync)" 두 모드를 두고, 무엇을 하려는지
//      가져오기 전에 미리보기로 보여준다.
//
// 2. 내보내기 형식과 가져오기 형식이 달라 왕복이 불가능했고, CSV로 수정·삭제를
//    할 수 없었다.
//    → 내보내기와 같은 열(item_id 포함)을 그대로 다시 받아 upsert 한다.
//
// 적용은 항상 CSV 원문에서 계획을 다시 세운 뒤 수행한다. 미리보기 화면이 돌려준
// 계획을 그대로 믿지 않는다(폼을 조작해 다른 항목을 폐기시키는 것을 막기 위함).

define('IMPORT_SHAPE_ITEMS', 'items');
define('IMPORT_SHAPE_QUANTITY', 'quantity');

// ---------------------------------------------------------------------------
// 해석
// ---------------------------------------------------------------------------

// CSV 텍스트를 헤더와 행 배열로 나눈다.
function import_read_csv($csvText)
{
    $lines = preg_split('/\r\n|\r|\n/', trim((string)$csvText));
    $rows = array();
    foreach ($lines as $index => $line) {
        if (trim($line) === '') {
            continue;
        }
        $cols = str_getcsv($line);
        if ($cols && isset($cols[0])) {
            $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cols[0]);
        }
        $rows[] = array('line' => $index + 1, 'cols' => $cols);
    }
    return $rows;
}

// 헤더 행을 보고 어느 형식인지 판단한다.
//   - item_id 열이 있으면  → 개체 단위(내보내기 왕복용)
//   - category + quantity  → 수량 단위(동방 물품현황표용)
function import_detect_shape(array $header)
{
    $normalized = array_map(function ($h) { return strtolower(trim((string)$h)); }, $header);
    if (in_array('item_id', $normalized, true)) {
        return IMPORT_SHAPE_ITEMS;
    }
    if (in_array('category', $normalized, true) && in_array('quantity', $normalized, true)) {
        return IMPORT_SHAPE_QUANTITY;
    }
    return null;
}

function import_column_map(array $header)
{
    $map = array();
    foreach ($header as $i => $name) {
        $key = strtolower(trim((string)$name));
        if ($key !== '' && !isset($map[$key])) {
            $map[$key] = $i;
        }
    }
    return $map;
}

function import_cell(array $cols, array $map, $key, $default = '')
{
    if (!isset($map[$key]) || !isset($cols[$map[$key]])) {
        return $default;
    }
    return trim((string)$cols[$map[$key]]);
}

// 예전 함수명 유지 — 수량 형식만 해석하는 단순 파서.
// item_new.php 의 엑셀 드래그앤드롭이 만들어내는 형식이 이것이다.
function parse_items_csv($csvText)
{
    $rows = import_read_csv($csvText);
    if (!$rows) {
        return array('rows' => array(), 'errors' => array());
    }
    $header = $rows[0]['cols'];
    $shape = import_detect_shape($header);
    $out = array();
    $errors = array();
    $start = ($shape === null) ? 0 : 1;
    $map = ($shape === null)
        ? array('category' => 0, 'quantity' => 1, 'location' => 2, 'condition_note' => 3)
        : import_column_map($header);

    for ($i = $start; $i < count($rows); $i++) {
        $cols = $rows[$i]['cols'];
        $line = $rows[$i]['line'];
        $name = import_cell($cols, $map, 'category');
        if ($name === '') {
            $errors[] = $line . '행: 카테고리명이 비어 있습니다.';
            continue;
        }
        $qty = (int)import_cell($cols, $map, 'quantity', '1');
        if ($qty < 1) {
            $errors[] = $line . '행: 수량이 1 이상이어야 합니다 (' . $name . ').';
            continue;
        }
        $out[] = array(
            'line' => $line,
            'category' => $name,
            'quantity' => $qty,
            'location' => import_cell($cols, $map, 'location'),
            'note' => import_cell($cols, $map, 'condition_note'),
        );
    }
    return array('rows' => $out, 'errors' => $errors);
}

// ---------------------------------------------------------------------------
// 계획 수립 (미리보기)
// ---------------------------------------------------------------------------

function import_empty_plan($shape, $mode)
{
    return array(
        'shape' => $shape,
        'mode' => $mode,
        'errors' => array(),
        'new_categories' => array(),
        'creates' => array(),
        'updates' => array(),
        'retires' => array(),
        'blocked' => array(),
        'unchanged' => 0,
        'total_new_items' => 0,
    );
}

// CSV와 현재 상태를 비교해 "무엇이 일어날지"를 계산한다. 아무것도 바꾸지 않는다.
//   $mode: 'add'  — 수량만큼 새로 추가 (예전 동작)
//          'sync' — 수량을 목표 총 개수로 보고 현재와 맞춤 (부족하면 추가, 많으면 폐기)
//   $allowRetire: sync 모드에서 폐기를 실제 계획에 넣을지. 꺼져 있으면 폐기 후보만 보여준다.
function import_build_plan($state, $csvText, $mode = 'add', $allowRetire = false)
{
    $rows = import_read_csv($csvText);
    if (!$rows) {
        $plan = import_empty_plan(null, $mode);
        $plan['errors'][] = '내용이 비어 있습니다.';
        return $plan;
    }

    $shape = import_detect_shape($rows[0]['cols']);
    if ($shape === null) {
        $plan = import_empty_plan(null, $mode);
        $plan['errors'][] = '첫 줄에서 열 이름을 찾지 못했습니다. '
            . '"category,quantity,location,condition_note" 또는 기자재 CSV 내보내기 형식(item_id 포함)을 사용해 주세요.';
        return $plan;
    }

    return $shape === IMPORT_SHAPE_ITEMS
        ? import_plan_items($state, $rows, $mode, $allowRetire)
        : import_plan_quantity($state, $rows, $mode, $allowRetire);
}

// 개체 단위 CSV(내보내기 왕복). item_id 로 기존 기자재를 찾아 위치/특이사항을 갱신하고,
// item_id 가 비어 있으면 새로 만든다.
function import_plan_items($state, array $rows, $mode, $allowRetire)
{
    $plan = import_empty_plan(IMPORT_SHAPE_ITEMS, $mode);
    $map = import_column_map($rows[0]['cols']);
    $seenIds = array();

    for ($i = 1; $i < count($rows); $i++) {
        $cols = $rows[$i]['cols'];
        $line = $rows[$i]['line'];
        $rawId = import_cell($cols, $map, 'item_id');
        $categoryName = import_cell($cols, $map, 'category');
        $location = import_cell($cols, $map, 'location');
        $note = import_cell($cols, $map, 'condition_note');

        // item_id 가 비어 있으면 신규
        if ($rawId === '') {
            if ($categoryName === '') {
                $plan['errors'][] = $line . '행: 새 기자재인데 카테고리명이 비어 있습니다.';
                continue;
            }
            $plan['creates'][] = array(
                'line' => $line, 'category' => $categoryName, 'quantity' => 1,
                'location' => $location, 'note' => $note,
            );
            $plan['total_new_items']++;
            if (!find_category_by_name($state, $categoryName) && !in_array($categoryName, $plan['new_categories'], true)) {
                $plan['new_categories'][] = $categoryName;
            }
            continue;
        }

        if (!ctype_digit($rawId)) {
            $plan['errors'][] = $line . '행: item_id 가 숫자가 아닙니다 (' . $rawId . ').';
            continue;
        }
        $itemId = (int)$rawId;
        $item = find_item($state, $itemId);
        if (!$item) {
            $plan['errors'][] = $line . '행: item_id ' . $itemId . ' 인 기자재를 찾을 수 없습니다.';
            continue;
        }
        $seenIds[$itemId] = true;

        $changes = array();
        if ($location !== (string)$item['location']) {
            $changes['location'] = array((string)$item['location'], $location);
        }
        if ($note !== (string)$item['condition_note']) {
            $changes['condition_note'] = array((string)$item['condition_note'], $note);
        }

        // 카테고리/상태는 CSV로 바꾸지 않는다. 값이 다르면 무시된다는 사실을 알려준다.
        $ignored = array();
        $category = item_category($state, $item);
        if ($categoryName !== '' && $category && $categoryName !== $category['name']) {
            $ignored[] = '카테고리(' . $category['name'] . ' → ' . $categoryName . ')';
        }
        $statusCell = import_cell($cols, $map, 'status');
        if ($statusCell !== '' && $statusCell !== status_label($item['status'])) {
            $ignored[] = '상태(' . status_label($item['status']) . ' → ' . $statusCell . ')';
        }

        if ($changes || $ignored) {
            $plan['updates'][] = array(
                'line' => $line, 'item_id' => $itemId, 'label' => $item['label'],
                'changes' => $changes, 'ignored' => $ignored,
            );
        } else {
            $plan['unchanged']++;
        }
    }

    // sync 모드: CSV에 없는 기존 기자재는 폐기 후보
    if ($mode === 'sync') {
        foreach ($state['items'] as $item) {
            if ((int)$item['is_active'] !== 1 || isset($seenIds[(int)$item['item_id']])) {
                continue;
            }
            $entry = array('item_id' => (int)$item['item_id'], 'label' => $item['label'], 'reason' => 'CSV에 없음');
            if ($item['status'] === 'borrowed') {
                $entry['reason'] = '대여 중이라 폐기할 수 없음';
                $plan['blocked'][] = $entry;
            } else {
                $plan['retires'][] = $entry;
            }
        }
        if (!$allowRetire) {
            $plan['retires_withheld'] = $plan['retires'];
            $plan['retires'] = array();
        }
    }

    return $plan;
}

// 수량 단위 CSV(동방 물품현황표). 카테고리별 개수를 다룬다.
function import_plan_quantity($state, array $rows, $mode, $allowRetire)
{
    $plan = import_empty_plan(IMPORT_SHAPE_QUANTITY, $mode);
    $map = import_column_map($rows[0]['cols']);

    for ($i = 1; $i < count($rows); $i++) {
        $cols = $rows[$i]['cols'];
        $line = $rows[$i]['line'];
        $name = import_cell($cols, $map, 'category');
        if ($name === '') {
            $plan['errors'][] = $line . '행: 카테고리명이 비어 있습니다.';
            continue;
        }
        $quantity = (int)import_cell($cols, $map, 'quantity', '0');
        if ($quantity < 0) {
            $plan['errors'][] = $line . '행: 수량은 0 이상이어야 합니다 (' . $name . ').';
            continue;
        }
        $location = import_cell($cols, $map, 'location');
        $note = import_cell($cols, $map, 'condition_note');

        $category = find_category_by_name($state, $name);
        $current = $category ? category_status_counts($state, $category['category_id']) : null;
        $currentTotal = $current ? (int)$current['total'] : 0;

        if (!$category && !in_array($name, $plan['new_categories'], true)) {
            $plan['new_categories'][] = $name;
        }

        if ($mode === 'add') {
            if ($quantity < 1) {
                $plan['errors'][] = $line . '행: 추가 모드에서는 수량이 1 이상이어야 합니다 (' . $name . ').';
                continue;
            }
            $plan['creates'][] = array(
                'line' => $line, 'category' => $name, 'quantity' => $quantity,
                'location' => $location, 'note' => $note,
                'current' => $currentTotal, 'after' => $currentTotal + $quantity,
            );
            $plan['total_new_items'] += $quantity;
            continue;
        }

        // sync: quantity 를 "이 카테고리가 가져야 할 총 개수"로 본다
        $delta = $quantity - $currentTotal;
        if ($delta > 0) {
            $plan['creates'][] = array(
                'line' => $line, 'category' => $name, 'quantity' => $delta,
                'location' => $location, 'note' => $note,
                'current' => $currentTotal, 'after' => $quantity,
            );
            $plan['total_new_items'] += $delta;
        } elseif ($delta < 0) {
            $need = -$delta;
            // 대여 중인 것은 폐기 대상이 아니다. 사용 가능한 것부터 고른다.
            $retirable = array();
            foreach (array('available', 'unavailable', 'broken', 'lost') as $status) {
                foreach ($state['items'] as $item) {
                    if (count($retirable) >= $need) {
                        break 2;
                    }
                    if ((int)$item['category_id'] === (int)$category['category_id']
                        && (int)$item['is_active'] === 1
                        && $item['status'] === $status) {
                        $retirable[] = array('item_id' => (int)$item['item_id'], 'label' => $item['label'], 'reason' => $name . ' ' . $currentTotal . '개 → ' . $quantity . '개');
                    }
                }
            }
            if (count($retirable) < $need) {
                $plan['blocked'][] = array(
                    'item_id' => null,
                    'label' => $name,
                    'reason' => ($need - count($retirable)) . '개는 대여 중이라 줄일 수 없음',
                );
            }
            foreach ($retirable as $entry) {
                $plan['retires'][] = $entry;
            }
        } else {
            $plan['unchanged']++;
        }
    }

    if ($mode === 'sync' && !$allowRetire) {
        $plan['retires_withheld'] = $plan['retires'];
        $plan['retires'] = array();
    }

    return $plan;
}

function import_plan_is_noop($plan)
{
    return !$plan['creates'] && !$plan['updates'] && !$plan['retires'];
}

// ---------------------------------------------------------------------------
// 적용
// ---------------------------------------------------------------------------

// 계획을 실제로 반영한다. 전체가 하나의 트랜잭션이라 중간에 실패하면 아무것도 남지 않는다.
function import_apply_plan(&$state, $plan, $actor)
{
    $run = function () use (&$state, $plan, $actor) {
        $created = 0;
        $createdIds = array();
        $updated = 0;
        $retired = 0;

        foreach ($plan['creates'] as $create) {
            $category = find_category_by_name($state, $create['category']);
            if (!$category) {
                $category = create_category($state, $create['category'], 'CSV 가져오기', $actor);
                if (config('mode') !== 'local') {
                    $state['categories'][] = $category;
                }
            }
            $items = add_items_to_category($state, $category['category_id'], $create['quantity'], $create['location'], $create['note'], $actor);
            foreach ($items as $item) {
                // 로컬 모드의 add_items_to_category()는 이미 $state['items']에 넣어준다.
                // 운영 모드는 넣지 않으므로 여기서 채워야 이어지는 계획 항목이 최신 상태를 본다.
                if (config('mode') !== 'local') {
                    $state['items'][] = $item;
                }
                $createdIds[] = (int)$item['item_id'];
                $created++;
            }
        }

        foreach ($plan['updates'] as $update) {
            if (!$update['changes']) {
                continue;
            }
            $item = find_item($state, $update['item_id']);
            if (!$item) {
                continue;
            }
            $location = isset($update['changes']['location']) ? $update['changes']['location'][1] : $item['location'];
            $note = isset($update['changes']['condition_note']) ? $update['changes']['condition_note'][1] : $item['condition_note'];
            update_item_details($state, $update['item_id'], $location, $note, $item['admin_memo'], $actor);
            $updated++;
        }

        foreach ($plan['retires'] as $retire) {
            retire_item($state, $retire['item_id'], 'CSV 가져오기: ' . $retire['reason'], $actor);
            $retired++;
        }

        return array($created, $createdIds, $updated, $retired);
    };

    try {
        if (config('mode') !== 'local') {
            list($created, $createdIds, $updated, $retired) = db_transaction(function () use ($run) {
                return $run();
            });
        } else {
            list($created, $createdIds, $updated, $retired) = $run();
        }
    } catch (Exception $e) {
        // 트랜잭션이 통째로 되돌아갔으므로 $state 도 신뢰할 수 없다.
        $state = rental_load();
        $message = $e->getMessage();
        if (stripos($message, 'uniq_category_slug') !== false || stripos($message, '1062') !== false) {
            $message = '이미 있는 카테고리와 이름이 겹칩니다. 카테고리명을 확인해 주세요. (' . $message . ')';
        }
        return array('ok' => false, 'created' => 0, 'created_ids' => array(), 'updated' => 0, 'retired' => 0, 'message' => $message);
    }

    return array(
        'ok' => true,
        'created' => $created,
        'created_ids' => $createdIds,
        'updated' => $updated,
        'retired' => $retired,
        'message' => '',
    );
}

// 예전 진입점 유지 — 미리보기 없이 "추가" 모드로 바로 적용한다.
function import_items_from_csv(&$state, $csvText, $actor)
{
    $plan = import_build_plan($state, $csvText, 'add', false);
    if ($plan['errors'] && import_plan_is_noop($plan)) {
        return array('created' => 0, 'created_ids' => array(), 'errors' => $plan['errors']);
    }
    $result = import_apply_plan($state, $plan, $actor);
    $errors = $plan['errors'];
    if (!$result['ok']) {
        $errors[] = '가져오기를 취소했습니다 — ' . $result['message'];
        return array('created' => 0, 'created_ids' => array(), 'errors' => $errors);
    }
    return array('created' => $result['created'], 'created_ids' => $result['created_ids'], 'errors' => $errors);
}
