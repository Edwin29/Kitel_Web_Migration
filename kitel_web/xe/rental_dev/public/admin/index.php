<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$items = active_items($state);
$activeLoans = array_filter($state['loans'], function ($loan) { return $loan['status'] === 'borrowed'; });
$overdue = array_filter($activeLoans, 'loan_is_overdue');
render_header('관리자 대시보드', true);
admin_nav();
?>
<h1>관리자 대시보드</h1>
<section class="grid">
  <a class="card metric" href="<?php echo e(app_url('admin/items.php')); ?>"><span>전체 기자재</span><strong><?php echo count($items); ?></strong></a>
  <a class="card metric" href="<?php echo e(app_url('admin/items.php?status=available')); ?>"><span>대여 가능</span><strong><?php echo count(array_filter($items, function ($i) { return $i['status'] === 'available'; })); ?></strong></a>
  <a class="card metric" href="<?php echo e(app_url('admin/loans.php?status=borrowed')); ?>"><span>대여 중</span><strong><?php echo count($activeLoans); ?></strong></a>
  <a class="card metric" href="<?php echo e(app_url('admin/loans.php?status=overdue')); ?>"><span>연체</span><strong><?php echo count($overdue); ?></strong></a>
</section>
<section class="card table-wrap">
  <h2 class="section-head">최근 처리 로그 <a class="muted" href="<?php echo e(app_url('admin/logs.php')); ?>">전체 보기 →</a></h2>
  <table>
    <thead><tr><th>시간</th><th>액션</th><th>대상</th><th>메모</th></tr></thead>
    <tbody>
    <?php foreach (array_slice(array_reverse($state['logs']), 0, 10) as $log): ?>
      <tr><td><?php echo e($log['created_at']); ?></td><td><?php echo e($log['action']); ?></td><td><?php echo e($log['item_id']); ?></td><td><?php echo e($log['memo']); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
