<?php

require_once HELPER_PATH . '/auth.php';
requireCustomerLogin();
require_once CONFIG_PATH . '/demo-database.php';
require_once HELPER_PATH . '/demo_stripe_payment.php';

if (!isset($_SESSION['demo_customer'])) {
    http_response_code(403);
    exit('Demo customer access required.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$requestId = (int) ($_POST['request_id'] ?? 0);
$customerId = (int) ($_SESSION['demo_customer']['id'] ?? 0);
$demoTenantId = (int) ($_SESSION['demo_customer']['demo_tenant_id'] ?? 0);

if ($requestId <= 0 || $customerId <= 0 || $demoTenantId <= 0) {
    header('Location: ?page=customer-requests');
    exit;
}

try {
    $checkoutUrl = demoStripeCreateCheckoutSessionForRequest(
        $demoPdo,
        $requestId,
        $customerId,
        $demoTenantId
    );

    header('Location: ' . $checkoutUrl);
    exit;
} catch (Throwable $e) {
    error_log('Demo Stripe checkout creation failed: ' . $e->getMessage());

    $_SESSION['stripe_error'] =
        'We could not start the Demo card payment. Please try again.';

    header('Location: ?page=customer-requests');
    exit;
}
