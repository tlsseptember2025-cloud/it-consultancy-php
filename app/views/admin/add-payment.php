<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require CONFIG_PATH . '/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requestId = $_POST['request_id'];
    $amount = (float) $_POST['amount'];

    $balanceStmt = $pdo->prepare("
    SELECT
        requests.quoted_price,

        COALESCE(
            SUM(DISTINCT payments.amount),
            0
        ) AS paid_amount,

        (
            SELECT COALESCE(
                SUM(refunds.amount),
                0
            )
            FROM refunds
            WHERE refunds.request_id = requests.id
        ) AS refunded_amount

    FROM requests

    LEFT JOIN payments
        ON payments.request_id = requests.id

    WHERE requests.id = ?

    GROUP BY requests.id
");

$balanceStmt->execute([$requestId]);

$requestData = $balanceStmt->fetch();

$outstandingBalance =
    $requestData['quoted_price']
    - $requestData['paid_amount']
    + $requestData['refunded_amount'];

    if ($amount > $outstandingBalance) {

        $error =
            "Payment exceeds outstanding balance. Remaining balance is $" .
            number_format($outstandingBalance, 2);
    }

    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO payments
            (
                request_id,
                amount,
                status,
                payment_date,
                notes
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['request_id'],
            $_POST['amount'],
            $_POST['status'],
            $_POST['payment_date'],
            $_POST['notes']
        ]);

        if ($_POST['status'] === 'Paid') {

    $update = $pdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Service Active',
            status = 'In Progress'
        WHERE id = ?
    ");

    $update->execute([
    $_POST['request_id']
]);
}

        header("Location: ?page=payments");
        exit;
    }
}

$requests = $pdo->query("
    SELECT
        requests.id,
        requests.quoted_price,

        COALESCE(
            SUM(payments.amount),
            0
        ) AS paid_amount,

        customers.name,

        services.title

    FROM requests

    JOIN customers
        ON customers.id = requests.customer_id

    JOIN services
        ON services.id = requests.service_id

    LEFT JOIN payments
        ON payments.request_id = requests.id

    GROUP BY requests.id

    ORDER BY requests.id DESC
")->fetchAll();

    $selectedRequestId =
    $_GET['request_id'] ?? null;

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Add Payment
                </h2>

                <?php if (!empty($error)): ?>

                    <div id="errorAlert" class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>

                <script>

                    setTimeout(function() {

                        const alert =
                            document.getElementById('errorAlert');

                        if (alert) {

                            alert.style.transition =
                                'opacity 0.5s ease';

                            alert.style.opacity = '0';

                            setTimeout(function() {

                                alert.remove();

                            }, 500);

                        }

                    }, 5000);

                </script>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Request
                        </label>

                        <select
                            name="request_id"
                            id="request_id"
                            class="form-select"
                            required>

                            <option value="">
                                Select Request
                            </option>

                            <?php foreach ($requests as $request): ?>

                                <option
                                    value="<?= $request['id'] ?>"
                                    data-price="<?= $request['quoted_price'] - $request['paid_amount'] ?>"
                                    <?= $selectedRequestId == $request['id']
                                        ? 'selected'
                                        : '' ?>>

                                    #<?= $request['id'] ?>
                                    -
                                    <?= htmlspecialchars($request['name']) ?>
                                    -
                                    <?= htmlspecialchars($request['title']) ?>
                                    ($<?= number_format($request['quoted_price'], 2) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Amount
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="amount"
                            id="amount"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <option>Unpaid</option>
                            <option>Partially Paid</option>
                            <option>Paid</option>
                            <option>Refund Pending</option>
                            <option>Refunded</option>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Payment Date
                        </label>

                        <input
                            type="datetime-local"
                            name="payment_date"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            rows="4"
                            class="form-control"></textarea>

                    </div>

                    <button
                        class="btn btn-primary">

                        Save Payment

                    </button>

                    <a
                        href="?page=payments"
                        class="btn btn-secondary ms-2">

                        Cancel

                    </a>

                </form>

            </div>

        </div>

    </div>

</div>

<script>

document
    .getElementById('request_id')
    .addEventListener('change', function() {

        let option =
            this.options[this.selectedIndex];

        let price =
            option.getAttribute('data-price');

        document
            .getElementById('amount')
            .value = price;

    });

</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>