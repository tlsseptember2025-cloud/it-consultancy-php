<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/../helpers/email.php';
require_once __DIR__ . '/../helpers/notifications.php';

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!in_array($action, ['approve', 'reject'], true)) {
    die('Invalid action.');
}

$stmt = $pdo->prepare("
    SELECT
        rr.*,
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title
    FROM refund_requests rr
    JOIN requests r
        ON rr.request_id = r.id
    JOIN customers c
        ON r.customer_id = c.id
    JOIN services s
        ON r.service_id = s.id
    WHERE rr.id = ?
");

$stmt->execute([$id]);

$refundRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refundRequest) {
    die('Refund request not found.');
}

if ($refundRequest['status'] !== 'Pending') {
    header("Location: ?page=refund-requests");
    exit;
}

/*
|--------------------------------------------------------------------------
| REJECT REFUND
|--------------------------------------------------------------------------
*/

if ($action === 'reject') {

    $stmt = $pdo->prepare("
        UPDATE refund_requests
        SET status = 'Rejected'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    sendEmail(
        $refundRequest['email'],
        'Refund Request Rejected',
        "
        <h2>Hello {$refundRequest['name']},</h2>

        <p>
            We regret to inform you that your refund request has been rejected.
        </p>

        <p>
            <strong>Service:</strong>
            {$refundRequest['service_title']}
        </p>

        <p>
            If you require further clarification, please contact our support team.
        </p>

        <p>
            Kind regards,<br>
            IT Consultancy Team
        </p>
        "
    );

    createNotification(
        $pdo,
        'customer',
        $refundRequest['customer_id'],
        'Refund Rejected',
        'Unfortunately, your refund request has been rejected.',
        '?page=customer-refunds'
    );

    header("Location: ?page=refund-requests");
    exit;
}

/*
|--------------------------------------------------------------------------
| APPROVE REFUND
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO refunds
    (
        request_id,
        amount,
        refund_date,
        reason,
        status
    )
    VALUES
    (
        ?,
        0,
        NOW(),
        ?,
        'Processed'
    )
");

$stmt->execute([
    $refundRequest['request_id'],
    $refundRequest['reason_type']
]);

$stmt = $pdo->prepare("
    UPDATE refund_requests
    SET status = 'Approved'
    WHERE id = ?
");

$stmt->execute([$id]);

sendEmail(
    $refundRequest['email'],
    'Refund Approved',
    "
    <h2>Hello {$refundRequest['name']},</h2>

    <p>
        Your refund request has been approved.
    </p>

    <p>
        <strong>Service:</strong>
        {$refundRequest['service_title']}
    </p>

    <p>
        Your refund is now being processed by our finance department.
    </p>

    <p>
        Kind regards,<br>
        IT Consultancy Team
    </p>
    "
);

createNotification(
    $pdo,
    'customer',
    $refundRequest['customer_id'],
    'Refund Approved',
    'Your refund request has been approved and is now being processed.',
    '?page=customer-refunds'
);

header("Location: ?page=refund-requests");
exit;