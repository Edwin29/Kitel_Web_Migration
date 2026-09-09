<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$items = active_items($state);
// 지표는 SQL 집계로 센다 (예전에는 전체 대여 기록을 메모리에 올려 count() 했다).
$counts = loan_dashboard_counts($state);
// 연체는 관리자가 가장 먼저 조치해야 할 항목이라 숫자만 두지 않고 목록도 함께 보여준다.
$overdueList = loan_query($state, array('status' => 'overdue'), 1, 10);
render_header('관리자 대시보드', true);
admin_nav();
?>
<h1>관리자 대시보드</h1>
<section class="grid">
  <a class="card metric" href="<?php echo e(app_url('admin/items.php')); ?>"><span>전체 기자재</span><strong><?php echo count($items); ?></strong></a>
  <a class="card metric" href="<?php echo e(app_url('admin/items.php?status=available')); ?>"><span>대여 가능</span><strong><?php echo count(array_filter($items, function ($i) { return $i['status'] === 'available'; })); ?></strong></a>
  <a class="card metric" href="<?php echo e(app_url('admin/loans.php?status=borrowed')); ?>"><span>대여 중</span><strong><?php echo (int)$counts['borrowed']; ?></strong></a>
  <a class="card metric" href="<?php echo e(app_url('admin/loans.php?status=overdue')); ?>"><span>연체</span><strong><?php echo (int)$counts['overdue']; ?></strong></a>
  <a class="card metric" href="<?php echo e(app_url('admin/loans.php?status=borrowed')); ?>"><span>오늘 반납 예정</span><strong><?php echo (int)$counts['due_today']; ?></strong></a>
</section>

<?php if ($overdueList['rows']): ?>
<section class="card table-wrap">
  <h2 class="section-head">연체 <a class="muted" href="<?php echo e(app_url('admin/loans.php?status=overdue')); ?>">전체 보기 →</a></h2>
  <table>
    <thead><tr><th>기자재</th><th>대여자</th><th>반납 예정일</th><th>지연</th></tr></thead>
    <tbody>
    <?php foreach (group_loans_for_display($state, $overdueList['rows']) as $loan): ?>
      <?php $daysLate = (int)floor((strtotime(date('Y-m-d')) - strtotime(date_only($loan['due_at']))) / 86400); ?>
      <tr>
        <td><?php echo e($loan['label']); ?><?php echo $loan['count'] > 1 ? ' (' . $loan['count'] . '개)' : ''; ?></td>
        <td><a href="<?php echo e(app_url('admin/member_history.php?member_srl=' . $loan['borrower_member_srl'])); ?>"><?php echo e($loan['borrower_name']); ?></a></td>
        <td><?php echo e(date_only($loan['due_at'])); ?></td>
        <td><span class="badge overdue"><?php echo $daysLate; ?>일</span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
<section class="card table-wrap">
  <h2 class="section-head">최근 처리 로그 <a class="muted" href="<?php echo e(app_url('admin/logs.php')); ?>">전체 보기 →</a></h2>
  <table>
    <thead><tr><th>시간</th><th>처리자</th><th>액션</th><th>대상</th><th>메모</th></tr></thead>
    <tbody>
    <?php $recentLogs = log_recent($state, 10); ?>
    <?php prefetch_member_names(array_map(function ($l) { return $l['actor_member_srl']; }, $recentLogs)); ?>
    <?php foreach ($recentLogs as $log): $logItem = $log['item_id'] ? find_item($state, $log['item_id']) : null; ?>
      <tr>
        <td><?php echo e($log['created_at']); ?></td>
        <td><?php echo e(actor_label($log['actor_member_srl'])); ?></td>
        <td><?php echo e($log['action']); ?></td>
        <td>
          <?php if ($logItem): ?>
            <a href="<?php echo e(app_url('admin/item_history.php?item_id=' . (int)$log['item_id'])); ?>"><?php echo e($logItem['label']); ?></a>
          <?php else: ?>
            <span class="muted">-</span>
          <?php endif; ?>
        </td>
        <td><?php echo e($log['memo']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
