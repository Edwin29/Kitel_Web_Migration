<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();

// SSH/phpMyAdmin 없이 브라우저로 마이그레이션을 실행하기 위한 1회성 도구입니다.
// (2026_08_add_item_display_no.sql 대응)
// 실행이 끝나고 정상 동작을 확인했으면 이 파일은 삭제하세요.

function display_no_migration_column_exists($pdo, $table, $column)
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ');
    $stmt->execute(array($table, $column));
    return (int)$stmt->fetchColumn() > 0;
}

$log = array();
$error = null;

if (is_post()) {
    require_post();
    try {
        $pdo = db_connect();

        if (!display_no_migration_column_exists($pdo, 'kitel_rental_items', 'display_no')) {
            $pdo->exec('ALTER TABLE kitel_rental_items ADD COLUMN display_no INT UNSIGNED NULL AFTER serial_no');
            $log[] = 'display_no 컬럼을 추가했습니다.';
        } else {
            $log[] = 'display_no 컬럼이 이미 있어서 건너뛰었습니다.';
        }

        $updated = $pdo->exec('UPDATE kitel_rental_items SET display_no = serial_no WHERE display_no IS NULL');
        $log[] = 'display_no가 비어 있던 ' . (int)$updated . '개 행을 serial_no 값으로 채웠습니다.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$existing = false;
$nullCount = null;
try {
    $pdoCheck = db_connect();
    $existing = display_no_migration_column_exists($pdoCheck, 'kitel_rental_items', 'display_no');
    if ($existing) {
        $stmt = $pdoCheck->query('SELECT COUNT(*) FROM kitel_rental_items WHERE display_no IS NULL');
        $nullCount = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    if (!$error) {
        $error = $e->getMessage();
    }
}

render_header('display_no 마이그레이션', true);
admin_nav();
?>
<h1>기자재 표시번호(display_no) 마이그레이션</h1>
<section class="card">
  <p class="muted">SSH나 phpMyAdmin 없이 이 페이지에서 <code>kitel_rental_items.display_no</code> 컬럼을 추가하고, 비어 있는 값을 <code>serial_no</code>로 채웁니다. 여러 번 눌러도 안전합니다.</p>

  <?php if ($error): ?>
    <p class="import-status error"><?php echo e($error); ?></p>
  <?php endif; ?>
  <?php foreach ($log as $line): ?>
    <p class="import-status ok"><?php echo e($line); ?></p>
  <?php endforeach; ?>

  <p>
    현재 상태 — display_no 컬럼: <strong><?php echo $existing ? '있음' : '없음'; ?></strong>
    <?php if ($existing): ?> · 비어 있는 행: <strong><?php echo $nullCount; ?>개</strong><?php endif; ?>
  </p>

  <form method="post">
    <?php echo csrf_input(); ?>
    <button class="primary" type="submit">마이그레이션 실행</button>
  </form>

  <p class="muted">컬럼이 "있음"이고 비어 있는 행이 0개면 끝난 겁니다. 확인 후 이 파일(<code>public/admin/run_display_no_migration.php</code>)은 삭제해 주세요.</p>
</section>
<?php render_footer(); ?>
