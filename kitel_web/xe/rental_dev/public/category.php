<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category = find_category($state, $categoryId);
if (!$category || (int)$category['is_active'] !== 1 || category_tracking_mode($category) !== 'bulk') {
    http_response_code(404);
    exit('카테고리를 찾을 수 없습니다.');
}

if (is_post()) {
    require_post();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $quantity = max(0, isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0);
    if ($action === 'add_rent' && $quantity > 0) {
        cart_set_category_quantity('rent_list', $categoryId, $category['name'], $quantity);
        redirect_to('rent_list.php');
    }
    if ($action === 'add_return' && $quantity > 0) {
        cart_set_category_quantity('return_list', $categoryId, $category['name'], $quantity);
        redirect_to('return_list.php');
    }
    redirect_to('category.php?id=' . $categoryId);
}

$counts = category_item_counts($state, $categoryId);
$myCount = user_active_loan_count_in_category($state, $user['member_srl'], $categoryId);
$maxPerUser = category_max_per_user($category);
$remaining = $maxPerUser > 0 ? max(0, $maxPerUser - $myCount) : null;
$rentMax = $maxPerUser > 0 ? min($counts['available'], $remaining) : $counts['available'];
$returnMax = $counts['borrowed'];
$rentInCart = cart_category_quantity('rent_list', $categoryId);
$returnInCart = cart_category_quantity('return_list', $categoryId);
render_header($category['name']);
?>
<h1><?php echo e($category['name']); ?></h1>
<div class="two">
  <section class="card">
    <h2>현재 재고</h2>
    <p><strong><?php echo (int)$counts['available']; ?></strong> / <?php echo (int)$counts['total']; ?>개 대여 가능</p>
    <p class="muted">현재 대여 중: <?php echo (int)$counts['borrowed']; ?>개</p>
    <?php if ($category['description']): ?>
      <p class="muted"><?php echo nl2br(e($category['description'])); ?></p>
    <?php endif; ?>
    <?php if ($maxPerUser > 0): ?>
      <p class="muted">1인당 최대 <?php echo $maxPerUser; ?>개 · 현재 내가 대여 중: <?php echo $myCount; ?>개</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <?php if ($rentMax > 0): ?>
      <h2>대여 목록에 담기</h2>
      <?php if ($rentInCart > 0): ?><p class="muted">현재 대여 목록에 <?php echo $rentInCart; ?>개 담겨 있습니다. 다시 담으면 이 값으로 바뀝니다.</p><?php endif; ?>
      <form class="form" method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="add_rent">
        <label>담을 개수 (최대 <?php echo $rentMax; ?>개)
          <input type="number" name="quantity" min="1" max="<?php echo $rentMax; ?>" value="<?php echo $rentInCart > 0 ? $rentInCart : 1; ?>" required>
        </label>
        <button class="primary" type="submit">대여 목록에 담기</button>
      </form>
    <?php else: ?>
      <h2>대여 불가</h2>
      <p class="muted"><?php echo $counts['available'] <= 0 ? '현재 대여 가능한 재고가 없습니다.' : '1인당 대여 한도에 도달했습니다.'; ?></p>
    <?php endif; ?>

    <?php if ($returnMax > 0): ?>
      <hr>
      <h2>반납 목록에 담기</h2>
      <?php if ($returnInCart > 0): ?><p class="muted">현재 반납 목록에 <?php echo $returnInCart; ?>개 담겨 있습니다. 다시 담으면 이 값으로 바뀝니다.</p><?php endif; ?>
      <form class="form" method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="add_return">
        <label>반납할 개수 (현재 대여 중 <?php echo $returnMax; ?>개)
          <input type="number" name="quantity" min="1" max="<?php echo $returnMax; ?>" value="<?php echo $returnInCart > 0 ? $returnInCart : 1; ?>" required>
        </label>
        <button type="submit">반납 목록에 담기</button>
      </form>
    <?php endif; ?>
  </section>
</div>
<?php render_footer(); ?>
