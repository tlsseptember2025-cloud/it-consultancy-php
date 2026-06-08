<?php

$host = 'db5020646106.hosting-data.io';
$dbname = 'dbs15765712';
$username = 'dbu1056528';
$password = 'Fatima@2020';

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {

    die("Database connection failed");

}