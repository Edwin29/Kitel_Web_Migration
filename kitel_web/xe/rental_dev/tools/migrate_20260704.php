<?php
$root = dirname(__DIR__);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

if (getenv('KITEL_RENTAL_INSTALL') !== '1') {
    echo "Refusing to modify DB.\n";
    echo "Run with KITEL_RENTAL_INSTALL=1 php tools/migrate_20260704.php\n";
    exit(2);
}

require_once $root . '/app/helpers.php';
require_once $root . '/app/db.php';

function migration_column_exists($pdo, $table, $column)
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ');
    $stmt->execute(array($table, $column));
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $pdo = db_connect();
    $table = 'kitel_rental_categories';

    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ');
    $stmt->execute(array($table));
    if ((int)$stmt->fetchColumn() === 0) {
        throw new RuntimeException($table . ' table not found. Run tools/install.php first.');
    }

    if (!migration_column_exists($pdo, $table, 'tracking_mode')) {
        echo "Adding tracking_mode...\n";
        $pdo->exec('ALTER TABLE kitel_rental_categories ADD COLUMN tracking_mode VARCHAR(10) NOT NULL DEFAULT "unique" AFTER description');
    } else {
        echo "tracking_mode already exists.\n";
    }

    if (!migration_column_exists($pdo, $table, 'max_per_user')) {
        echo "Adding max_per_user...\n";
        $pdo->exec('ALTER TABLE kitel_rental_categories ADD COLUMN max_per_user INT UNSIGNED NULL AFTER tracking_mode');
    } else {
        echo "max_per_user already exists.\n";
    }

    $pdo->exec('UPDATE kitel_rental_categories SET tracking_mode = "unique" WHERE tracking_mode IS NULL OR tracking_mode = ""');

    echo "Migration done.\n";
    exit(0);
} catch (Exception $e) {
    echo "Migration failed\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
