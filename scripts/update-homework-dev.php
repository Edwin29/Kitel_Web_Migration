<?php
/** Local-only additive schema update for Homework. Dry run by default. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$module = $pdo->query("SELECT module_srl, mid, module FROM rx_modules WHERE mid = 'homework'")->fetch(PDO::FETCH_ASSOC);
if (!$module || $module['module'] !== 'homework') {
    throw new RuntimeException('Expected the local homework module; no change made.');
}
$columns = [
    'rx_homework_task' => [
        'answer_fields' => 'TEXT NULL',
        'allowed_extensions' => 'VARCHAR(250) NULL',
        'description_image' => 'VARCHAR(250) NULL',
        'is_visible' => "CHAR(1) NOT NULL DEFAULT 'Y'",
    ],
    'rx_homework_submission' => ['answers' => 'TEXT NULL'],
];
$pending = [];
foreach ($columns as $table => $definitions) {
    foreach ($definitions as $column => $type) {
        $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $query->execute([$db['database'], $table, $column]);
        if (!(int)$query->fetchColumn()) $pending[] = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$type}";
    }
}
echo "Development homework module #{$module['module_srl']}\n";
foreach ($pending as $statement) echo $statement . "\n";
if (!in_array('--apply', $argv, true)) {
    echo "DRY RUN. Pass --apply to add missing columns in the guarded local database.\n";
    exit;
}
foreach ($pending as $statement) $pdo->exec($statement);
echo "APPLIED. Existing task and submission records were preserved.\n";
