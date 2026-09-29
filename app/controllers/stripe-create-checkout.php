<?php

require_once HELPER_PATH . '/auth.php';
requireCustomerLogin();
require_once HELPER_PATH . '/stripe_payment.php';

$requestId = (int) ($_POST['request_id'] ?? 0);
$customerId = (int) ($_SESSION['customer']['id'] ?? 0);

if ($requestId <= 0 || $customerId <= 0) {
    header('Location: ?page=customer-requests');
    exit;
}

try {
    $checkoutUrl = stripeCreateCheckoutSessionForRequest(
        $pdo,
        $requestId,
        $customerId
    );

    header('Location: ' . $checkoutUrl);
    exit;
} catch (Throwable $e) {
    error_log('Stripe checkout creation failed: ' . $e->getMessage());

    $_SESSION['stripe_error'] =
        'We could not start the card payment. Please try again or use Bank Transfer.';

    header('Location: ?page=customer-requests');
    exit;
}
