<?php

require_once __DIR__ . '/../helpers/auth.php';

requireCustomerLogin();

$customerId = (int) $_SESSION['customer']['id'];

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT id
    FROM requests
    WHERE id = ?
      AND customer_id = ?
");

$stmt->execute([
    $requestId,
    $customerId
]);

if (!$stmt->fetch()) {

    header('Location: ?page=customer-requests');
    exit;

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reason = trim($_POST['reason']);

    // Prevent duplicate refund requests
    $check = $pdo->prepare("
        SELECT COUNT(*)
        FROM refunds
        WHERE request_id = ?
          AND status IN ('Pending', 'Approved', 'Processed')
    ");

    $check->execute([$requestId]);

    if ($check->fetchColumn() > 0) {

        $error = 'A refund request has already been submitted for this service.';

    } else {

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
                NULL,
                NULL,
                ?,
                'Pending'
            )
        ");

        $stmt->execute([
            $requestId,
            $reason
        ]);

        header('Location: ?page=customer-refunds');
        exit;
    }
}

require __DIR__ . '/layouts/header.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Request Refund
        </h2>

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Reason for Refund
                </label>

                <textarea
                    name="reason"
                    class="form-control"
                    rows="5"
                    required></textarea>

            </div>

            <button
                type="submit"
                class="btn btn-danger">

                Submit Refund Request

            </button>

            <a
                href="?page=customer-requests"
                class="btn btn-secondary ms-2">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>