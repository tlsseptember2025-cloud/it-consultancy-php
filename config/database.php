<?php

require_once __DIR__ . '/workflow.php';

/**
 * Load Environment Configuration
 */
$envFile = dirname(__DIR__) . '/.env';

if (!is_file($envFile)) {
    die('Database configuration unavailable.');
}

$env = parse_ini_file($envFile, false, INI_SCANNER_RAW);

if ($env === false) {
    die('Database configuration unavailable.');
}

$host     = trim((string) ($env['DB_HOST'] ?? ''));
$port     = trim((string) ($env['DB_PORT'] ?? ''));
$dbname   = trim((string) ($env['DB_NAME'] ?? ''));
$username = (string) ($env['DB_USER'] ?? '');
$password = (string) ($env['DB_PASS'] ?? '');
$caValue  = trim((string) ($env['DB_SSL_CA'] ?? ''));

if ($host === '' || $port === '' || $dbname === '' || $username === '' || $caValue === '') {
    die('Database configuration is incomplete.');
}

$sslCa = dirname(__DIR__) . '/' . ltrim($caValue, '/\\');

if (!is_file($sslCa)) {
    die('Database SSL certificate unavailable.');
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_SSL_CA => $sslCa,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
    $pdo->exec("SET time_zone = '+04:00'");

} catch (PDOException $e) {
    error_log('Main database connection failed: ' . $e->getMessage());
    die('Database connection failed.');
}
