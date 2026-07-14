<?php

require_once HELPER_PATH . '/auth.php';

requireCustomerLogin();

$customerId = (int) $_SESSION['customer']['id'];

require dirname(__DIR__) . '/layouts/header-customer.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

require CONFIG_PATH . '/database.php';

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT
        requests.id,
        services.title
    FROM requests
    JOIN services
        ON services.id = requests.service_id
    WHERE requests.customer_id = ?
    ORDER BY requests.id DESC
");

$stmt->execute([$customerId]);

$requests = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requestId = $_POST['request_id'];

    $checkStmt = $pdo->prepare("
        SELECT id
        FROM payment_slips
        WHERE request_id = ?
        AND status = 'Pending'
        LIMIT 1
    ");

    $checkStmt->execute([$requestId]);

    if ($checkStmt->fetch()) {

        $error =
            'A pending deposit slip already exists for this request. Please wait for admin review.';

    } else {

        $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'pdf'
    ];

    $fileExtension = strtolower(
        pathinfo(
            $_FILES['slip']['name'],
            PATHINFO_EXTENSION
        )
    );

    if (!in_array($fileExtension, $allowedExtensions)) {

        $error =
            'Only JPG, JPEG, PNG and PDF files are allowed.';

    } else {

        $fileName = time() . '_' . basename($_FILES['slip']['name']);

       $targetPath =
    ROOT_PATH .
    '/public/uploads/slips/' .
    $fileName;

move_uploaded_file(
    $_FILES['slip']['tmp_name'],
    $targetPath
);

        $stmt = $pdo->prepare("
            INSERT INTO payment_slips
            (
                customer_id,
                request_id,
                file_name
            )
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['customer']['id'],
            $requestId,
            $fileName
        ]);

        $update = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = 'Payment Submitted'
    WHERE id = ?
");

$update->execute([$requestId]);

        $success =
            'Deposit slip uploaded successfully.';
    }
}

        
}

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Upload Deposit Slip
        </h2>

        <?php if (!empty($error)): ?>

    <div class="alert alert-danger">

        <?= $error ?>

    </div>

<?php endif; ?>

<?php if (!empty($success)): ?>

    <div class="alert alert-success">

        <?= $success ?>

    </div>

<?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="mb-3">

                <label class="form-label">
                    Select Request
                </label>

                <select
                    name="request_id"
                    class="form-select">

                    <?php foreach ($requests as $request): ?>

                        <option value="<?= $request['id'] ?>">

                            <?= htmlspecialchars($request['title']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Deposit Slip
                </label>

                <input
                    type="file"
                    name="slip"
                    class="form-control"
                    required>

            </div>

            <button
                type="submit"
                class="btn btn-primary">

                Upload Slip

            </button>

            <a
    href="?page=customer-requests"
    class="btn btn-secondary ms-2">

    Cancel

</a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>