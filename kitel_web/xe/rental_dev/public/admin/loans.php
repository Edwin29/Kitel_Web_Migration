<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
if (is_post()) {
    require_post();
    $memo = trim(isset($_POST['memo']) ? $_POST['memo'] : '');
    $loanIds = (isset($_POST['loan_ids']) && is_array($_POST['loan_ids'])) ? array_map('intval', $_POST['loan_ids']) : array();
    if (!$loanIds && isset($_POST['loan_id'])) {
        $loanIds = array((int)$_POST['loan_id']);
    }
    if ($memo === '') {
        flash('강제 반납 사유가 필요합니다.');
    } elseif (!$loanIds) {
        flash('처리할 대여 건을 찾지 못했습니다.');
    } else {
        $result = return_loans_bulk($state, $loanIds, $user, 'force', $memo);
        rental_save($state);
        if ($result['returned'] > 0) {
            $message = $result['returned'] . '건 강제 반납 처리했습니다.';
            if ($result['returned'] < $result['requested']) {
                $message .= ' (' . ($result['requested'] - $result['returned']) . '건은 이미 처리되어 건너뜀)';
            }
            flash($message);
        } else {
            flash('처리할 수 있는 대여 중 기록을 찾지 못했습니다.');
        }
    }
    redirect_to('admin/loans.php');
}
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$fromDate = isset($_GET['from']) ? trim($_GET['from']) : '';
$toDate = isset($_GET['to']) ? trim($_GET['to']) : '';
$loans = array_values(array_filter($state['loans'], function ($loan) use ($state, $query, $statusFilter, $fromDate, $toDate) {
    $item = find_item($state, $loan['item_id']);
    $effectiveStatus = loan_is_overdue($loan) ? 'overdue' : $loan['status'];
    if ($statusFilter !== '' && $effectiveStatus !== $statusFilter) {
        return false;
    }
    if ($fromDate !== '' && date_only($loan['borrowed_at']) < $fromDate) {
        return false;
    }
    if ($toDate !== '' && date_only($loan['borrowed_at']) > $toDate) {
        return false;
    }
    if ($query !== '') {
        $haystack = implode(' ', array(
            $item ? $item['label'] : '',
            $item ? $item['public_code'] : '',
            $loan['borrower_name_snapshot'],
            $loan['borrower_user_id_snapshot'],
            $loan['actual_user_name'],
            $loan['actual_user_contact'],
        ));
        if (stripos($haystack, $query) === false) {
            return false;
        }
    }
    return true;
}));
$exportType = 'loans';
if ($statusFilter === 'overdue') {
    $exportType = 'overdue';
} elseif ($statusFilter === 'borrowed') {
    $exportType = 'active';
}
render_header('대여 현황', true);
admin_nav();
?>
<div class="page-head">
  <h1>전체 대여/반납 현황</h1>
  <div class="page-head-actions">
    <a class="button primary" href="<?php echo e(app_url('admin/loan_new.php')); ?>">+ 관리자 대여 등록</a>
    <a class="button no-print" href="<?php echo e(app_url('admin/export.php?type=' . $exportType)); ?>">CSV 내보내기</a>
  </div>
</div>
<form class="card actions no-print" method="get">
  <input name="q" value="<?php echo e($query); ?>" placeholder="기자재, 사용자, 연락처 검색">
  <select name="status">
    <option value="">전체 상태</option>
    <?php foreach (array('borrowed' => '대여 중', 'overdue' => '연체', 'returned' => '반납 완료', 'force_returned' => '강제 반납') as $value => $label): ?>
      <option value="<?php echo e($value); ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?php echo e($fromDate); ?>">
  <input type="date" name="to" value="<?php echo e($toDate); ?>">
  <button type="submit">필터</button>
</form>
<section class="card table-wrap">
  <table><thead><tr><th>기자재</th><th>대여자</th><th>실사용자</th><th>기간</th><th>상태</th><th>관리</th></tr></thead><tbody>
  <?php foreach (group_loans_for_display($state, array_reverse($loans)) as $loan): ?>
    <tr>
      <td><?php echo e($loan['label']); ?><?php echo $loan['count'] > 1 ? ' (' . $loan['count'] . '개)' : ''; ?></td>
      <td><a href="<?php echo e(app_url('admin/member_history.php?member_srl=' . $loan['borrower_member_srl'])); ?>"><?php echo e($loan['borrower_name']); ?></a></td>
      <td><?php echo e($loan['actual_user_name']); ?><br><span class="muted"><?php echo e($loan['actual_user_contact']); ?></span></td>
      <td><?php echo e(date_only($loan['borrowed_at'])); ?> ~ <?php echo e(date_only($loan['due_at'])); ?></td>
      <td><span class="badge <?php echo e($loan['status']); ?>"><?php echo e(loan_is_overdue($loan) ? '연체' : status_label($loan['status'])); ?></span></td>
      <td>
      <?php if ($loan['status'] === 'borrowed'): ?>
        <form class="actions" method="post">
          <?php echo csrf_input(); ?>
          <?php foreach ($loan['loan_ids'] as $loanId): ?>
            <input type="hidden" name="loan_ids[]" value="<?php echo e($loanId); ?>">
          <?php endforeach; ?>
          <input name="memo" placeholder="강제 반납 사유" required>
          <button class="danger" type="submit">강제 반납<?php echo $loan['count'] > 1 ? ' (' . $loan['count'] . '개 전체)' : ''; ?></button>
        </form>
      <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$loans): ?><tr><td colspan="6">조건에 맞는 대여 기록이 없습니다.</td></tr><?php endif; ?>
  </tbody></table>
</section>
<?php render_footer(); ?>
