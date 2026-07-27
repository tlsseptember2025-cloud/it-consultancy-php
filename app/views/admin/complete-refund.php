<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/email.php';
require CONFIG_PATH . '/database.php';

$refundId = (int) ($_GET['id'] ?? 0);

// Get refund details
$stmt = $pdo->prepare("
    SELECT *
    FROM refund_requests
    WHERE id = ?
");

$stmt->execute([$refundId]);

$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refund) {
    die('Refund request not found.');
}

// Prevent duplicate completion
if ($refund['refund_status'] === 'Completed') {
    header("Location: ?page=refunds");
    exit;
}

// Mark refund as completed
$stmt = $pdo->prepare("
    UPDATE refund_requests
    SET refund_status = 'Completed'
    WHERE id = ?
");

$stmt->execute([$refundId]);

// Record completed refund in finance history
$stmt = $pdo->prepare("
    INSERT INTO refunds (
        request_id,
        amount,
        refund_date,
        reason,
        status
    )
    VALUES (?, ?, NOW(), ?, 'Completed')
");

$stmt->execute([
    $refund['request_id'],
    $refund['refund_amount'],
    $refund['reason_type']
]);

$stmt = $pdo->prepare("
    SELECT
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title,
        rr.refund_amount

    FROM refund_requests rr

    JOIN requests r
        ON rr.request_id = r.id

    JOIN customers c
        ON r.customer_id = c.id

    JOIN services s
        ON r.service_id = s.id

    WHERE rr.id = ?
");

$stmt->execute([$refundId]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($customer) {

    $subject = "Your Refund Has Been Completed";

    $formattedAmount = number_format(
    $customer['refund_amount'],
    2
);

    $body = "
Dear {$customer['name']},

We are pleased to inform you that your refund has been successfully completed.

Service:
{$customer['service_title']}

Refund Amount:
AED {$formattedAmount}

The refund has now been processed successfully.

Please note that your bank or payment provider may require additional time before the funds appear in your account.

If you have any questions, please feel free to contact us.

Kind regards,

IT Consultancy Team
";

    sendEmail(
        $customer['email'],
        $subject,
        nl2br($body)
    );

}

$stmt = $pdo->prepare("
    INSERT INTO notifications (
        recipient_type,
        recipient_id,
        title,
        message,
        link,
        is_read
    )
    VALUES (?, ?, ?, ?, ?, 0)
");

$stmt->execute([
    'customer',
    $customer['customer_id'],
    'Refund Completed',
    'Your refund has been successfully completed. The funds should appear in your account soon.',
    '?page=refund-history'
]);

header("Location: ?page=refunds");
exit;