<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    DELETE FROM contract_leads
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: ?page=contract-leads");
exit;