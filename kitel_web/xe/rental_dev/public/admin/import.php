<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$sample = "category,quantity,location,condition_note\n니퍼,3,동방 공구함,새로 구매\n디지털 캘리퍼스,2,계측기 선반,";
if (is_post()) {
    require_post();
    $csv = isset($_POST['csv_text']) ? $_POST['csv_text'] : '';
    // 가져오기 전체가 하나의 트랜잭션이다. 제약 위반 등으로 실패하면 created=0으로
    // 돌아오고 DB에는 아무것도 반영되지 않는다 (예전에는 예외 처리가 없어서
    // 일부 행만 반영된 채 치명적 오류 화면으로 끝났다).
    $result = import_items_from_csv($state, $csv, $user);
    $_SESSION['last_added_item_ids'] = isset($result['created_ids']) ? $result['created_ids'] : array();
    rental_save($state);
    if ($result['created'] > 0) {
        $message = $result['created'] . '개의 기자재를 가져왔습니다.';
        if ($result['errors']) {
            $message .= ' 일부 행은 건너뛰었습니다: ' . implode(' / ', $result['errors']);
        }
    } elseif ($result['errors']) {
        $message = '가져오지 못했습니다: ' . implode(' / ', $result['errors']);
    } else {
        $message = '가져올 행이 없습니다.';
    }
    flash($message);
    redirect_to('admin/item_new.php?tab=csv');
}
render_header('CSV 가져오기', true);
admin_nav();
?>
<h1>CSV 가져오기</h1>
<section class="card">
  <p class="muted">열 순서: category, quantity, location, condition_note</p>
  <form class="form" method="post">
    <?php echo csrf_input(); ?>
    <label>CSV 내용
      <textarea name="csv_text" rows="12" required><?php echo e($sample); ?></textarea>
    </label>
    <button class="primary" type="submit">가져오기</button>
  </form>
</section>
<?php render_footer(); ?>
