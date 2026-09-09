<?php
/**
 * 운영 DB → local_data.json 변환 (읽기 전용).
 * NAS 에서 php74 로 실행. 운영 데이터를 로컬 모드 시드로 바꾼다.
 * 쓰기 작업 없음. 결과는 인자로 받은 경로(또는 /tmp/local_data.json)에 저장.
 */
putenv('KITEL_RENTAL_MODE=production');
require_once '/volume1/kitel_web/xe/rental_dev/app/bootstrap.php';

$out = isset($argv[1]) ? $argv[1] : '/tmp/local_data.json';
$LOG_CAP = 500;                 // 로그는 최근 500건만 (파일 크기·로드 속도)
$FAKE_SRL = 4;                  // config fake_user 의 member_srl
$FAKE_NICK = '정보부장';

$pdo = db_connect();

$categories = $pdo->query('SELECT * FROM kitel_rental_categories ORDER BY category_id')->fetchAll(PDO::FETCH_ASSOC);
$items      = $pdo->query('SELECT * FROM kitel_rental_items ORDER BY item_id')->fetchAll(PDO::FETCH_ASSOC);
$bundles    = $pdo->query('SELECT * FROM kitel_rental_bundles ORDER BY bundle_id')->fetchAll(PDO::FETCH_ASSOC);
$bundleCats = $pdo->query('SELECT * FROM kitel_rental_bundle_categories')->fetchAll(PDO::FETCH_ASSOC);
$groups     = $pdo->query('SELECT * FROM kitel_rental_allowed_groups ORDER BY permission_type, group_srl')->fetchAll(PDO::FETCH_ASSOC);
$loans      = $pdo->query('SELECT * FROM kitel_rental_loans ORDER BY loan_id')->fetchAll(PDO::FETCH_ASSOC);
$logs       = $pdo->query('SELECT * FROM kitel_rental_logs ORDER BY log_id DESC LIMIT ' . (int)$LOG_CAP)->fetchAll(PDO::FETCH_ASSOC);
$logs = array_reverse($logs);  // 다시 오름차순으로

// 숫자 컬럼 int 캐스팅 (JSON 왕복 후 타입 일관성)
$intify = function (&$rows, $cols) {
    foreach ($rows as &$r) {
        foreach ($cols as $c) {
            if (array_key_exists($c, $r) && $r[$c] !== null) $r[$c] = (int)$r[$c];
        }
    }
    unset($r);
};
$intify($categories, ['category_id','next_serial','max_per_user','due_days','is_active','created_by_member_srl']);
$intify($items, ['item_id','category_id','serial_no','display_no','is_active','created_by_member_srl']);
$intify($bundles, ['bundle_id','sort_order','is_active','created_by_member_srl']);
$intify($bundleCats, ['bundle_id','category_id','sort_order']);
$intify($groups, ['id','group_srl','active']);
$intify($loans, ['loan_id','item_id','borrower_member_srl','created_by_member_srl','returned_by_member_srl','related_loan_id']);
$intify($logs, ['log_id','item_id','loan_id','actor_member_srl']);

$maxId = function ($rows, $key) {
    $m = 0;
    foreach ($rows as $r) if (isset($r[$key])) $m = max($m, (int)$r[$key]);
    return $m;
};

// ── 데모가 처음부터 살아있게 보이도록 합성 대여 몇 건 추가 ──
// 운영 스냅샷은 현재 '대여 중' 0건이라 대시보드/내대여/연체가 전부 비어 보인다.
$nextLoanId = $maxId($loans, 'loan_id') + 1;
$nextLogId  = $maxId($logs, 'log_id') + 1;
$byId = array();
foreach ($items as $i => $it) $byId[$it['item_id']] = $i;

$dueDaysFor = function ($catId) use ($categories) {
    foreach ($categories as $c) {
        if ($c['category_id'] === $catId) {
            return ($c['due_days'] > 0) ? $c['due_days'] : 7;
        }
    }
    return 7;
};

$synthPlan = array(
    // [며칠 전 대여, 연체 여부]
    array(2, false), array(5, false), array(9, true),  // 9일 전 + 7일 기간 → 연체
);
$picked = 0;
foreach ($items as $idx => $it) {
    if ($picked >= count($synthPlan)) break;
    if ($it['status'] !== 'available' || $it['is_active'] !== 1) continue;
    // 개수 관리 카테고리는 건너뛰고 개별 관리 물품만
    $isBulk = false;
    foreach ($categories as $c) if ($c['category_id'] === $it['category_id'] && $c['tracking_mode'] === 'bulk') $isBulk = true;
    if ($isBulk) continue;

    list($ago, ) = $synthPlan[$picked];
    $borrowedAt = date('Y-m-d H:i:s', strtotime("-{$ago} days"));
    $due = $dueDaysFor($it['category_id']);
    $dueAt = date('Y-m-d', strtotime($borrowedAt . " +{$due} days")) . ' 23:59:59';

    $loans[] = array(
        'loan_id' => $nextLoanId,
        'item_id' => $it['item_id'],
        'borrower_member_srl' => $FAKE_SRL,
        'borrower_user_id_snapshot' => 'admin',
        'borrower_name_snapshot' => $FAKE_NICK,
        'actual_user_name' => '',
        'actual_user_contact' => '',
        'borrowed_at' => $borrowedAt,
        'due_at' => $dueAt,
        'returned_at' => null,
        'status' => 'borrowed',
        'created_by_member_srl' => $FAKE_SRL,
        'returned_by_member_srl' => null,
        'return_type' => null,
        'admin_note' => '',
        'related_loan_id' => null,
        'created_at' => $borrowedAt,
        'updated_at' => $borrowedAt,
    );
    $logs[] = array(
        'log_id' => $nextLogId++,
        'item_id' => $it['item_id'],
        'loan_id' => $nextLoanId,
        'actor_member_srl' => $FAKE_SRL,
        'action' => 'loan.create',
        'before_status' => 'available',
        'after_status' => 'borrowed',
        'memo' => '',
        'created_at' => $borrowedAt,
    );
    $items[$idx]['status'] = 'borrowed';
    $items[$idx]['updated_at'] = $borrowedAt;
    $nextLoanId++;
    $picked++;
}

$state = array(
    'next_ids' => array(
        'category_id' => $maxId($categories, 'category_id') + 1,
        'item_id'     => $maxId($items, 'item_id') + 1,
        'loan_id'     => $maxId($loans, 'loan_id') + 1,
        'log_id'      => $maxId($logs, 'log_id') + 1,
        'bundle_id'   => $maxId($bundles, 'bundle_id') + 1,
    ),
    'categories' => $categories,
    'bundles' => $bundles,
    'bundle_categories' => $bundleCats,
    'items' => $items,
    'allowed_groups' => $groups,
    'loans' => $loans,
    'logs' => $logs,
);

file_put_contents($out, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("wrote %s  (%.0f KB)\n", $out, filesize($out) / 1024);
printf("  categories=%d items=%d bundles=%d bundle_cats=%d groups=%d loans=%d logs=%d\n",
    count($categories), count($items), count($bundles), count($bundleCats), count($groups), count($loans), count($logs));
printf("  합성 대여 %d건 추가 (그중 1건 연체), 현재 '대여 중' 물품 %d개\n",
    $picked, count(array_filter($items, function ($i) { return $i['status'] === 'borrowed'; })));
