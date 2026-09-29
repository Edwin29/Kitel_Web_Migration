<?php
/** Fail closed before either setup script connects to a database. */
declare(strict_types=1);

function kitelDevConfig(string $requestedRoot): array
{
    $expectedRoot = realpath('D:/rhymix_dev/www/rhymix');
    $root = realpath($requestedRoot);
    if (!$expectedRoot || !$root || strcasecmp($root, $expectedRoot) !== 0) {
        throw new RuntimeException('Refusing a root other than D:/rhymix_dev/www/rhymix.');
    }
    $config = include $root . '/files/config/config.php';
    kitelAssertDevDatabase($config['db']['master'] ?? []);
    return [$root, $config];
}

function kitelAssertDevDatabase(array $db): void
{
    if (!in_array($db['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
        || (int)($db['port'] ?? 0) !== 3307
        || ($db['database'] ?? '') !== 'rhymix_dev'
        || ($db['prefix'] ?? '') !== 'rx_'
        || ($db['user'] ?? '') !== 'rhymix') {
        throw new RuntimeException('Refusing a database other than local rhymix_dev:3307 (rx_, rhymix).');
    }
}
