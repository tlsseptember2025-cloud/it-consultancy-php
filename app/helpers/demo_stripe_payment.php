<?php

require_once CONFIG_PATH . '/demo-stripe.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';

function demoStripeCreateCheckoutSessionForRequest(
    PDO $pdo,
    int $requestId,
    int $customerId,
    int $demoTenantId
): string {
    if (!demoStripeIsConfigured()) {
        throw new RuntimeException('Demo Stripe is not configured.');
    }

    if ($customerId <= 0 || $demoTenantId <= 0) {
        throw new RuntimeException('Invalid Demo payment account.');
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
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$requestId, $customerId, $demoTenantId, $demoTenantId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new RuntimeException('Demo payment request not found.');
    }

    if ($request['workflow_stage'] !== 'Awaiting Payment') {
        throw new RuntimeException('This Demo request is not currently awaiting payment.');
    }

    $paidStmt = $pdo->prepare("
        SELECT id
        FROM payments
        WHERE request_id = ?
          AND status = 'Paid'
        LIMIT 1
    ");
    $paidStmt->execute([$requestId]);

    if ($paidStmt->fetchColumn()) {
        throw new RuntimeException('This Demo request has already been paid.');
    }

    $amount = (float) $request['quoted_price'];
    $unitAmount = (int) round($amount * 100);

    if ($unitAmount <= 0) {
        throw new RuntimeException('Invalid Demo payment amount.');
    }

    $session = demoStripeApiRequest('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'success_url' => DEMO_APP_URL . '/index.php?page=stripe-success&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => DEMO_APP_URL . '/index.php?page=stripe-cancel&request_id=' . $requestId,
        'client_reference_id' => (string) $requestId,
        'customer_email' => $request['email'],
        'metadata' => [
            'request_id' => (string) $requestId,
            'customer_id' => (string) $customerId,
            'demo_tenant_id' => (string) $demoTenantId,
        ],
        'line_items' => [[
            'price_data' => [
                'currency' => 'aed',
                'product_data' => [
                    'name' => $request['service_title'],
                    'description' => 'Demo IT Consultancy Request #' . $requestId,
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
    | Demo payments has no Stripe-specific columns.
    | Keep the Checkout Session ID in notes for audit/debugging and correlate
    | the webhook using its signed metadata request_id/customer_id/tenant_id.
    |--------------------------------------------------------------------------
    */
    $pendingStmt = $pdo->prepare("
        SELECT id
        FROM payments
        WHERE request_id = ?
          AND status = 'Pending'
        ORDER BY id DESC
        LIMIT 1
    ");
    $pendingStmt->execute([$requestId]);
    $pendingPaymentId = $pendingStmt->fetchColumn();

    $pendingNote =
        'Demo Stripe Checkout session created in Test/Sandbox mode. Session: ' .
        $sessionId;

    if ($pendingPaymentId) {
        $update = $pdo->prepare("
            UPDATE payments
            SET
                amount = ?,
                notes = ?
            WHERE id = ?
              AND status = 'Pending'
        ");
        $update->execute([
            $amount,
            $pendingNote,
            $pendingPaymentId,
        ]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO payments
            (
                request_id,
                amount,
                status,
                payment_date,
                notes
            )
            VALUES (?, ?, 'Pending', NULL, ?)
        ");
        $insert->execute([
            $requestId,
            $amount,
            $pendingNote,
        ]);
    }

    return $checkoutUrl;
}

function demoStripeCompleteCheckoutPayment(
    PDO $pdo,
    array $session
): bool {
    $sessionId = (string) ($session['id'] ?? '');
    $paymentStatus = (string) ($session['payment_status'] ?? '');
    $requestId = (int) ($session['metadata']['request_id'] ?? $session['client_reference_id'] ?? 0);
    $customerId = (int) ($session['metadata']['customer_id'] ?? 0);
    $demoTenantId = (int) ($session['metadata']['demo_tenant_id'] ?? 0);
    $amountTotal = isset($session['amount_total']) ? (int) $session['amount_total'] : 0;

    if (
        $sessionId === ''
        || $requestId <= 0
        || $customerId <= 0
        || $demoTenantId <= 0
        || $paymentStatus !== 'paid'
    ) {
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
          AND r.customer_id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $requestId,
        $customerId,
        $demoTenantId,
        $demoTenantId,
    ]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new RuntimeException('Demo Stripe payment references an unknown request.');
    }

    $expectedAmount = (int) round((float) $request['quoted_price'] * 100);

    if ($amountTotal !== $expectedAmount) {
        throw new RuntimeException('Demo Stripe payment amount does not match the request amount.');
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

        if ($paidStmt->fetchColumn()) {
            $pdo->commit();
            return true;
        }

        $paymentStmt = $pdo->prepare("
            SELECT id
            FROM payments
            WHERE request_id = ?
              AND status = 'Pending'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $paymentStmt->execute([$requestId]);
        $paymentId = $paymentStmt->fetchColumn();

        $paidNote =
            'Demo payment confirmed automatically by Stripe Test/Sandbox. Session: ' .
            $sessionId;

        if ($paymentId) {
            $update = $pdo->prepare("
                UPDATE payments
                SET
                    amount = ?,
                    status = 'Paid',
                    payment_date = NOW(),
                    notes = ?
                WHERE id = ?
            ");
            $update->execute([
                $request['quoted_price'],
                $paidNote,
                $paymentId,
            ]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO payments
                (
                    request_id,
                    amount,
                    status,
                    payment_date,
                    notes
                )
                VALUES (?, ?, 'Paid', NOW(), ?)
            ");
            $insert->execute([
                $requestId,
                $request['quoted_price'],
                $paidNote,
            ]);
        }

        $requestUpdate = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Awaiting Service Scheduling',
                status = 'Approved'
            WHERE id = ?
              AND customer_id = ?
              AND workflow_stage = 'Awaiting Payment'
        ");
        $requestUpdate->execute([$requestId, $customerId]);

        if ($requestUpdate->rowCount() === 1) {
            RequestEventHelper::add(
                $pdo,
                $requestId,
                RequestEventHelper::EVENT_PAYMENT_RECEIVED,
                RequestEventHelper::TYPE_PAYMENT,
                'Payment Received',
                'The Demo customer payment was confirmed automatically by Stripe Test/Sandbox.',
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

    sendEmail(
        $request['email'],
        'Demo Payment Received - Schedule Your Service',
        '<h2>Hello ' . htmlspecialchars($request['customer_name'], ENT_QUOTES, 'UTF-8') . ',</h2>' .
        '<p>Your Demo card payment has been confirmed in Stripe Test/Sandbox mode.</p>' .
        '<p><strong>Service:</strong> ' . htmlspecialchars($request['service_title'], ENT_QUOTES, 'UTF-8') . '</p>' .
        '<p>You can now return to the Demo website and continue the service workflow.</p>'
    );

    createNotification(
        $pdo,
        'customer',
        $customerId,
        'Payment Received',
        'Your Demo card payment has been confirmed by Stripe Test/Sandbox.',
        '?page=customer-requests'
    );

    createNotification(
        $pdo,
        'admin',
        null,
        'Demo Stripe Payment Received',
        $request['customer_name'] . ' completed a Demo Stripe Test/Sandbox payment of AED '
            . number_format((float) $request['quoted_price'], 2)
            . ' for ' . $request['service_title'] . '.',
        '?page=view-request&id=' . $requestId,
        $demoTenantId
    );

    return true;
}
