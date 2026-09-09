<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$actionFilter = isset($_GET['action']) ? trim($_GET['action']) : '';
$actorFilter = isset($_GET['actor']) ? trim($_GET['actor']) : '';
$fromDate = isset($_GET['from']) ? trim($_GET['from']) : '';
$toDate = isset($_GET['to']) ? trim($_GET['to']) : '';
$actions = array();
foreach ($state['logs'] as $log) {
    if (!in_array($log['action'], $actions, true)) {
        $actions[] = $log['action'];
    }
}
$logs = array_values(array_filter($state['logs'], function ($log) use ($query, $actionFilter, $actorFilter, $fromDate, $toDate) {
    if ($actionFilter !== '' && $log['action'] !== $actionFilter) {
        return false;
    }
    if ($actorFilter !== '' && (string)$log['actor_member_srl'] !== $actorFilter) {
        return false;
    }
    if ($fromDate !== '' && date_only($log['created_at']) < $fromDate) {
        return false;
    }
    if ($toDate !== '' && date_only($log['created_at']) > $toDate) {
        return false;
    }
    if ($query !== '') {
        $haystack = implode(' ', array(
            $log['action'],
            $log['item_id'],
            $log['loan_id'],
            $log['before_status'],
            $log['after_status'],
            $log['memo'],
        ));
        if (stripos($haystack, $query) === false) {
            return false;
        }
    }
    return true;
}));
sort($actions);
render_header('처리 로그', true);
admin_nav();
?>
<div class="page-head">
  <h1>처리 로그</h1>
  <a class="button no-print" href="<?php echo e(app_url('admin/export.php?type=logs')); ?>">CSV 내보내기</a>
</div>
<form class="card actions no-print" method="get">
  <input name="q" value="<?php echo e($query); ?>" placeholder="액션, 물품 번호, 대여 번호, 메모 검색">
  <select name="action">
    <option value="">전체 액션</option>
    <?php foreach ($actions as $action): ?>
      <option value="<?php echo e($action); ?>" <?php echo $actionFilter === $action ? 'selected' : ''; ?>><?php echo e($action); ?></option>
    <?php endforeach; ?>
  </select>
  <input name="actor" value="<?php echo e($actorFilter); ?>" placeholder="처리자 member_srl">
  <input type="date" name="from" value="<?php echo e($fromDate); ?>">
  <input type="date" name="to" value="<?php echo e($toDate); ?>">
  <button type="submit">필터</button>
</form>
<section class="card table-wrap">
  <table><thead><tr><th>시간</th><th>처리자</th><th>액션</th><th>대상</th><th>상태</th><th>메모</th></tr></thead><tbody>
  <?php foreach (array_reverse($logs) as $log): ?>
    <tr>
      <td><?php echo e($log['created_at']); ?></td>
      <td><?php echo e($log['actor_member_srl']); ?></td>
      <td><?php echo e($log['action']); ?></td>
      <td>item <?php echo e($log['item_id']); ?><br>loan <?php echo e($log['loan_id']); ?></td>
      <td><?php echo e($log['before_status']); ?> → <?php echo e($log['after_status']); ?></td>
      <td><?php echo e($log['memo']); ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$logs): ?><tr><td colspan="6">조건에 맞는 로그가 없습니다.</td></tr><?php endif; ?>
  </tbody></table>
</section>
<?php render_footer(); ?>
