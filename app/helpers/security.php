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
        header('Location: ?page=' . ($isDemoCustomer ? 'demo-login' : 'public-login'));
        exit;
    }

    if ($isDemoCustomer) {
        $demoTenantId = (int) ($session['demo_tenant_id'] ?? 0);

        if ($demoTenantId <= 0) {
            unset($_SESSION['demo_customer']);
            header('Location: ?page=demo-login');
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT r.id
            FROM requests r
            INNER JOIN customers c
                ON c.id = r.customer_id
            INNER JOIN services s
                ON s.id = r.service_id
            INNER JOIN demo_tenants t
                ON t.id = c.demo_tenant_id
            WHERE r.id = ?
              AND r.customer_id = ?
              AND c.demo_tenant_id = ?
              AND c.is_demo_account = 1
              AND s.demo_tenant_id = ?
              AND t.status = 'Active'
              AND (
                  t.expires_at IS NULL
                  OR t.expires_at > NOW()
              )
            LIMIT 1
        ");

        $stmt->execute([
            $requestId,
            $customerId,
            $demoTenantId,
            $demoTenantId
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM requests
            WHERE id = ?
              AND customer_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $requestId,
            $customerId
        ]);
    }

    if (!$stmt->fetch()) {
        header('Location: ?page=customer-requests');
        exit;
    }
}
