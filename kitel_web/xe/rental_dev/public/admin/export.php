<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($type === '') {
    // 예전에는 여기서 5개 카드로 된 별도 "CSV 내보내기" 허브 화면을 보여줬지만,
    // 지금은 기자재/대여 현황/로그 각 화면에 그 화면에 맞는 CSV 버튼이 붙어 있으므로
    // type 없이 직접 들어오면 기자재 관리로 안내한다.
    redirect_to('admin/items.php');
}

// 화면에서 건 필터를 그대로 물려받는다 (각 화면의 CSV 버튼이 현재 쿼리스트링을
// 그대로 넘긴다). 예전에는 type만 받아서, 로그를 한 달치로 걸러놓고 CSV를 눌러도
// 전체 로그가 내려왔다.
if ($type === 'logs') {
    $filters = log_filters_from_request();
} elseif ($type === 'items') {
    $filters = item_filters_from_request();
} else {
    $filters = loan_filters_from_request();
}

list($type, $filters) = export_normalize_type($type, $filters);

$definition = export_definition($type);
if (!$definition) {
    http_response_code(404);
    exit('지원하지 않는 내보내기 유형입니다.');
}

$state = rental_load();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $definition['filename'] . '-' . date('Ymd') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, $definition['header']);

// 행을 만드는 즉시 흘려보낸다 — 전체를 메모리에 쌓지 않는다.
export_each_row($state, $type, $filters, function ($row) use ($out) {
    fputcsv($out, $row);
});

fclose($out);
exit;
