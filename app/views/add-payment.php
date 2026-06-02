<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    header("Location: ?page=payments");
    exit;
}

$requests = $pdo->query("
    SELECT
        requests.id,
        requests.quoted_price,
        customers.name,
        services.title
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    ORDER BY requests.id DESC
")->fetchAll();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Add Payment
                </h2>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Request
                        </label>

                        <select
                            name="request_id"
                            class="form-select"
                            required>

                            <option value="">
                                Select Request
                            </option>

                            <?php foreach ($requests as $request): ?>

                                <option value="<?= $request['id'] ?>">

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

                </form>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>