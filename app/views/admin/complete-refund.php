<?php
// CSRF protection for this state-changing GET action.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$submittedCsrfToken = $_GET['csrf_token'] ?? '';
if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();
    require_once CONFIG_PATH . '/demo-database.php';

    $refundPdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}
    require_once CONFIG_PATH . '/database.php';

    $refundPdo = $pdo;

} else {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once APP_PATH . '/helpers/notifications.php';

$refundId = (int) ($_GET['id'] ?? 0);

if ($refundId <= 0) {
    die('Invalid refund request.');
}

if ($isDemoAdmin) {

    $stmt = $refundPdo->prepare("
        SELECT rr.*
        FROM refund_requests rr
        INNER JOIN requests r
            ON r.id = rr.request_id
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE rr.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $refundId,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $refundPdo->prepare("
        SELECT *
        FROM refund_requests
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$refundId]);
}

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
if ($isDemoAdmin) {

    $stmt = $refundPdo->prepare("
        UPDATE refund_requests rr
        INNER JOIN requests r
            ON r.id = rr.request_id
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        SET rr.refund_status = 'Completed'
        WHERE rr.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
          AND rr.refund_status <> 'Completed'
    ");

    $stmt->execute([
        $refundId,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $refundPdo->prepare("
        UPDATE refund_requests
        SET refund_status = 'Completed'
        WHERE id = ?
    ");

    $stmt->execute([$refundId]);
}

// Record completed refund in finance history
$stmt = $refundPdo->prepare("
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

if ($isDemoAdmin) {

    $stmt = $refundPdo->prepare("
        SELECT
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title,
            rr.refund_amount
        FROM refund_requests rr
        INNER JOIN requests r
            ON rr.request_id = r.id
        INNER JOIN customers c
            ON r.customer_id = c.id
        INNER JOIN services s
            ON r.service_id = s.id
        WHERE rr.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $refundId,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $refundPdo->prepare("
        SELECT
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title,
            rr.refund_amount
        FROM refund_requests rr
        INNER JOIN requests r
            ON rr.request_id = r.id
        INNER JOIN customers c
            ON r.customer_id = c.id
        INNER JOIN services s
            ON r.service_id = s.id
        WHERE rr.id = ?
        LIMIT 1
    ");

    $stmt->execute([$refundId]);
}

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

createNotification(
    $refundPdo,
    'customer',
    $customer['customer_id'],
    'Refund Completed',
    'Your refund has been successfully completed. The funds should appear in your account soon.',
    '?page=customer-refunds'
);

header("Location: ?page=refunds");
exit;