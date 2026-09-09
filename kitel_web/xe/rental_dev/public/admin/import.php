<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();

// CSV 가져오기 — 미리보기 후 적용.
//
// 예전에는 붙여넣고 버튼을 누르면 곧바로 생성됐다. 물품현황표의 "현재 수량"을
// 증분으로 해석해서, 시트를 갱신해 다시 넣으면 8개가 16개가 됐다.
// 이제는 무엇이 일어날지 먼저 보여주고, 적용은 그 화면에서 한 번 더 눌러야 한다.
//
// 적용 단계에서도 계획을 CSV 원문에서 다시 세운다. 미리보기가 돌려준 목록을
// 그대로 믿지 않는다(폼을 조작해 엉뚱한 항목을 폐기시키는 것을 막기 위함).

$csv = isset($_POST['csv_text']) ? (string)$_POST['csv_text'] : '';
$mode = (isset($_POST['mode']) && $_POST['mode'] === 'sync') ? 'sync' : 'add';
$allowRetire = !empty($_POST['allow_retire']);
$stage = isset($_POST['stage']) ? $_POST['stage'] : '';
$plan = null;

if (is_post()) {
    require_post();
    if (trim($csv) === '') {
        flash('가져올 내용이 비어 있습니다.');
        redirect_to('admin/item_new.php?tab=csv');
    }

    $plan = import_build_plan($state, $csv, $mode, $allowRetire);

    if ($stage === 'apply') {
        if (import_plan_is_noop($plan)) {
            flash('적용할 변경이 없습니다.');
            redirect_to('admin/item_new.php?tab=csv');
        }
        $result = import_apply_plan($state, $plan, $user);
        $_SESSION['last_added_item_ids'] = $result['created_ids'];
        rental_save($state);

        if (!$result['ok']) {
            flash('가져오기를 취소했습니다 (아무것도 반영되지 않았습니다) — ' . $result['message']);
            redirect_to('admin/item_new.php?tab=csv');
        }
        $parts = array();
        if ($result['created']) { $parts[] = $result['created'] . '개 추가'; }
        if ($result['updated']) { $parts[] = $result['updated'] . '건 수정'; }
        if ($result['retired']) { $parts[] = $result['retired'] . '개 폐기'; }
        $message = $parts ? implode(' · ', $parts) . ' 완료했습니다.' : '변경 사항이 없습니다.';
        if ($plan['errors']) {
            $message .= ' 일부 행은 건너뛰었습니다: ' . implode(' / ', $plan['errors']);
        }
        flash($message);
        redirect_to('admin/items.php');
    }
}

if (!$plan) {
    redirect_to('admin/item_new.php?tab=csv');
}

$shapeLabel = $plan['shape'] === IMPORT_SHAPE_ITEMS ? '개체 단위 (item_id 포함)' : '수량 단위 (카테고리별 개수)';
$withheld = isset($plan['retires_withheld']) ? $plan['retires_withheld'] : array();

render_header('가져오기 미리보기', true);
admin_nav();
?>
<h1>가져오기 미리보기</h1>
<p class="muted">아직 아무것도 반영되지 않았습니다. 아래 내용을 확인한 뒤 맨 아래에서 적용해 주세요.</p>

<section class="card">
  <h2>해석 결과</h2>
  <p>
    형식: <strong><?php echo e($shapeLabel); ?></strong> ·
    모드: <strong><?php echo $plan['mode'] === 'sync' ? '맞추기 (시트 상태에 맞춤)' : '추가 (시트 수량만큼 새로 만듦)'; ?></strong>
  </p>
  <p class="import-summary">
    <span class="badge available">추가 <?php echo (int)$plan['total_new_items']; ?></span>
    <span class="badge borrowed">수정 <?php echo count($plan['updates']); ?></span>
    <span class="badge broken">폐기 <?php echo count($plan['retires']); ?></span>
    <span class="badge">변경 없음 <?php echo (int)$plan['unchanged']; ?></span>
  </p>
</section>

<?php if ($plan['errors']): ?>
<section class="card">
  <h2>건너뛴 행 <span class="muted">(<?php echo count($plan['errors']); ?>건)</span></h2>
  <ul class="muted">
    <?php foreach ($plan['errors'] as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($plan['new_categories']): ?>
<section class="card">
  <h2>새로 만들어질 카테고리 <span class="muted">(<?php echo count($plan['new_categories']); ?>개)</span></h2>
  <p class="muted">기존 카테고리와 이름이 조금이라도 다르면 새 카테고리가 됩니다. 오타가 아닌지 확인해 주세요.</p>
  <p><?php foreach ($plan['new_categories'] as $name): ?><span class="badge borrowed"><?php echo e($name); ?></span> <?php endforeach; ?></p>
</section>
<?php endif; ?>

<?php if ($plan['creates']): ?>
<section class="card table-wrap">
  <h2>추가</h2>
  <table>
    <thead><tr><th>카테고리</th><th>개수</th><?php if ($plan['shape'] === IMPORT_SHAPE_QUANTITY): ?><th>현재 → 이후</th><?php endif; ?><th>위치</th><th>특이사항</th></tr></thead>
    <tbody>
    <?php foreach ($plan['creates'] as $create): ?>
      <tr>
        <td><?php echo e($create['category']); ?></td>
        <td>+<?php echo (int)$create['quantity']; ?></td>
        <?php if ($plan['shape'] === IMPORT_SHAPE_QUANTITY): ?>
          <td><?php echo (int)$create['current']; ?> → <strong><?php echo (int)$create['after']; ?></strong></td>
        <?php endif; ?>
        <td><?php echo e($create['location']); ?></td>
        <td><?php echo e($create['note']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<?php if ($plan['updates']): ?>
<section class="card table-wrap">
  <h2>수정</h2>
  <table>
    <thead><tr><th>기자재</th><th>바뀌는 값</th><th>무시되는 값</th></tr></thead>
    <tbody>
    <?php foreach ($plan['updates'] as $update): ?>
      <tr>
        <td><a href="<?php echo e(app_url('admin/item_history.php?item_id=' . (int)$update['item_id'])); ?>"><?php echo e($update['label']); ?></a></td>
        <td>
          <?php if ($update['changes']): ?>
            <?php foreach ($update['changes'] as $field => $pair): ?>
              <div><span class="muted"><?php echo e($field === 'location' ? '위치' : '특이사항'); ?>:</span>
                <?php echo e($pair[0] !== '' ? $pair[0] : '(빈값)'); ?> → <strong><?php echo e($pair[1] !== '' ? $pair[1] : '(빈값)'); ?></strong></div>
            <?php endforeach; ?>
          <?php else: ?><span class="muted">-</span><?php endif; ?>
        </td>
        <td class="muted"><?php echo $update['ignored'] ? e(implode(', ', $update['ignored'])) : '-'; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted">카테고리와 상태는 CSV로 바꾸지 않습니다. 카테고리 이동은 기자재 관리 화면에서, 상태 변경은 사유가 필요하므로 각 화면에서 해 주세요.</p>
</section>
<?php endif; ?>

<?php if ($plan['retires'] || $withheld): ?>
<section class="card table-wrap">
  <h2 class="danger-head">폐기 <span class="muted">(<?php echo count($plan['retires']) + count($withheld); ?>개)</span></h2>
  <?php if ($withheld): ?>
    <p class="import-status error">아래 항목은 <strong>아직 폐기 대상에 포함되지 않았습니다.</strong> 폐기까지 반영하려면 아래 "시트에 없는 항목을 폐기" 를 체크한 뒤 다시 미리보기를 눌러 주세요.</p>
  <?php endif; ?>
  <table>
    <thead><tr><th>기자재</th><th>사유</th></tr></thead>
    <tbody>
    <?php foreach (array_merge($plan['retires'], $withheld) as $retire): ?>
      <tr>
        <td><a href="<?php echo e(app_url('admin/item_history.php?item_id=' . (int)$retire['item_id'])); ?>"><?php echo e($retire['label']); ?></a></td>
        <td class="muted"><?php echo e($retire['reason']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<?php if ($plan['blocked']): ?>
<section class="card table-wrap">
  <h2>처리할 수 없는 항목</h2>
  <table>
    <thead><tr><th>대상</th><th>사유</th></tr></thead>
    <tbody>
    <?php foreach ($plan['blocked'] as $blocked): ?>
      <tr><td><?php echo e($blocked['label']); ?></td><td class="muted"><?php echo e($blocked['reason']); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted">대여 중인 기자재는 폐기할 수 없습니다. 먼저 반납 처리한 뒤 다시 가져오기를 해 주세요.</p>
</section>
<?php endif; ?>

<section class="card">
  <h2>적용</h2>
  <?php if (import_plan_is_noop($plan)): ?>
    <p class="muted">반영할 변경이 없습니다.</p>
    <a class="button" href="<?php echo e(app_url('admin/item_new.php?tab=csv')); ?>">← 돌아가기</a>
  <?php else: ?>
    <form method="post" onsubmit="return confirm('위 내용을 실제로 반영합니다. 계속할까요?');">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="stage" value="apply">
      <input type="hidden" name="mode" value="<?php echo e($plan['mode']); ?>">
      <?php if ($allowRetire): ?><input type="hidden" name="allow_retire" value="1"><?php endif; ?>
      <input type="hidden" name="csv_text" value="<?php echo e($csv); ?>">
      <button class="primary" type="submit">이대로 적용</button>
      <a class="button" href="<?php echo e(app_url('admin/item_new.php?tab=csv')); ?>">취소하고 돌아가기</a>
    </form>
    <p class="muted">전체가 하나의 트랜잭션으로 처리됩니다 — 도중에 실패하면 아무것도 반영되지 않습니다.</p>
  <?php endif; ?>
</section>
<?php render_footer(); ?>
