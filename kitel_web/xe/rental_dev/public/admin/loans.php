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
    $itemStatus = isset($_POST['item_status']) ? $_POST['item_status'] : 'available';
    // 개수 관리 묶음은 일부만 회수되는 경우가 흔하다(5개 중 2개만 돌아옴).
    // 개수를 지정하면 그 묶음에서 앞의 N건만 반납 처리한다.
    $returnCount = isset($_POST['return_count']) ? (int)$_POST['return_count'] : 0;
    if ($returnCount > 0 && $returnCount < count($loanIds)) {
        $loanIds = array_slice($loanIds, 0, $returnCount);
    }
    if ($memo === '') {
        flash('강제 반납 사유가 필요합니다.');
    } elseif (!$loanIds) {
        flash('처리할 대여 건을 찾지 못했습니다.');
    } else {
        try {
            $result = return_loans_bulk($state, $loanIds, $user, 'force', $memo, $itemStatus);
            rental_save($state);
            if ($result['returned'] > 0) {
                $message = $result['returned'] . '건 강제 반납 처리했습니다.';
                if ($result['returned'] < $result['requested']) {
                    $message .= ' (' . ($result['requested'] - $result['returned']) . '건은 이미 처리되어 건너뜀)';
                }
                if ($itemStatus !== 'available') {
                    $message .= ' 반납 후 상태: ' . status_label($itemStatus) . '.';
                }
                flash($message);
            } else {
                flash('처리할 수 있는 대여 중 기록을 찾지 못했습니다.');
            }
        } catch (RuntimeException $e) {
            flash($e->getMessage());
        }
    }
    redirect_to('admin/loans.php');
}

// 필터링·정렬·페이지 나누기는 조회 계층(SQL)에서 한다. 페이지 단위는 "행"이 아니라
// "묶음"이라, 개수 관리 카테고리를 한 번에 빌린 건이 페이지 경계에서 갈라지지 않는다.
$filters = loan_filters_from_request();
$result = loan_query($state, $filters, rental_page_param(), 50);
render_header('대여 현황', true);
admin_nav();
?>
<div class="page-head">
  <h1>전체 대여/반납 현황</h1>
  <div class="page-head-actions">
    <a class="button primary" href="<?php echo e(app_url('admin/loan_new.php')); ?>">+ 관리자 대여 등록</a>
    <a class="button no-print" href="<?php echo e(rental_query_url('admin/export.php', array('type' => 'loans', 'page' => null))); ?>">CSV 내보내기</a>
  </div>
</div>
<form class="card actions no-print" method="get">
  <input name="q" value="<?php echo e($filters['q']); ?>" placeholder="기자재, 사용자, 연락처 검색">
  <select name="status">
    <option value="">전체 상태</option>
    <?php foreach (array('borrowed' => '대여 중', 'overdue' => '연체', 'returned' => '반납 완료', 'force_returned' => '강제 반납') as $value => $label): ?>
      <option value="<?php echo e($value); ?>" <?php echo $filters['status'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?php echo e($filters['from']); ?>">
  <input type="date" name="to" value="<?php echo e($filters['to']); ?>">
  <button type="submit">필터</button>
</form>
<section class="card table-wrap">
  <table><thead><tr><th>기자재</th><th>대여자</th><th>실사용자</th><th>기간</th><th>상태</th><th>관리</th></tr></thead><tbody>
  <?php foreach (group_loans_for_display($state, $result['rows']) as $loan): ?>
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
          <?php if ($loan['count'] > 1): ?>
            <input class="qty-input" type="number" name="return_count" min="1"
                   max="<?php echo (int)$loan['count']; ?>" value="<?php echo (int)$loan['count']; ?>"
                   title="반납 처리할 개수 (총 <?php echo (int)$loan['count']; ?>개)">
            <span class="muted">/ <?php echo (int)$loan['count']; ?>개</span>
          <?php endif; ?>
          <input name="memo" placeholder="강제 반납 사유" required>
          <select name="item_status" title="반납 후 기자재 상태">
            <option value="available">반납 후 대여 가능</option>
            <option value="broken">반납 후 고장</option>
            <option value="unavailable">반납 후 대여 불가</option>
            <option value="lost">반납 후 분실</option>
          </select>
          <button class="danger" type="submit">강제 반납</button>
        </form>
      <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$result['rows']): ?><tr><td colspan="6">조건에 맞는 대여 기록이 없습니다.</td></tr><?php endif; ?>
  </tbody></table>
</section>
<?php render_pagination($result, 'admin/loans.php'); ?>
<?php render_footer(); ?>
