<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

require CONFIG_PATH . '/database.php';

$error = '';

/*
|--------------------------------------------------------------------------
| Select Database Context
|--------------------------------------------------------------------------
|
| Main Admin -> Main System DB
| Demo Admin -> Demo DB
|
*/

$paymentPdo = $pdo;
$demoTenantId = null;

if (isset($_SESSION['demo_user'])) {

    $paymentPdo = $demoPdo;
    $demoTenantId = (int) $_SESSION['demo_user']['demo_tenant_id'];

}


/*
|--------------------------------------------------------------------------
| Save Payment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requestId = (int) ($_POST['request_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Load Request Balance
    |--------------------------------------------------------------------------
    |
    | For Demo Admin, the request must belong to the current Demo tenant.
    |
    */

    if ($demoTenantId !== null) {

        $balanceStmt = $paymentPdo->prepare("
            SELECT
                requests.id,
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

            JOIN customers
                ON customers.id = requests.customer_id
               AND customers.demo_tenant_id = ?
               AND customers.is_demo_account = 1

            LEFT JOIN payments
                ON payments.request_id = requests.id

            WHERE requests.id = ?

            GROUP BY requests.id
        ");

        $balanceStmt->execute([
            $demoTenantId,
            $requestId
        ]);

    } else {

        $balanceStmt = $paymentPdo->prepare("
            SELECT
                requests.id,
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

        $balanceStmt->execute([
            $requestId
        ]);

    }

    $requestData = $balanceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$requestData) {

        $error = 'The selected request is not available.';

    } else {

        $outstandingBalance =
            (float) $requestData['quoted_price']
            - (float) $requestData['paid_amount']
            + (float) $requestData['refunded_amount'];

        if ($amount <= 0) {

            $error = 'Payment amount must be greater than zero.';

        } elseif ($amount > $outstandingBalance) {

            $error =
                "Payment exceeds outstanding balance. Remaining balance is $" .
                number_format($outstandingBalance, 2);
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Insert Payment
    |--------------------------------------------------------------------------
    */

    if (empty($error)) {

        $stmt = $paymentPdo->prepare("
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
            $requestId,
            $_POST['amount'],
            $_POST['status'],
            $_POST['payment_date'],
            $_POST['notes']
        ]);


        /*
        |--------------------------------------------------------------------------
        | Activate Service When Payment Is Paid
        |--------------------------------------------------------------------------
        */

        if ($_POST['status'] === 'Paid') {

            if ($demoTenantId !== null) {

                $update = $paymentPdo->prepare("
                    UPDATE requests

                    JOIN customers
                        ON customers.id = requests.customer_id
                       AND customers.demo_tenant_id = ?
                       AND customers.is_demo_account = 1

                    SET
                        requests.workflow_stage = 'Service Active',
                        requests.status = 'In Progress'

                    WHERE requests.id = ?
                ");

                $update->execute([
                    $demoTenantId,
                    $requestId
                ]);

            } else {

                $update = $paymentPdo->prepare("
                    UPDATE requests
                    SET
                        workflow_stage = 'Service Active',
                        status = 'In Progress'
                    WHERE id = ?
                ");

                $update->execute([
                    $requestId
                ]);

            }
        }


        header("Location: ?page=payments");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Load Available Requests
|--------------------------------------------------------------------------
*/

if ($demoTenantId !== null) {

    $requestsStmt = $paymentPdo->prepare("
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
           AND customers.demo_tenant_id = ?
           AND customers.is_demo_account = 1

        JOIN services
            ON services.id = requests.service_id

        LEFT JOIN payments
            ON payments.request_id = requests.id

        GROUP BY requests.id

        ORDER BY requests.id DESC
    ");

    $requestsStmt->execute([
        $demoTenantId
    ]);

    $requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $requests = $paymentPdo->query("
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
    ")->fetchAll(PDO::FETCH_ASSOC);

}


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
                                    value="<?= (int) $request['id'] ?>"
                                    data-price="<?= max(
                                        0,
                                        (float) $request['quoted_price']
                                        - (float) $request['paid_amount']
                                    ) ?>"
                                    <?= $selectedRequestId == $request['id']
                                        ? 'selected'
                                        : '' ?>>

                                    #<?= (int) $request['id'] ?>
                                    -
                                    <?= htmlspecialchars($request['name']) ?>
                                    -
                                    <?= htmlspecialchars($request['title']) ?>
                                    ($<?= number_format(
                                        $request['quoted_price'],
                                        2
                                    ) ?>)

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
