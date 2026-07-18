<?php

function verifyCustomerRequest(PDO $pdo, int $requestId): void
{
    if (!isset($_SESSION['customer'])) {
        header('Location: ?page=public-login');
        exit;
    }

    $customerId = $_SESSION['customer']['id'];

    $stmt = $pdo->prepare("
        SELECT id
        FROM requests
        WHERE id = ?
          AND customer_id = ?
    ");

    $stmt->execute([
        $requestId,
        $customerId
    ]);

    if (!$stmt->fetch()) {
        header('Location: ?page=customer-requests');
        exit;
    }
}