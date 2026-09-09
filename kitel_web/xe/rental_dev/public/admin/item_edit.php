<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : (isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0);
$item = find_item($state, $itemId);
if (!$item) {
    http_response_code(404);
    exit('기자재를 찾을 수 없습니다.');
}
if (is_post()) {
    require_post();
    update_item_details(
        $state,
        $itemId,
        isset($_POST['location']) ? $_POST['location'] : '',
        isset($_POST['condition_note']) ? $_POST['condition_note'] : '',
        isset($_POST['admin_memo']) ? $_POST['admin_memo'] : '',
        $user
    );
    rental_save($state);
    flash('기자재 정보를 수정했습니다.');
    redirect_to('admin/items.php');
}
$category = item_category($state, $item);
render_header('기자재 수정', true);
admin_nav();
?>
<h1>기자재 수정</h1>
<section class="card">
  <p><strong><?php echo e($item['label']); ?></strong> · <?php echo e($category ? $category['name'] : '-'); ?></p>
  <p class="muted">라벨, 카테고리 번호, public_code는 기록 안정성을 위해 이 화면에서 수정하지 않습니다.</p>
  <form class="form" method="post">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
    <label>보관 위치
      <input name="location" value="<?php echo e($item['location']); ?>">
    </label>
    <label>특이사항
      <textarea name="condition_note" rows="4"><?php echo e($item['condition_note']); ?></textarea>
    </label>
    <label>관리자 메모
      <textarea name="admin_memo" rows="4"><?php echo e($item['admin_memo']); ?></textarea>
    </label>
    <button class="primary" type="submit">저장</button>
  </form>
</section>
<?php render_footer(); ?>
