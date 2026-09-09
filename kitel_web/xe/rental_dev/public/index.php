<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();
$activeLoans = user_active_loans($state, $user['member_srl']);
$overdueCount = count(array_filter($activeLoans, 'loan_is_overdue'));
render_header('기자재 대여');
?>
<h1>KITEL 기자재 대여</h1>
<section class="grid">
  <a class="card metric" href="<?php echo e(app_url('scan.php?mode=rent')); ?>">
    <span>대여하기</span>
    <strong>QR 스캔<?php echo cart_count('rent_list') > 0 ? ' (' . cart_count('rent_list') . ')' : ''; ?></strong>
  </a>
  <a class="card metric" href="<?php echo e(app_url('scan.php?mode=return')); ?>">
    <span>반납하기</span>
    <strong>QR 스캔<?php echo cart_count('return_list') > 0 ? ' (' . cart_count('return_list') . ')' : ''; ?></strong>
  </a>
  <a class="card metric" href="<?php echo e(app_url('my.php')); ?>">
    <span>내 대여</span>
    <strong><?php echo count($activeLoans); ?>개<?php echo $overdueCount > 0 ? ' (연체 ' . $overdueCount . ')' : ''; ?></strong>
  </a>
  <a class="card metric" href="<?php echo e(app_url('catalog.php')); ?>">
    <span>기자재 현황</span>
    <strong>재고 조회</strong>
  </a>
</section>

<section class="card table-wrap">
  <h2 class="section-head">내가 대여 중인 기자재 <a class="muted" href="<?php echo e(app_url('my.php')); ?>">전체 보기 →</a></h2>
  <table>
    <thead><tr><th>기자재</th><th>대여일</th><th>반납 예정일</th><th>상태</th></tr></thead>
    <tbody>
    <?php foreach (array_slice(group_loans_for_display($state, $activeLoans), 0, 5) as $loan): ?>
      <tr>
        <td><?php echo e($loan['label']); ?><?php echo $loan['count'] > 1 ? ' (' . $loan['count'] . '개)' : ''; ?></td>
        <td><?php echo e(date_only($loan['borrowed_at'])); ?></td>
        <td><?php echo e(date_only($loan['due_at'])); ?></td>
        <td><span class="badge <?php echo loan_is_overdue($loan) ? 'overdue' : 'borrowed'; ?>"><?php echo loan_is_overdue($loan) ? '연체' : '대여 중'; ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$activeLoans): ?><tr><td colspan="4">현재 대여 중인 기자재가 없습니다.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
