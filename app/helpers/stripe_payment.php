<?php

require_once CONFIG_PATH . '/stripe.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';

function stripeCreateCheckoutSessionForRequest(
    PDO $pdo,
    int $requestId,
    int $customerId
): string {
    if (!stripeIsConfigured()) {
        throw new RuntimeException('Stripe is not configured.');
    }

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.customer_id,
            r.quoted_price,
            r.workflow_stage,
            c.name AS customer_name,
            c.email,
            s.title AS service_title
        FROM requests r
        INNER JOIN customers c ON c.id = r.customer_id
        INNER JOIN services s ON s.id = r.service_id
        WHERE r.id = ?
          AND r.customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([$requestId, $customerId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new RuntimeException('Payment request not found.');
    }

    if ($request['workflow_stage'] !== 'Awaiting Payment') {
        throw new RuntimeException('This request is not currently awaiting payment.');
    }

    $paidStmt = $pdo->prepare("
        SELECT id
        FROM payments
        WHERE request_id = ?
          AND status = 'Paid'
        LIMIT 1
    ");
    $paidStmt->execute([$requestId]);

    if ($paidStmt->fetch()) {
        throw new RuntimeException('This request has already been paid.');
    }

    $amount = (float) $request['quoted_price'];
    $unitAmount = (int) round($amount * 100);

    if ($unitAmount <= 0) {
        throw new RuntimeException('Invalid payment amount.');
    }

    $session = stripeApiRequest('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'success_url' => APP_URL . '/index.php?page=stripe-success&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => APP_URL . '/index.php?page=stripe-cancel&request_id=' . $requestId,
        'client_reference_id' => (string) $requestId,
        'customer_email' => $request['email'],
        'metadata' => [
            'request_id' => (string) $requestId,
            'customer_id' => (string) $customerId,
        ],
        'line_items' => [[
            'price_data' => [
                'currency' => 'aed',
                'product_data' => [
                    'name' => $request['service_title'],
                    'description' => 'IT Consultancy Request #' . $requestId,
                ],
                'unit_amount' => $unitAmount,
            ],
            'quantity' => 1,
        ]],
    ]);

    $sessionId = (string) ($session['id'] ?? '');
    $checkoutUrl = (string) ($session['url'] ?? '');

    if ($sessionId === '' || $checkoutUrl === '') {
        throw new RuntimeException('Stripe did not return a checkout URL.');
    }

    /*
    |--------------------------------------------------------------------------
    | Keep Stripe payments in the existing payments table.
    |--------------------------------------------------------------------------
    */
    $pendingStmt = $pdo->prepare("
        SELECT id
        FROM payments
        WHERE request_id = ?
          AND payment_method = 'Stripe'
          AND status = 'Pending'
        ORDER BY id DESC
        LIMIT 1
    ");
    $pendingStmt->execute([$requestId]);
    $pendingPaymentId = $pendingStmt->fetchColumn();

    if ($pendingPaymentId) {
        $update = $pdo->prepare("
            UPDATE payments
            SET
                amount = ?,
                stripe_checkout_session_id = ?,
                stripe_payment_intent_id = NULL,
                notes = ?
            WHERE id = ?
              AND status = 'Pending'
        ");
        $update->execute([
            $amount,
            $sessionId,
            'Stripe Checkout session created.',
            $pendingPaymentId
        ]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO payments
            (
                request_id,
                amount,
                payment_method,
                stripe_checkout_session_id,
                status,
                payment_date,
                notes
            )
            VALUES (?, ?, 'Stripe', ?, 'Pending', NULL, ?)
        ");
        $insert->execute([
            $requestId,
            $amount,
            $sessionId,
            'Stripe Checkout session created.'
        ]);
    }

    return $checkoutUrl;
}

function stripeCompleteCheckoutPayment(
    PDO $pdo,
    array $session
): bool {
    $sessionId = (string) ($session['id'] ?? '');
    $paymentIntentId = (string) ($session['payment_intent'] ?? '');
    $paymentStatus = (string) ($session['payment_status'] ?? '');
    $requestId = (int) ($session['metadata']['request_id'] ?? $session['client_reference_id'] ?? 0);

    if ($sessionId === '' || $requestId <= 0 || $paymentStatus !== 'paid') {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.customer_id,
            r.quoted_price,
            r.workflow_stage,
            c.name AS customer_name,
            c.email,
            s.title AS service_title
        FROM requests r
        INNER JOIN customers c ON c.id = r.customer_id
        INNER JOIN services s ON s.id = r.service_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new RuntimeException('Stripe payment references an unknown request.');
    }

    $pdo->beginTransaction();

    try {
        $paidStmt = $pdo->prepare("
            SELECT id
            FROM payments
            WHERE request_id = ?
              AND status = 'Paid'
            LIMIT 1
            FOR UPDATE
        ");
        $paidStmt->execute([$requestId]);
        $existingPaidId = $paidStmt->fetchColumn();

        if ($existingPaidId) {
            $pdo->commit();
            return true;
        }

        $paymentStmt = $pdo->prepare("
            SELECT id
            FROM payments
            WHERE stripe_checkout_session_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $paymentStmt->execute([$sessionId]);
        $paymentId = $paymentStmt->fetchColumn();

        if ($paymentId) {
            $update = $pdo->prepare("
                UPDATE payments
                SET
                    amount = ?,
                    payment_method = 'Stripe',
                    stripe_payment_intent_id = ?,
                    status = 'Paid',
                    payment_date = NOW(),
                    notes = ?
                WHERE id = ?
            ");
            $update->execute([
                $request['quoted_price'],
                $paymentIntentId !== '' ? $paymentIntentId : null,
                'Payment confirmed automatically by Stripe.',
                $paymentId
            ]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO payments
                (
                    request_id,
                    amount,
                    payment_method,
                    stripe_checkout_session_id,
                    stripe_payment_intent_id,
                    status,
                    payment_date,
                    notes
                )
                VALUES (?, ?, 'Stripe', ?, ?, 'Paid', NOW(), ?)
            ");
            $insert->execute([
                $requestId,
                $request['quoted_price'],
                $sessionId,
                $paymentIntentId !== '' ? $paymentIntentId : null,
                'Payment confirmed automatically by Stripe.'
            ]);
        }

        $requestUpdate = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Awaiting Service Scheduling',
                status = 'Approved'
            WHERE id = ?
              AND workflow_stage = 'Awaiting Payment'
        ");
        $requestUpdate->execute([$requestId]);

        if ($requestUpdate->rowCount() === 1) {
            RequestEventHelper::add(
                $pdo,
                $requestId,
                RequestEventHelper::EVENT_PAYMENT_RECEIVED,
                RequestEventHelper::TYPE_PAYMENT,
                'Payment Received',
                'The customer payment was confirmed automatically by Stripe.',
                RequestEventHelper::SOURCE_SYSTEM,
                null,
                true
            );
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications/email happen after the database transaction commits.
    |--------------------------------------------------------------------------
    */
    sendEmail(
        $request['email'],
        'Payment Received - Schedule Your Service',
        '<h2>Hello ' . htmlspecialchars($request['customer_name'], ENT_QUOTES, 'UTF-8') . ',</h2>' .
        '<p>We have received and automatically confirmed your card payment.</p>' .
        '<p><strong>Service:</strong> ' . htmlspecialchars($request['service_title'], ENT_QUOTES, 'UTF-8') . '</p>' .
        '<p>You can now log in to your account and schedule your service.</p>' .
        '<p><a href="' . htmlspecialchars(APP_URL . '/index.php?page=public-login', ENT_QUOTES, 'UTF-8') . '">Log in to your account</a></p>' .
        '<p>Thank you for choosing ' . htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8') . '.</p>'
    );

    createNotification(
        $pdo,
        'customer',
        (int) $request['customer_id'],
        'Payment Received',
        'Your card payment has been confirmed. You may now schedule your service.',
        '?page=customer-requests'
    );

    createNotification(
        $pdo,
        'admin',
        null,
        'Stripe Payment Received',
        $request['customer_name'] . ' paid AED ' . number_format((float) $request['quoted_price'], 2) . ' by card for ' . $request['service_title'] . '.',
        '?page=view-request&id=' . $requestId
    );

    return true;
}
