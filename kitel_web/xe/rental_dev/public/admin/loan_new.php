<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
if (is_post()) {
    require_post();
    $borrower = find_xe_member(isset($_POST['borrower_member_srl']) ? (int)$_POST['borrower_member_srl'] : 0);
    if (!$borrower) {
        flash('XE 회원을 찾을 수 없습니다.');
        redirect_to('admin/loan_new.php');
    }
    $ignoreLimit = isset($_POST['ignore_limit']);
    $mode = isset($_POST['mode']) ? $_POST['mode'] : 'unique';
    try {
        if ($mode === 'bulk') {
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            $result = create_bulk_loan(
                $state,
                $categoryId,
                $quantity,
                isset($_POST['actual_user_name']) ? $_POST['actual_user_name'] : '',
                isset($_POST['actual_user_contact']) ? $_POST['actual_user_contact'] : '',
                isset($_POST['due_date']) ? $_POST['due_date'] : default_due_date(),
                isset($_POST['note']) ? $_POST['note'] : '관리자 대여 등록',
                $user,
                $borrower,
                $ignoreLimit
            );
            add_log($state, 'loan.admin_create', null, null, null, 'borrowed', '관리자 대여 등록 (' . $result['created'] . '개)', $user);
            rental_save($state);
            $message = $result['created'] . '개 관리자 대여가 등록되었습니다.';
            if ($result['created'] < $result['requested']) {
                $message .= ' (' . ($result['requested'] - $result['created']) . '개는 처리하지 못함: ' . $result['reason'] . ')';
            }
            flash($message);
        } else {
            $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            $loan = create_loan(
                $state,
                $itemId,
                isset($_POST['actual_user_name']) ? $_POST['actual_user_name'] : '',
                isset($_POST['actual_user_contact']) ? $_POST['actual_user_contact'] : '',
                isset($_POST['due_date']) ? $_POST['due_date'] : default_due_date(),
                isset($_POST['note']) ? $_POST['note'] : '관리자 대여 등록',
                $user,
                $borrower,
                $ignoreLimit
            );
            add_log($state, 'loan.admin_create', $itemId, $loan['loan_id'], null, 'borrowed', '관리자 대여 등록', $user);
            rental_save($state);
            flash('관리자 대여가 등록되었습니다.');
        }
        redirect_to('admin/loans.php');
    } catch (RuntimeException $e) {
        flash($e->getMessage());
        redirect_to('admin/loan_new.php');
    }
}
$availableItems = array_values(array_filter(active_items($state), function ($item) use ($state) {
    if ($item['status'] !== 'available') {
        return false;
    }
    $category = item_category($state, $item);
    return !$category || category_tracking_mode($category) !== 'bulk';
}));
$bulkCategories = array_values(array_filter(active_categories($state), function ($category) {
    return category_tracking_mode($category) === 'bulk';
}));
$memberQuery = isset($_GET['member_q']) ? trim($_GET['member_q']) : '';
$memberResults = search_xe_members($memberQuery);
render_header('관리자 대여 등록', true);
admin_nav();
?>
<h1>관리자 대여 등록</h1>
<section class="card">
  <h2>회원 찾기</h2>
  <form class="actions" method="get">
    <input name="member_q" value="<?php echo e($memberQuery); ?>" placeholder="이름, 아이디, 이메일 검색">
    <button type="submit">검색</button>
  </form>
  <?php if ($memberQuery !== ''): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>회원 번호</th><th>아이디</th><th>이름</th><th>이메일</th></tr></thead>
        <tbody>
        <?php foreach ($memberResults as $member): ?>
          <tr>
            <td><?php echo e($member['member_srl']); ?></td>
            <td><?php echo e($member['user_id']); ?></td>
            <td><?php echo e($member['nick_name']); ?></td>
            <td><?php echo e($member['email_address']); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$memberResults): ?><tr><td colspan="4">검색 결과가 없습니다.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<div class="two">
  <section class="card">
    <h2>개별 관리 기자재</h2>
    <form class="form" method="post">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="mode" value="unique">
      <label>기자재
        <select name="item_id" required>
          <?php foreach ($availableItems as $item): ?>
            <option value="<?php echo e($item['item_id']); ?>"><?php echo e($item['label']); ?> · <?php echo e($item['location']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>회원 번호 member_srl
        <input type="number" name="borrower_member_srl" min="1" required>
      </label>
      <label>실제 사용자 이름
        <input name="actual_user_name">
      </label>
      <label>실제 사용자 연락처
        <input name="actual_user_contact">
      </label>
      <label>반납 예정일
        <input type="date" name="due_date" value="<?php echo e(default_due_date()); ?>" required>
          <span class="muted">카테고리에 설정된 대여 기간 대신 이 날짜가 적용됩니다.</span>
      </label>
      <label>등록 사유/비고
        <textarea name="note" rows="3" required>관리자 대여 등록</textarea>
      </label>
      <label class="inline"><input type="checkbox" name="ignore_limit" value="1"> 1인당 제한 무시</label>
      <button class="primary" type="submit">대여 등록</button>
    </form>
  </section>
  <section class="card">
    <h2>개수 관리 카테고리</h2>
    <?php if (!$bulkCategories): ?>
      <p class="muted">개수 관리로 설정된 카테고리가 없습니다. (카테고리 관리 → 고급 설정에서 전환)</p>
    <?php else: ?>
      <form class="form" method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="mode" value="bulk">
        <label>카테고리
          <select name="category_id" required>
            <?php foreach ($bulkCategories as $category): $counts = category_item_counts($state, $category['category_id']); ?>
              <option value="<?php echo e($category['category_id']); ?>"><?php echo e($category['name']); ?> (가용 <?php echo $counts['available']; ?>개)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>수량
          <input type="number" name="quantity" min="1" value="1" required>
        </label>
        <label>회원 번호 member_srl
          <input type="number" name="borrower_member_srl" min="1" required>
        </label>
        <label>실제 사용자 이름
          <input name="actual_user_name">
        </label>
        <label>실제 사용자 연락처
          <input name="actual_user_contact">
        </label>
        <label>반납 예정일
          <input type="date" name="due_date" value="<?php echo e(default_due_date()); ?>" required>
          <span class="muted">카테고리에 설정된 대여 기간 대신 이 날짜가 적용됩니다.</span>
        </label>
        <label>등록 사유/비고
          <textarea name="note" rows="3" required>관리자 대여 등록</textarea>
        </label>
        <label class="inline"><input type="checkbox" name="ignore_limit" value="1"> 1인당 제한 무시</label>
        <button class="primary" type="submit">대여 등록</button>
      </form>
    <?php endif; ?>
  </section>
</div>
<?php render_footer(); ?>
