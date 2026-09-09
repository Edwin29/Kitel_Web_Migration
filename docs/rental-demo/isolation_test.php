<?php
/**
 * 격리 검증: 데모에서 대여를 실행하고, 운영 DB 가 그대로인지 확인한다.
 * 데모 부분은 로컬 모드로 직접 함수 호출, 운영 부분은 별도 커넥션으로 읽기만.
 */

// ── [1] 운영 DB 기준값 (별도 프로세스에서 읽어 격리) ──
$prodBefore = json_decode(shell_exec(
    '/usr/local/bin/php74 -r ' . escapeshellarg(
        'putenv("KITEL_RENTAL_MODE=production");' .
        'require "/volume1/kitel_web/xe/rental_dev/app/bootstrap.php";' .
        '$p=db_connect();' .
        'echo json_encode(["borrowed"=>(int)$p->query(' . "\"SELECT COUNT(*) FROM kitel_rental_loans WHERE status='borrowed'\"" . ')->fetchColumn(),' .
        '"logs"=>(int)$p->query("SELECT COUNT(*) FROM kitel_rental_logs")->fetchColumn(),' .
        '"items_avail"=>(int)$p->query(' . "\"SELECT COUNT(*) FROM kitel_rental_items WHERE status='available'\"" . ')->fetchColumn()]);'
    )
), true);
echo "운영 DB 기준값: " . json_encode($prodBefore, JSON_UNESCAPED_UNICODE) . "\n";

// ── [2] 데모 인스턴스에서 대여 실행 (로컬 모드) ──
putenv('KITEL_RENTAL_MODE=local');
require '/volume1/kitel_web/xe/rental_demo/app/bootstrap.php';
$actor = config('fake_user');
$state = rental_load();

$demoBorrowedBefore = count(array_filter($state['loans'], function ($l) { return $l['status'] === 'borrowed'; }));
$target = null;
foreach ($state['items'] as $it) {
    if ($it['status'] === 'available' && (int)$it['is_active'] === 1) { $target = $it; break; }
}
echo "\n데모: '{$target['label']}' ({$target['public_code']}) 대여 시도\n";
create_loan($state, $target['item_id'], '격리테스트', '010', item_due_date($state, $target), '', $actor, $actor);
rental_save($state);

$state = rental_load();
$demoBorrowedAfter = count(array_filter($state['loans'], function ($l) { return $l['status'] === 'borrowed'; }));
echo "데모 borrowed: {$demoBorrowedBefore} -> {$demoBorrowedAfter}  " . ($demoBorrowedAfter === $demoBorrowedBefore + 1 ? "(정상: 데모에 반영됨)" : "(이상)") . "\n";
echo "데모 local_data.json 크기: " . number_format(filesize('/volume1/kitel_web/xe/rental_demo/database/local_data.json')) . " bytes\n";

// ── [3] 운영 DB 재확인 ──
$prodAfter = json_decode(shell_exec(
    '/usr/local/bin/php74 -r ' . escapeshellarg(
        'putenv("KITEL_RENTAL_MODE=production");' .
        'require "/volume1/kitel_web/xe/rental_dev/app/bootstrap.php";' .
        '$p=db_connect();' .
        'echo json_encode(["borrowed"=>(int)$p->query(' . "\"SELECT COUNT(*) FROM kitel_rental_loans WHERE status='borrowed'\"" . ')->fetchColumn(),' .
        '"logs"=>(int)$p->query("SELECT COUNT(*) FROM kitel_rental_logs")->fetchColumn(),' .
        '"items_avail"=>(int)$p->query(' . "\"SELECT COUNT(*) FROM kitel_rental_items WHERE status='available'\"" . ')->fetchColumn()]);'
    )
), true);
echo "\n운영 DB 재확인:  " . json_encode($prodAfter, JSON_UNESCAPED_UNICODE) . "\n";

$unchanged = ($prodBefore === $prodAfter);
echo "\n" . ($unchanged
    ? "==> 운영 DB 완전히 그대로. 격리 확인됨."
    : "==> !!! 운영 DB 가 변경됨. 즉시 조사 필요 !!!") . "\n";

// ── [4] 데모 초기 상태로 되돌리기 (테스트가 남긴 대여 제거) ──
copy('/tmp/local_data.json', '/volume1/kitel_web/xe/rental_demo/database/local_data.json');
echo "데모 시드 원상 복구 완료 (테스트가 만든 대여 제거)\n";
