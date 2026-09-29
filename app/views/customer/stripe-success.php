<?php

require_once HELPER_PATH . '/auth.php';
requireCustomerLogin();
require_once HELPER_PATH . '/stripe_payment.php';

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$paid = false;
$requestId = 0;

if ($sessionId !== '') {
    try {
        $session = stripeApiRequest(
            'GET',
            'checkout/sessions/' . rawurlencode($sessionId)
        );

        $requestId = (int) ($session['metadata']['request_id'] ?? 0);
        $sessionCustomerId = (int) ($session['metadata']['customer_id'] ?? 0);
        $paid = ($session['payment_status'] ?? '') === 'paid'
            && $sessionCustomerId === (int) $_SESSION['customer']['id'];

        if ($paid) {
            stripeCompleteCheckoutPayment($pdo, $session);
        }
    } catch (Throwable $e) {
        error_log('Stripe success verification failed: ' . $e->getMessage());
    }
}

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<div class="card shadow-sm">
    <div class="card-body text-center py-5">

        <?php if ($paid): ?>

            <div class="text-success mb-3" style="font-size:3rem;">✓</div>

            <h2 class="mb-3">Payment Successful</h2>

            <p class="text-muted">
                Your card payment has been confirmed. Your request is now ready for service scheduling.
            </p>

        <?php else: ?>

            <div class="text-warning mb-3" style="font-size:3rem;">!</div>

            <h2 class="mb-3">Payment Processing</h2>

            <p class="text-muted">
                We could not confirm the payment yet. Please check My Requests again in a moment.
                If the payment was completed, Stripe will automatically update your request.
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
