<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();

// 이 파일은 SSH/phpMyAdmin 없이 브라우저로 마이그레이션을 실행하기 위한 1회성 도구입니다.
// 실행이 끝나고 정상 동작을 확인했으면 이 파일은 삭제하세요.

function bundle_migration_table_exists($pdo, $table)
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ');
    $stmt->execute(array($table));
    return (int)$stmt->fetchColumn() > 0;
}

$result = null;
$error = null;

if (is_post()) {
    require_post();
    try {
        $pdo = db_connect();

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS kitel_rental_bundles (
              bundle_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              name VARCHAR(100) NOT NULL,
              public_code VARCHAR(40) NOT NULL,
              description TEXT NULL,
              sort_order INT UNSIGNED NOT NULL DEFAULT 0,
              is_active TINYINT(1) NOT NULL DEFAULT 1,
              created_by_member_srl BIGINT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (bundle_id),
              UNIQUE KEY uniq_bundle_public_code (public_code),
              KEY idx_bundles_active_sort (is_active, sort_order, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS kitel_rental_bundle_categories (
              bundle_id INT UNSIGNED NOT NULL,
              category_id INT UNSIGNED NOT NULL,
              sort_order INT UNSIGNED NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (bundle_id, category_id),
              UNIQUE KEY uniq_bundle_category_once (category_id),
              KEY idx_bundle_categories_sort (bundle_id, sort_order),
              CONSTRAINT fk_bundle_categories_bundle FOREIGN KEY (bundle_id)
                REFERENCES kitel_rental_bundles (bundle_id) ON DELETE CASCADE,
              CONSTRAINT fk_bundle_categories_category FOREIGN KEY (category_id)
                REFERENCES kitel_rental_categories (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        $result = array(
            'bundles' => bundle_migration_table_exists($pdo, 'kitel_rental_bundles'),
            'bundle_categories' => bundle_migration_table_exists($pdo, 'kitel_rental_bundle_categories'),
        );
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pdoCheck = null;
$existing = array('bundles' => false, 'bundle_categories' => false);
try {
    $pdoCheck = db_connect();
    $existing['bundles'] = bundle_migration_table_exists($pdoCheck, 'kitel_rental_bundles');
    $existing['bundle_categories'] = bundle_migration_table_exists($pdoCheck, 'kitel_rental_bundle_categories');
} catch (Exception $e) {
    // 연결 자체가 안 되면 아래 화면에서 그대로 안내
}

render_header('번들 마이그레이션', true);
admin_nav();
?>
<h1>번들(bundle) 테이블 마이그레이션</h1>
<section class="card">
  <p class="muted">SSH나 phpMyAdmin 없이 이 페이지에서 바로 <code>kitel_rental_bundles</code>, <code>kitel_rental_bundle_categories</code> 테이블을 생성합니다. 여러 번 눌러도 안전합니다(이미 있으면 건너뜀).</p>

  <?php if ($error): ?>
    <p class="import-status error"><?php echo e($error); ?></p>
  <?php elseif ($result): ?>
    <p class="import-status ok">완료되었습니다. kitel_rental_bundles: <?php echo $result['bundles'] ? '있음' : '실패'; ?> · kitel_rental_bundle_categories: <?php echo $result['bundle_categories'] ? '있음' : '실패'; ?></p>
  <?php endif; ?>

  <p>현재 상태 — kitel_rental_bundles: <strong><?php echo $existing['bundles'] ? '있음' : '없음'; ?></strong> · kitel_rental_bundle_categories: <strong><?php echo $existing['bundle_categories'] ? '있음' : '없음'; ?></strong></p>

  <form method="post">
    <?php echo csrf_input(); ?>
    <button class="primary" type="submit">마이그레이션 실행</button>
  </form>

  <p class="muted">두 테이블 모두 "있음"으로 뜨면 끝난 겁니다. 확인 후 이 파일(<code>public/admin/run_bundle_migration.php</code>)은 삭제해 주세요.</p>
</section>
<?php render_footer(); ?>
