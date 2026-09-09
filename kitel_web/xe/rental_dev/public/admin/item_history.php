<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$item = find_item($state, $itemId);
if (!$item) {
    http_response_code(404);
    exit('기자재를 찾을 수 없습니다.');
}
$category = item_category($state, $item);
$loans = item_all_loans($state, $itemId);
$logs = array_values(array_filter($state['logs'], function ($log) use ($itemId) {
    return (int)$log['item_id'] === (int)$itemId;
}));
render_header('기자재 이력', true);
admin_nav();
?>
<h1>기자재 이력</h1>
<section class="card">
  <h2><?php echo e($item['label']); ?></h2>
  <p class="muted"><?php echo e($category ? $category['name'] : '-'); ?> · <?php echo e($item['public_code']); ?></p>
  <p><span class="badge <?php echo e($item['status']); ?>"><?php echo e(status_label($item['status'])); ?></span> <?php echo e($item['location']); ?></p>
  <p class="actions">
    <a class="button" href="<?php echo e(app_url('admin/item_detail.php?item_id=' . $item['item_id'])); ?>">상세</a>
    <a class="button" href="<?php echo e(app_url('admin/item_edit.php?item_id=' . $item['item_id'])); ?>">수정</a>
    <a class="button" href="<?php echo e(app_url('admin/qr.php?item_id=' . $item['item_id'])); ?>">QR</a>
  </p>
</section>

<section class="card table-wrap">
  <h2>대여/반납 기록</h2>
  <table>
    <thead><tr><th>대여 번호</th><th>대여자</th><th>실사용자</th><th>대여일</th><th>반납 예정일</th><th>반납일</th><th>상태</th></tr></thead>
    <tbody>
    <?php foreach (array_reverse($loans) as $loan): ?>
      <tr>
        <td><?php echo e($loan['loan_id']); ?></td>
        <td><?php echo e($loan['borrower_name_snapshot']); ?><br><span class="muted"><?php echo e($loan['borrower_user_id_snapshot']); ?></span></td>
        <td><?php echo e($loan['actual_user_name']); ?><br><span class="muted"><?php echo e($loan['actual_user_contact']); ?></span></td>
        <td><?php echo e(date_only($loan['borrowed_at'])); ?></td>
        <td><?php echo e(date_only($loan['due_at'])); ?></td>
        <td><?php echo e($loan['returned_at'] ? date_only($loan['returned_at']) : '-'); ?></td>
        <td><span class="badge <?php echo e($loan['status']); ?>"><?php echo e(loan_is_overdue($loan) ? '연체' : status_label($loan['status'])); ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="7">대여 기록이 없습니다.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<section class="card table-wrap">
  <h2>처리 로그</h2>
  <table>
    <thead><tr><th>시간</th><th>처리자</th><th>액션</th><th>상태</th><th>메모</th></tr></thead>
    <tbody>
    <?php foreach (array_reverse($logs) as $log): ?>
      <tr>
        <td><?php echo e($log['created_at']); ?></td>
        <td><?php echo e($log['actor_member_srl']); ?></td>
        <td><?php echo e($log['action']); ?></td>
        <td><?php echo e($log['before_status']); ?> → <?php echo e($log['after_status']); ?></td>
        <td><?php echo e($log['memo']); ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="5">처리 로그가 없습니다.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
