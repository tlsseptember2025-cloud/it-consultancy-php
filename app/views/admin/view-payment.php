<?php

require_once HELPER_PATH . '/auth.php';

$isMainAdmin      = isset($_SESSION['user']);
$isDemoAdmin      = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}

requireAdminLogin();

if ($isDemoAdmin) {
    require CONFIG_PATH . '/demo-database.php';
    $paymentPdo = $demoPdo;

    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $paymentPdo->prepare("
        SELECT id
        FROM demo_tenants
        WHERE id = ?
          AND status = 'Active'
          AND (expires_at IS NULL OR expires_at >= CURDATE())
        LIMIT 1
    ");
    $tenantStmt->execute([$demoTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminStmt = $paymentPdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
          AND demo_tenant_id = ?
        LIMIT 1
    ");
    $adminStmt->execute([
        (int) ($_SESSION['demo_user']['id'] ?? 0),
        $demoTenantId
    ]);

    if (!$adminStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
} else {
    require CONFIG_PATH . '/database.php';
    $paymentPdo = $pdo;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    $_SESSION['error'] = 'Invalid payment ID.';
    header('Location: ?page=payments');
    exit;
}

if ($isDemoAdmin) {
    $stmt = $paymentPdo->prepare("
        SELECT
            payments.*,
            customers.name AS customer_name,
            customers.email,
            customers.phone,
            services.title AS service_title,
            requests.description AS request_description
        FROM payments
        JOIN requests
            ON requests.id = payments.request_id
        JOIN customers
            ON customers.id = requests.customer_id
        JOIN services
            ON services.id = requests.service_id
        WHERE payments.id = ?
          AND customers.demo_tenant_id = ?
          AND customers.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([$id, $demoTenantId]);
} else {
    $stmt = $paymentPdo->prepare("
        SELECT
            payments.*,
            customers.name AS customer_name,
            customers.email,
            customers.phone,
            services.title AS service_title,
            requests.description AS request_description
        FROM payments
        JOIN requests
            ON requests.id = payments.request_id
        JOIN customers
            ON customers.id = requests.customer_id
        JOIN services
            ON services.id = requests.service_id
        WHERE payments.id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
}

$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    $_SESSION['error'] = 'Payment not found or you do not have access to this payment.';
    header('Location: ?page=payments');
    exit;
}

?><?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Payment Details
        </h2>

        <p>
            <strong>Customer:</strong>
            <?= htmlspecialchars($payment['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= htmlspecialchars($payment['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            <strong>Phone:</strong>
            <?= htmlspecialchars($payment['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <hr>

        <p>
            <strong>Service:</strong>
            <?= htmlspecialchars($payment['service_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            <strong>Amount:</strong>
            $<?= number_format($payment['amount'], 2) ?>
        </p>

        <p>
            <strong>Payment Method:</strong>
            <?= htmlspecialchars(($payment['payment_method'] ?? null) ?: 'Bank Transfer') ?>
        </p>

        <?php if (!empty($payment['stripe_checkout_session_id'])): ?>
            <p>
                <strong>Stripe Checkout Session:</strong>
                <?= htmlspecialchars($payment['stripe_checkout_session_id']) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($payment['stripe_payment_intent_id'])): ?>
            <p>
                <strong>Stripe Payment Intent:</strong>
                <?= htmlspecialchars($payment['stripe_payment_intent_id']) ?>
            </p>
        <?php endif; ?>

        <p>
            <strong>Status:</strong>
            <?= htmlspecialchars($payment['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            <strong>Payment Date:</strong>
            <?= htmlspecialchars($payment['payment_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            <strong>Request:</strong>
        </p>

        <div class="border rounded p-3 mb-3">

            <?= nl2br(htmlspecialchars($payment['request_description'] ?? '', ENT_QUOTES, 'UTF-8')) ?>

        </div>

        <p>
            <strong>Notes:</strong>
        </p>

        <div class="border rounded p-3 mb-3">

            <?= nl2br(htmlspecialchars($payment['notes'] ?? '', ENT_QUOTES, 'UTF-8')) ?>

        </div>

        <a
            href="?page=payments"
            class="btn btn-secondary">

            Back

        </a>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>