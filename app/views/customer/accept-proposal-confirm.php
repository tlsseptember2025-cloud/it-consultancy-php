<?php

require_once HELPER_PATH . '/payment_request.php';
require_once HELPER_PATH . '/email.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        proposal,
        quoted_price
    FROM requests
    WHERE id = ?
");

$stmt->execute([$requestId]);

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.quoted_price,
        c.name AS customer_name,
        c.email,
        s.title AS service_title
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.id = ?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch();

$paymentDir = dirname(__DIR__, 2) . '/storage/payment_requests';

if (!is_dir($paymentDir)) {
    mkdir($paymentDir, 0777, true);
}

$paymentRequestPath =
    $paymentDir .
    '/PAY-' .
    str_pad($request['id'], 6, '0', STR_PAD_LEFT) .
    '.pdf';    


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['agree_rules'])) {

        die('You must agree to the Rules & Regulations before accepting the proposal.');

    }

   $stmt = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = 'Proposal Accepted'
    WHERE id = ?
");

$stmt->execute([$requestId]);

generatePaymentRequestPdf(
    $request,
    $paymentRequestPath
);

    sendPaymentRequestEmail(
        $request['email'],
        $request['customer_name'],
        $request['service_title'],
        $paymentRequestPath
    );

    header('Location: ?page=customer-requests');
    exit;
}

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>Proposal Acceptance</h2>

        <p>

            <strong>Quoted Price:</strong>

            <?= number_format(
                $request['quoted_price'],
                2
            ) ?>

        </p>

        <div class="alert alert-warning">

            By proceeding, you confirm that you accept the proposed scope of work and quoted price.

        </div>

                <form method="POST">

                    <div class="form-check mb-3">

            <input
                class="form-check-input"
                type="checkbox"
                name="agree_rules"
                id="agree_rules"
                required>

            <label
                class="form-check-label"
                for="agree_rules">

                I have read and agree to the
                <a href="?page=rules" target="_blank">
                    Rules & Regulations
                </a>.

            </label>

        </div>

            <button
                type="submit"
                class="btn btn-success">

                Confirm Acceptance

            </button>

            <a
                href="?page=view-proposal&request_id=<?= $requestId ?>"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>