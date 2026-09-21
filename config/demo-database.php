<?php

require_once __DIR__ . '/workflow.php';

/**
 * Demo Database Configuration
 *
 * Demo database credentials are loaded from the server .env file.
 */


/**
 * Load Environment Configuration
 */

$envFile = dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {
    die('Unable to load environment configuration.');
}

$env = parse_ini_file($envFile);

if ($env === false) {
    die('Unable to load environment configuration.');
}


/**
 * Demo Database Configuration
 */

$host     = getenv('DEMO_DB_HOST')     ?: ($env['DEMO_DB_HOST'] ?? '');
$port     = getenv('DEMO_DB_PORT')     ?: ($env['DEMO_DB_PORT'] ?? '');
$dbname   = getenv('DEMO_DB_NAME')     ?: ($env['DEMO_DB_NAME'] ?? '');
$username = getenv('DEMO_DB_USER')     ?: ($env['DEMO_DB_USER'] ?? '');
$password = getenv('DEMO_DB_PASS')     ?: ($env['DEMO_DB_PASS'] ?? '');
$sslCa    = getenv('DEMO_DB_SSL_CA')   ?: ($env['DEMO_DB_SSL_CA'] ?? '');


/**
 * Validate Required Configuration
 */

if (
    empty($host) ||
    empty($port) ||
    empty($dbname) ||
    empty($username) ||
    empty($password) ||
    empty($sslCa)
) {
    die('Demo database configuration is missing.');
}


/**
 * SSL Certificate
 */

$sslCaPath = dirname(__DIR__) . '/' . ltrim($sslCa, '/');

if (!file_exists($sslCaPath)) {
    die('Demo CA certificate not found: ' . $sslCaPath);
}


/**
 * Demo Database Connection
 */

try {

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_SSL_CA => $sslCaPath,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

    $demoPdo = new PDO(
        $dsn,
        $username,
        $password,
        $options
    );


    /**
     * Set MySQL Session Timezone
     */

    $demoPdo->exec("
        SET time_zone = '+04:00'
    ");


} catch (PDOException $e) {

    die('Demo database connection failed: ' . $e->getMessage());

}