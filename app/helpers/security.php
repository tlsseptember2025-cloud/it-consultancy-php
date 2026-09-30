<?php

function verifyCustomerRequest(PDO $pdo, int $requestId): void
{
    $isDemoCustomer = isset($_SESSION['demo_customer']);

    if (!$isDemoCustomer && !isset($_SESSION['customer'])) {
        header('Location: ?page=public-login');
        exit;
    }

    $session = $isDemoCustomer
        ? $_SESSION['demo_customer']
        : $_SESSION['customer'];

    $customerId = (int) ($session['id'] ?? 0);

    if ($customerId <= 0) {
        header('Location: ' . ($isDemoCustomer ? '?page=demo-login' : '?page=public-login'));
        exit;
    }

    if ($isDemoCustomer) {
        $stmt = $pdo->prepare("
            SELECT r.id
            FROM requests r
            INNER JOIN customers c
                ON c.id = r.customer_id
            WHERE r.id = ?
              AND r.customer_id = ?
              AND c.is_demo_account = 1
              AND c.demo_tenant_id IS NOT NULL
            LIMIT 1
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM requests
            WHERE id = ?
              AND customer_id = ?
            LIMIT 1
        ");
    }

    $stmt->execute([
        $requestId,
        $customerId
    ]);

    if (!$stmt->fetch()) {
        header('Location: ' . ($isDemoCustomer ? '?page=customer-requests' : '?page=customer-requests'));
        exit;
    }
}
