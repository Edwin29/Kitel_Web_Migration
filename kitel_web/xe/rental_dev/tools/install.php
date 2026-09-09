<?php
$root = dirname(__DIR__);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

if (getenv('KITEL_RENTAL_INSTALL') !== '1') {
    echo "Refusing to modify DB.\n";
    echo "Run with KITEL_RENTAL_INSTALL=1 php tools/install.php [--seed]\n";
    exit(2);
}

require_once $root . '/app/helpers.php';
require_once $root . '/app/db.php';

$withSeed = in_array('--seed', $argv, true);
$files = array($root . '/database/schema.sql');
if ($withSeed) {
    $files[] = $root . '/database/seed.sql';
}

try {
    $pdo = db_connect();
    foreach ($files as $file) {
        if (!is_file($file)) {
            throw new RuntimeException('SQL file missing: ' . basename($file));
        }
        echo "Applying " . basename($file) . "\n";
        foreach (split_sql_statements(file_get_contents($file)) as $sql) {
            if (trim($sql) !== '') {
                $pdo->exec($sql);
            }
        }
    }
    echo "Done\n";
    exit(0);
} catch (Exception $e) {
    echo "Install failed\n";
    exit(1);
}

function split_sql_statements($sql)
{
    $statements = array();
    $buffer = '';
    $quote = null;
    $escape = false;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;
        if ($escape) {
            $escape = false;
            continue;
        }
        if ($char === '\\') {
            $escape = true;
            continue;
        }
        if ($quote !== null) {
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"') {
            $quote = $char;
            continue;
        }
        if ($char === ';') {
            $statements[] = substr($buffer, 0, -1);
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }
    return $statements;
}
