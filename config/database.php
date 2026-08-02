<?php

require_once __DIR__ . '/workflow.php';

$env = parse_ini_file(dirname(__DIR__) . '/.env');

if ($env === false) {
    die('Unable to load .env configuration file.');
}

/**
 * Database Connection
 */

$env = parse_ini_file(dirname(__DIR__) . '/.env');

$host     = $env['DB_HOST'];
$port     = $env['DB_PORT'];
$dbname   = $env['DB_NAME'];
$username = $env['DB_USER'];
$password = $env['DB_PASS'];

$sslCa = dirname(__DIR__) . '/' . $env['DB_SSL_CA'];

try {

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => $sslCa,
    ];

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        $options
    );

} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}