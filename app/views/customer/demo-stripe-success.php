<?php

require_once CONFIG_PATH . '/demo-database.php';
require_once HELPER_PATH . '/demo_stripe_payment.php';

if (!isset($_SESSION['demo_customer'])) {
    http_response_code(403);
    exit('Demo customer access required.');
}

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$paid = false;
$requestId = 0;
$customerId = (int) ($_SESSION['demo_customer']['id'] ?? 0);
$demoTenantId = (int) ($_SESSION['demo_customer']['demo_tenant_id'] ?? 0);

if ($sessionId !== '' && $customerId > 0 && $demoTenantId > 0) {
    try {
        $session = demoStripeApiRequest(
            'GET',
            'checkout/sessions/' . rawurlencode($sessionId)
        );

        $requestId = (int) ($session['metadata']['request_id'] ?? 0);
        $sessionCustomerId = (int) ($session['metadata']['customer_id'] ?? 0);
        $sessionTenantId = (int) ($session['metadata']['demo_tenant_id'] ?? 0);

        $paid = ($session['payment_status'] ?? '') === 'paid'
            && $sessionCustomerId === $customerId
            && $sessionTenantId === $demoTenantId;

        if ($paid) {
            demoStripeCompleteCheckoutPayment($demoPdo, $session);
        }
    } catch (Throwable $e) {
        error_log('Demo Stripe success verification failed: ' . $e->getMessage());
    }
}

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<div class="card shadow-sm">
    <div class="card-body text-center py-5">

        <?php if ($paid): ?>
            <div class="text-success mb-3" style="font-size:3rem;">✓</div>
            <h2 class="mb-3">Demo Payment Successful</h2>
            <p class="text-muted">
                Your Demo card payment has been confirmed using Stripe Test/Sandbox mode.
            </p>
        <?php else: ?>
            <div class="text-warning mb-3" style="font-size:3rem;">!</div>
            <h2 class="mb-3">Demo Payment Processing</h2>
            <p class="text-muted">
                We could not confirm the payment yet. Please check My Requests again in a moment.
            </p>
        <?php endif; ?>

        <a href="?page=customer-requests" class="btn btn-primary">
            Back to My Requests
        </a>

        <?php if ($requestId > 0): ?>
            <a href="?page=customer-payments" class="btn btn-outline-secondary">
                My Payments
            </a>
        <?php endif; ?>

    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
