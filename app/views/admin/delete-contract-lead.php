<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=public-login');
    exit;
}

blockDemoAction(
    'Deleting contract leads is disabled in the online demo.',
    '?page=customers'
);

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    DELETE FROM contract_leads
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: ?page=contract-leads");
exit;