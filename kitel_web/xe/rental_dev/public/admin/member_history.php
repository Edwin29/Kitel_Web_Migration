<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
$memberQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$memberSrl = isset($_GET['member_srl']) ? (int)$_GET['member_srl'] : 0;
$memberResults = search_xe_members($memberQuery);
$member = $memberSrl > 0 ? find_xe_member($memberSrl) : null;
$loans = $member ? user_all_loans($state, $member['member_srl'], rental_page_param(), 50) : rental_page(array(), 0, 1, 50);
$activeLoans = $member ? user_active_loans($state, $member['member_srl'], 1, 200) : rental_page(array(), 0, 1, 200);
render_header('사용자별 대여 기록', true);
admin_nav();
?>
<h1>사용자별 대여 기록</h1>
<section class="card">
  <h2>회원 찾기</h2>
  <form class="actions" method="get">
    <input name="q" value="<?php echo e($memberQuery); ?>" placeholder="이름, 아이디, 이메일 검색">
    <button type="submit">검색</button>
  </form>
  <?php if ($memberQuery !== ''): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>회원 번호</th><th>아이디</th><th>이름</th><th>이메일</th><th>보기</th></tr></thead>
        <tbody>
        <?php foreach ($memberResults as $row): ?>
          <tr>
            <td><?php echo e($row['member_srl']); ?></td>
            <td><?php echo e($row['user_id']); ?></td>
            <td><?php echo e($row['nick_name']); ?></td>
            <td><?php echo e($row['email_address']); ?></td>
            <td><a class="button" href="<?php echo e(app_url('admin/member_history.php?member_srl=' . (int)$row['member_srl'])); ?>">기록</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$memberResults): ?><tr><td colspan="5">검색 결과가 없습니다.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($member): ?>
  <section class="card">
    <h2><?php echo e($member['nick_name']); ?></h2>
    <p class="muted"><?php echo e($member['user_id']); ?> · member_srl <?php echo e($member['member_srl']); ?> · <?php echo e($member['email_address']); ?></p>
    <p>현재 대여 <?php echo (int)$activeLoans['total']; ?>건 · 전체 기록 <?php echo (int)$loans['total']; ?>건</p>
  </section>

  <section class="card table-wrap">
    <h2>현재 대여 중</h2>
    <table>
      <thead><tr><th>기자재</th><th>실사용자</th><th>대여일</th><th>반납 예정일</th><th>상태</th><th>관리</th></tr></thead>
      <tbody>
      <?php foreach (group_loans_for_display($state, $activeLoans['rows']) as $loan): ?>
        <tr>
          <td><?php echo e($loan['label']); ?><?php echo $loan['count'] > 1 ? ' (' . $loan['count'] . '개)' : ''; ?></td>
          <td><?php echo e($loan['actual_user_name']); ?><br><span class="muted"><?php echo e($loan['actual_user_contact']); ?></span></td>
          <td><?php echo e(date_only($loan['borrowed_at'])); ?></td>
          <td><?php echo e(date_only($loan['due_at'])); ?></td>
          <td><span class="badge <?php echo loan_is_overdue($loan) ? 'overdue' : 'borrowed'; ?>"><?php echo loan_is_overdue($loan) ? '연체' : '대여 중'; ?></span></td>
          <td><?php if (isset($loan['item_id'])): ?><a class="button" href="<?php echo e(app_url('admin/item_history.php?item_id=' . $loan['item_id'])); ?>">기자재 이력</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$activeLoans['rows']): ?><tr><td colspan="6">현재 대여 중인 기자재가 없습니다.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="card table-wrap">
    <h2>전체 대여 기록</h2>
    <table>
      <thead><tr><th>기자재</th><th>대여일</th><th>반납 예정일</th><th>반납일</th><th>상태</th><th>비고</th></tr></thead>
      <tbody>
      <?php foreach (group_loans_for_display($state, $loans['rows']) as $loan): ?>
        <tr>
          <td><?php echo e($loan['label']); ?><?php echo $loan['count'] > 1 ? ' (' . $loan['count'] . '개)' : ''; ?></td>
          <td><?php echo e(date_only($loan['borrowed_at'])); ?></td>
          <td><?php echo e(date_only($loan['due_at'])); ?></td>
          <td><?php echo e($loan['returned_at'] ? date_only($loan['returned_at']) : '-'); ?></td>
          <td><span class="badge <?php echo e($loan['status']); ?>"><?php echo e(loan_is_overdue($loan) ? '연체' : status_label($loan['status'])); ?></span></td>
          <td><?php echo e($loan['admin_note']); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$loans['rows']): ?><tr><td colspan="6">대여 기록이 없습니다.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php render_pagination($loans, 'admin/member_history.php'); ?>
  </section>
<?php elseif ($memberSrl > 0): ?>
  <section class="card">회원을 찾을 수 없습니다.</section>
<?php endif; ?>
<?php render_footer(); ?>
