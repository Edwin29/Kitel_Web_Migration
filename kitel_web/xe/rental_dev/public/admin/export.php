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

$state = rental_load();
$exports = export_rows($state, $type);
if (!$exports) {
    http_response_code(404);
    exit('지원하지 않는 내보내기 유형입니다.');
}
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $exports['filename'] . '-' . date('Ymd') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, $exports['header']);
foreach ($exports['rows'] as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
