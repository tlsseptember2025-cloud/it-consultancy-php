<?php

require_once HELPER_PATH . '/stripe_payment.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!stripeVerifyWebhookSignature($payload, $signature)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook signature.']);
    exit;
}

$event = json_decode($payload, true);

if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook payload.']);
    exit;
}

$type = (string) ($event['type'] ?? '');
$session = $event['data']['object'] ?? null;

try {
    if (
        ($type === 'checkout.session.completed' ||
         $type === 'checkout.session.async_payment_succeeded') &&
        is_array($session)
    ) {
        stripeCompleteCheckoutPayment($pdo, $session);
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (Throwable $e) {
    error_log('Stripe webhook processing failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed.']);
}
