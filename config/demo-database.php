<?php

require_once __DIR__ . '/workflow.php';

/**
 * Demo Database Configuration
 *
 * Demo database credentials are supplied through the server/runtime
 * environment. They must not be stored in the repository .env file.
 */

$host     = getenv('DEMO_DB_HOST');
$port     = getenv('DEMO_DB_PORT');
$dbname   = getenv('DEMO_DB_NAME');
$username = getenv('DEMO_DB_USER');
$password = getenv('DEMO_DB_PASS');
$sslCa    = getenv('DEMO_DB_SSL_CA');


/**
 * Validate Required Configuration
 */

if (
    $host === false ||
    $port === false ||
    $dbname === false ||
    $username === false ||
    $password === false ||
    $sslCa === false
) {
    die('Demo database configuration is missing.');
}


/**
 * SSL Certificate
 */

$sslCaPath = dirname(__DIR__) . '/' . ltrim($sslCa, '/');


/**
 * Demo Database Connection
 */

try {

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => $sslCaPath,
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
