<?php

require_once CONFIG_PATH . '/database.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    http_response_code(400);
    exit('Invalid confirmation link.');
}

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        email,
        company_name,
        company_domain,
        status,
        confirmation_expires_at
    FROM demo_requests
    WHERE confirmation_token = ?
    LIMIT 1
");
$stmt->execute([$token]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(404);
    exit('This confirmation link is invalid or has already been used.');
}

if ($request['status'] !== 'Approved') {
    exit('This confirmation link is no longer active.');
}

if (
    empty($request['confirmation_expires_at']) ||
    strtotime($request['confirmation_expires_at']) < time()
) {
    $expireStmt = $pdo->prepare("
        UPDATE demo_requests
        SET
            status = 'Expired',
            confirmation_token = NULL,
            confirmation_expires_at = NULL
        WHERE id = ?
          AND status = 'Approved'
    ");
    $expireStmt->execute([$request['id']]);

    exit('This confirmation link has expired. Please contact the administrator if you still need access to the Demo.');
}

$confirmStmt = $pdo->prepare("
    UPDATE demo_requests
    SET
        status = 'Customer Confirmed',
        customer_confirmed_at = NOW(),
        confirmation_token = NULL,
        confirmation_expires_at = NULL
    WHERE id = ?
      AND status = 'Approved'
      AND confirmation_token = ?
");
$confirmStmt->execute([
    $request['id'],
    $token
]);

if ($confirmStmt->rowCount() !== 1) {
    exit('This confirmation link is no longer active.');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Demo Request Confirmed</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-5">

                    <div class="mb-4">
                        <span class="display-4">✓</span>
                    </div>

                    <h2 class="fw-bold mb-3">
                        Request Confirmed
                    </h2>

                    <p class="text-muted mb-4">
                        Thank you, <?= htmlspecialchars($request['full_name']) ?>.
                    </p>

                    <p class="mb-3">
                        You have confirmed that you are the person who requested
                        the Demo for
                        <strong>
                            <?= htmlspecialchars($request['company_name'] ?: $request['company_domain']) ?>
                        </strong>.
                    </p>

                    <p class="text-muted mb-4">
                        Your confirmation has been sent to the administrator.
                        The Demo will be created after the final administrative step.
                    </p>

                    <div class="alert alert-info text-start">
                        <strong>What happens next?</strong><br>
                        The administrator will complete the final Demo setup and
                        provide the Demo account details.
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>