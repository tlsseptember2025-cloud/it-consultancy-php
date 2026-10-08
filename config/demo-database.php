<?php

require_once __DIR__ . '/workflow.php';

/**
 * Demo database configuration is loaded from environment variables.
 */
$envFile = dirname(__DIR__) . '/.env';

if (!is_file($envFile)) {
    die('Demo database configuration unavailable.');
}

$env = parse_ini_file($envFile, false, INI_SCANNER_RAW);

if ($env === false) {
    die('Demo database configuration unavailable.');
}

$host     = trim((string) (getenv('DEMO_DB_HOST') ?: ($env['DEMO_DB_HOST'] ?? '')));
$port     = trim((string) (getenv('DEMO_DB_PORT') ?: ($env['DEMO_DB_PORT'] ?? '')));
$dbname   = trim((string) (getenv('DEMO_DB_NAME') ?: ($env['DEMO_DB_NAME'] ?? '')));
$username = (string) (getenv('DEMO_DB_USER') ?: ($env['DEMO_DB_USER'] ?? ''));
$password = (string) (getenv('DEMO_DB_PASS') ?: ($env['DEMO_DB_PASS'] ?? ''));
$sslValue = trim((string) (getenv('DEMO_DB_SSL_CA') ?: ($env['DEMO_DB_SSL_CA'] ?? '')));

if ($host === '' || $port === '' || $dbname === '' || $username === '' || $sslValue === '') {
    die('Demo database configuration is incomplete.');
}

$sslCaPath = dirname(__DIR__) . '/' . ltrim($sslValue, '/\\');

if (!is_file($sslCaPath)) {
    die('Demo database SSL certificate unavailable.');
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_SSL_CA => $sslCaPath,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ];

    $demoPdo = new PDO($dsn, $username, $password, $options);
    $demoPdo->exec("SET time_zone = '+04:00'");

} catch (PDOException $e) {
    error_log('Demo database connection failed: ' . $e->getMessage());
    die('Demo database connection failed.');
}
