<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();

if (is_post()) {
    require_post();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'remove') {
        cart_remove('rent_list', isset($_POST['index']) ? (int)$_POST['index'] : -1);
        redirect_to('rent_list.php');
    }
    if ($action === 'clear') {
        cart_clear('rent_list');
        flash('대여 목록을 비웠습니다.');
        redirect_to('rent_list.php');
    }
    if ($action === 'checkout') {
        require_trusted_network_or_redirect('rent_list.php');
        $list = cart_get('rent_list');
        if (!$list) {
            flash('대여 목록이 비어 있습니다.');
            redirect_to('rent_list.php');
        }
        $actualName = isset($_POST['actual_user_name']) ? $_POST['actual_user_name'] : '';
        $actualContact = isset($_POST['actual_user_contact']) ? $_POST['actual_user_contact'] : '';
        $note = isset($_POST['note']) ? $_POST['note'] : '';
        $dueDate = default_due_date();
        $succeeded = 0;
        $failed = 0;
        $reasons = array();
        foreach ($list as $entry) {
            try {
                if ($entry['type'] === 'item') {
                    create_loan($state, (int)$entry['item_id'], $actualName, $actualContact, $dueDate, $note, $user, $user);
                    $succeeded++;
                } else {
                    $result = create_bulk_loan($state, (int)$entry['category_id'], (int)$entry['quantity'], $actualName, $actualContact, $dueDate, $note, $user, $user);
                    $succeeded += $result['created'];
                    if ($result['created'] < $result['requested']) {
                        $failed += ($result['requested'] - $result['created']);
                        $reasons[] = $entry['name'] . ': ' . $result['reason'];
                    }
                }
            } catch (RuntimeException $e) {
                $failed++;
                $label = isset($entry['label']) ? $entry['label'] : $entry['name'];
                $reasons[] = $label . ': ' . $e->getMessage();
            }
        }
        rental_save($state);
        cart_clear('rent_list');
        $message = $succeeded . '개 대여 신청이 완료되었습니다.';
        if ($failed > 0) {
            $message .= ' (' . $failed . '개 실패: ' . implode(', ', $reasons) . ')';
        }
        flash($message);
        redirect_to('my.php');
    }
    redirect_to('rent_list.php');
}

$list = cart_get('rent_list');
render_header('대여 목록');
?>
<h1>대여 목록</h1>
<p class="muted"><a href="<?php echo e(app_url('')); ?>">← 홈으로</a></p>
<?php if (!$list): ?>
  <section class="card">
    <p class="muted">담긴 물품이 없습니다. 대여할 기자재의 QR을 스캔하면 여기에 담깁니다.</p>
    <p><a class="button" href="<?php echo e(app_url('')); ?>">홈으로</a></p>
  </section>
<?php else: ?>
  <section class="card table-wrap">
    <table>
      <thead><tr><th>물품</th><th>수량</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $index => $entry): ?>
        <tr>
          <td><?php echo e($entry['type'] === 'item' ? $entry['label'] : $entry['name']); ?></td>
          <td><?php echo $entry['type'] === 'item' ? '1개' : (int)$entry['quantity'] . '개'; ?></td>
          <td>
            <form method="post" class="inline-form">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="index" value="<?php echo (int)$index; ?>">
              <button type="submit">빼기</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>대여 신청 정보</h2>
    <?php if (!client_on_trusted_network()): ?>
      <p class="flash">동방 와이파이에 연결된 상태에서만 신청을 완료할 수 있습니다.</p>
    <?php endif; ?>
    <form class="form" method="post">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="checkout">
      <label>대여 명의자
        <input value="<?php echo e($user['nick_name']); ?>" disabled>
      </label>
      <label>실제 사용자 이름
        <input name="actual_user_name" placeholder="직접 사용하는 사람 이름">
      </label>
      <label>실제 사용자 연락처
        <input name="actual_user_contact" placeholder="010-0000-0000">
      </label>
      <p class="muted">반납 예정일은 대여일로부터 <?php echo (int)config('default_due_days'); ?>일 뒤로 자동 설정됩니다.</p>
      <label>비고
        <textarea name="note" rows="3"></textarea>
      </label>
      <button class="primary" type="submit">전체 대여 신청</button>
    </form>
    <form method="post" class="no-print">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="clear">
      <button type="submit" class="danger">목록 비우기</button>
    </form>
  </section>
<?php endif; ?>
<?php render_footer(); ?>
