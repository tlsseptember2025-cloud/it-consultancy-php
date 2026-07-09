<?php

require_once __DIR__ . '/workflow.php';

/**
 * Database Connection
 * Creates the PDO instance used throughout the application.
 */

$host = 'localhost';
$dbname = 'consultancy';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die('Database connection failed');
}