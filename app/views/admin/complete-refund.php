<?php

require_once HELPER_PATH . '/email.php';

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require CONFIG_PATH . '/database.php';

$refundId = (int) ($_GET['id'] ?? 0);

// Get refund details
$stmt = $pdo->prepare("
    SELECT *
    FROM refunds
    WHERE id = ?
");

$stmt->execute([$refundId]);

$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refund) {
    die('Refund not found.');
}

// Mark refund as completed
$stmt = $pdo->prepare("
    UPDATE refunds
    SET status = 'Completed'
    WHERE id = ?
");

$stmt->execute([$refundId]);

$stmt = $pdo->prepare("
    SELECT
        c.name,
        c.email,
        s.title AS service_title,
        rf.amount
    FROM refunds rf
    JOIN requests r
        ON rf.request_id = r.id
    JOIN customers c
        ON r.customer_id = c.id
    JOIN services s
        ON r.service_id = s.id
    WHERE rf.id = ?
");

$stmt->execute([$refundId]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($customer) {

    $subject = "Your Refund Has Been Completed";

    $formattedAmount = number_format(
        $customer['amount'],
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

header("Location: ?page=refunds");
exit;