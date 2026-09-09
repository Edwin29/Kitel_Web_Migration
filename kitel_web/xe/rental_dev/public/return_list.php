<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();
$state = rental_load();

if (is_post()) {
    require_post();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'remove') {
        cart_remove('return_list', isset($_POST['index']) ? (int)$_POST['index'] : -1);
        redirect_to('return_list.php');
    }
    if ($action === 'clear') {
        cart_clear('return_list');
        flash('반납 목록을 비웠습니다.');
        redirect_to('return_list.php');
    }
    if ($action === 'checkout') {
        require_trusted_network_or_redirect('return_list.php');
        $list = cart_get('return_list');
        if (!$list) {
            flash('반납 목록이 비어 있습니다.');
            redirect_to('return_list.php');
        }
        $memo = isset($_POST['note']) ? $_POST['note'] : '사용자 반납';
        $succeeded = 0;
        $failed = 0;
        $reasons = array();
        foreach ($list as $entry) {
            if ($entry['type'] === 'item') {
                $loan = active_loan_for_item($state, (int)$entry['item_id']);
                if ($loan && return_loan($state, (int)$loan['loan_id'], $user, 'user', $memo)) {
                    $succeeded++;
                } else {
                    $failed++;
                    $reasons[] = $entry['label'] . ': 이미 반납되었거나 처리할 수 없음';
                }
            } else {
                $result = return_bulk_in_category($state, (int)$entry['category_id'], (int)$entry['quantity'], $user, 'user', $memo);
                $succeeded += $result['returned'];
                if ($result['returned'] < $result['requested']) {
                    $failed += ($result['requested'] - $result['returned']);
                    $reasons[] = $entry['name'] . ': 반납 가능한 대여 건이 그새 줄어듦';
                }
            }
        }
        rental_save($state);
        cart_clear('return_list');
        $message = $succeeded . '개 반납이 완료되었습니다.';
        if ($failed > 0) {
            $message .= ' (' . $failed . '개 실패: ' . implode(', ', $reasons) . ')';
        }
        flash($message);
        redirect_to('my.php');
    }
    redirect_to('return_list.php');
}

$list = cart_get('return_list');
render_header('반납 목록');
?>
<h1>반납 목록</h1>
<p class="muted"><a href="<?php echo e(app_url('')); ?>">← 홈으로</a></p>
<?php if (!$list): ?>
  <section class="card">
    <p class="muted">담긴 물품이 없습니다. 반납할 기자재의 QR을 스캔하면 여기에 담깁니다.</p>
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
    <h2>반납 확정</h2>
    <?php if (!client_on_trusted_network()): ?>
      <p class="flash">동방 와이파이에 연결된 상태에서만 반납을 확정할 수 있습니다.</p>
    <?php endif; ?>
    <form class="form" method="post">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="checkout">
      <label>비고 (선택)
        <textarea name="note" rows="3" placeholder="반납 상태 특이사항 등"></textarea>
      </label>
      <button class="primary" type="submit">전체 반납 확정</button>
    </form>
    <form method="post" class="no-print">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="clear">
      <button type="submit" class="danger">목록 비우기</button>
    </form>
  </section>
<?php endif; ?>
<?php render_footer(); ?>
