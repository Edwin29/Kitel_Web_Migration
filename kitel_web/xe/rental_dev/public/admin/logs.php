<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();

// 필터링·정렬·페이지 나누기는 전부 조회 계층(SQL)에서 한다.
// 예전에는 전체 로그를 메모리에 올린 뒤 PHP 배열로 걸렀고, 그 결과를 화면에서
// 한 번 더 array_reverse() 해서 운영에서는 오래된 순으로 보였다.
$filters = log_filters_from_request();
$result = log_query($state, $filters, rental_page_param(), 100);
$actions = log_actions($state);
render_header('처리 로그', true);
admin_nav();
?>
<div class="page-head">
  <h1>처리 로그</h1>
  <a class="button no-print" href="<?php echo e(rental_query_url('admin/export.php', array('type' => 'logs', 'page' => null))); ?>">CSV 내보내기</a>
</div>
<form class="card actions no-print" method="get">
  <input name="q" value="<?php echo e($filters['q']); ?>" placeholder="액션, 물품 번호, 대여 번호, 메모 검색">
  <select name="action">
    <option value="">전체 액션</option>
    <?php foreach ($actions as $action): ?>
      <option value="<?php echo e($action); ?>" <?php echo $filters['action'] === $action ? 'selected' : ''; ?>><?php echo e($action); ?></option>
    <?php endforeach; ?>
  </select>
  <input name="actor" value="<?php echo e($filters['actor']); ?>" placeholder="처리자 member_srl">
  <input type="date" name="from" value="<?php echo e($filters['from']); ?>">
  <input type="date" name="to" value="<?php echo e($filters['to']); ?>">
  <button type="submit">필터</button>
</form>
<section class="card table-wrap">
  <table><thead><tr><th>시간</th><th>처리자</th><th>액션</th><th>대상</th><th>상태</th><th>메모</th></tr></thead><tbody>
  <?php foreach ($result['rows'] as $log): ?>
    <tr>
      <td><?php echo e($log['created_at']); ?></td>
      <td><?php echo e($log['actor_member_srl']); ?></td>
      <td><?php echo e($log['action']); ?></td>
      <td>item <?php echo e($log['item_id']); ?><br>loan <?php echo e($log['loan_id']); ?></td>
      <td><?php echo e($log['before_status']); ?> → <?php echo e($log['after_status']); ?></td>
      <td><?php echo e($log['memo']); ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$result['rows']): ?><tr><td colspan="6">조건에 맞는 로그가 없습니다.</td></tr><?php endif; ?>
  </tbody></table>
</section>
<?php render_pagination($result, 'admin/logs.php'); ?>
<?php render_footer(); ?>
