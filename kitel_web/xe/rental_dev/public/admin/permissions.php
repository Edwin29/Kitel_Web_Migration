<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();
$state = rental_load();
if (is_post()) {
    require_post();
    try {
        save_allowed_group(
            $state,
            isset($_POST['group_srl']) ? $_POST['group_srl'] : 0,
            isset($_POST['group_title_snapshot']) ? $_POST['group_title_snapshot'] : '',
            isset($_POST['permission_type']) ? $_POST['permission_type'] : '',
            isset($_POST['active']) ? 1 : 0,
            $user
        );
        rental_save($state);
        flash('권한 그룹을 저장했습니다.');
    } catch (RuntimeException $e) {
        flash($e->getMessage());
    }
    redirect_to('admin/permissions.php');
}
$rows = allowed_group_rows($state);
render_header('권한 그룹 관리', true);
admin_nav();
?>
<h1>권한 그룹 관리</h1>
<div class="two">
  <section class="card">
    <h2>그룹 추가/수정</h2>
    <form class="form" method="post">
      <?php echo csrf_input(); ?>
      <label>XE group_srl
        <input type="number" name="group_srl" min="1" required>
      </label>
      <label>그룹명
        <input name="group_title_snapshot" required placeholder="정회원">
      </label>
      <label>권한 종류
        <select name="permission_type" required>
          <option value="user">일반 대여</option>
          <option value="admin">관리자</option>
          <option value="staff">보조 권한</option>
        </select>
      </label>
      <label><input type="checkbox" name="active" value="1" checked> 활성</label>
      <button class="primary" type="submit">저장</button>
    </form>
  </section>
  <section class="card table-wrap">
    <h2>현재 설정</h2>
    <table>
      <thead><tr><th>group_srl</th><th>그룹명</th><th>권한</th><th>활성</th><th>관리</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): $formId = 'permission-' . e($row['permission_type']) . '-' . e($row['group_srl']); ?>
        <tr>
          <td>
            <form id="<?php echo $formId; ?>" method="post">
              <?php echo csrf_input(); ?>
              <input type="number" name="group_srl" value="<?php echo e($row['group_srl']); ?>" min="1" required>
            </form>
          </td>
          <td><input form="<?php echo $formId; ?>" name="group_title_snapshot" value="<?php echo e(isset($row['group_title_snapshot']) ? $row['group_title_snapshot'] : ''); ?>" required></td>
          <td>
            <select form="<?php echo $formId; ?>" name="permission_type">
              <?php foreach (array('user' => '일반 대여', 'admin' => '관리자', 'staff' => '보조 권한') as $value => $label): ?>
                <option value="<?php echo e($value); ?>" <?php echo $row['permission_type'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input form="<?php echo $formId; ?>" type="checkbox" name="active" value="1" <?php echo (int)$row['active'] === 1 ? 'checked' : ''; ?>></td>
          <td><button form="<?php echo $formId; ?>" type="submit">저장</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
<?php render_footer(); ?>
