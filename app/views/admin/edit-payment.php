<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require CONFIG_PATH . '/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM payments
    WHERE id = ?
");

$stmt->execute([$id]);

$payment = $stmt->fetch();

if (!$payment) {
    die('Payment not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE payments
        SET
            amount = ?,
            status = ?,
            payment_date = ?,
            notes = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['amount'],
        $_POST['status'],
        $_POST['payment_date'],
        $_POST['notes'],
        $id
    ]);

    header("Location: ?page=payments");
    exit;
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php';?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Edit Payment
                </h2>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Amount
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="amount"
                            class="form-control"
                            value="<?= $payment['amount'] ?>"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <?php

                            $statuses = [
                                'Unpaid',
                                'Partially Paid',
                                'Paid',
                                'Refund Pending',
                                'Refunded'
                            ];

                            foreach ($statuses as $status):

                            ?>

                                <option
                                    <?= $status == $payment['status'] ? 'selected' : '' ?>>

                                    <?= $status ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Payment Date
                        </label>

                        <input
                            type="datetime-local"
                            name="payment_date"
                            class="form-control"
                            value="<?= $payment['payment_date'] ? date('Y-m-d\TH:i', strtotime($payment['payment_date'])) : '' ?>">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            rows="4"
                            class="form-control"><?= htmlspecialchars($payment['notes']) ?></textarea>

                    </div>

                    <button
                        class="btn btn-primary">

                        Update Payment

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>