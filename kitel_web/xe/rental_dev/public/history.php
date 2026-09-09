<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();
$loanPage = user_all_loans($state, $user['member_srl'], rental_page_param(), 50);
$loans = group_loans_for_display($state, $loanPage['rows']);
render_header('내 대여 기록');
?>
<h1>내 대여 기록</h1>
<p class="muted"><a href="<?php echo e(app_url('')); ?>">← 홈으로</a></p>
<section class="card table-wrap">
  <table>
    <thead><tr><th>기자재</th><th>대여일</th><th>반납일</th><th>상태</th><th>연체</th></tr></thead>
    <tbody>
    <?php foreach ($loans as $loan): ?>
      <tr>
        <td><?php echo e($loan['label']); ?><?php echo $loan['count'] > 1 ? ' (' . $loan['count'] . '개)' : ''; ?></td>
        <td><?php echo e(date_only($loan['borrowed_at'])); ?></td>
        <td><?php echo e($loan['returned_at'] ? date_only($loan['returned_at']) : '-'); ?></td>
        <td><span class="badge <?php echo e($loan['status']); ?>"><?php echo e(status_label($loan['status'])); ?></span></td>
        <td><?php echo loan_is_overdue($loan) ? '연체' : '-'; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="5">대여 기록이 없습니다.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php render_pagination($loanPage, 'history.php'); ?>
<?php render_footer(); ?>
