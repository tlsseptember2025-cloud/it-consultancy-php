<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require dirname(__DIR__) . '/layouts/header-customer.php';

$customerId = (int) $_SESSION['customer']['id'];

require CONFIG_PATH . '/database.php';

$customerId = $_SESSION['customer']['id'];

$requestId = (int)($_GET['request_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        requests.id,
        services.title,
        requests.quoted_price,
        requests.workflow_stage
    FROM requests
    JOIN services
        ON services.id = requests.service_id
    WHERE requests.customer_id = ?
    AND requests.id = ?
    LIMIT 1
");

$stmt->execute([
    $customerId,
    $requestId
]);

$requests = $stmt->fetch();

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

    $fileSize = $_FILES['slip']['size'];
    $maxFileSize = 5 * 1024 * 1024; // 5 MB

    if (!in_array($fileExtension, $allowedExtensions)) {

        $error =
            'Only JPG, JPEG, PNG and PDF files are allowed.';

    } else {
        
        if ($fileSize > $maxFileSize) {

        $error =
            'The payment receipt must not exceed 5 MB.';

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

        
}

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Upload Payment Receipt
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
                    Service
                </label>

                <input
    type="text"
    class="form-control"
    value="<?= htmlspecialchars($requests['title']) ?>"
    readonly>


    <div class="mb-3">

    <label class="form-label">
        Amount Due
    </label>

    <input
        type="text"
        class="form-control"
        value="AED <?= number_format($requests['quoted_price'], 2) ?>"
        readonly>

</div>

<input
    type="hidden"
    name="request_id"
    value="<?= $requests['id'] ?>">

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Payment Receipt
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

                Submit Payment

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