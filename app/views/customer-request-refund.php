<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;

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