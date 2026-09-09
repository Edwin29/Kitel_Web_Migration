<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();
$loanPage = user_active_loans($state, $user['member_srl'], 1, 200);
$loans = group_loans_for_display($state, $loanPage['rows']);
render_header('내 대여 목록');
?>
<h1>내 대여 목록</h1>
<p class="muted">
  <a href="<?php echo e(app_url('')); ?>">← 홈으로</a> ·
  반납은 반납할 기자재의 QR을 스캔해서 <a href="<?php echo e(app_url('return_list.php')); ?>">반납 목록</a>에서 처리해 주세요 ·
  <a href="<?php echo e(app_url('history.php')); ?>">내 기록 보기</a>
</p>
<section class="card table-wrap">
  <table>
    <thead><tr><th>기자재</th><th>대여일</th><th>반납 예정일</th><th>상태</th></tr></thead>
    <tbody>
    <?php foreach ($loans as $loan): ?>
      <tr>
        <td><?php echo e($loan['label']); ?><?php echo $loan['count'] > 1 ? ' (' . $loan['count'] . '개)' : ''; ?></td>
        <td><?php echo e(date_only($loan['borrowed_at'])); ?></td>
        <td><?php echo e(date_only($loan['due_at'])); ?></td>
        <td><span class="badge <?php echo loan_is_overdue($loan) ? 'overdue' : 'borrowed'; ?>"><?php echo loan_is_overdue($loan) ? '연체' : '대여 중'; ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="4">현재 대여 중인 기자재가 없습니다.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
