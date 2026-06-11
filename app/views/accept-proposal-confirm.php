<?php

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

$proposal = $stmt->fetch();

if (!$proposal) {

    header('Location: ?page=customer-requests');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Awaiting Payment'
        WHERE id = ?
    ");

    $stmt->execute([$requestId]);

    header('Location: ?page=customer-requests');
    exit;
}

require __DIR__ . '/layouts/header.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>Proposal Acceptance</h2>

        <p>

            <strong>Quoted Price:</strong>

            $<?= number_format(
                $proposal['quoted_price'],
                2
            ) ?>

        </p>

        <div class="alert alert-warning">

            By proceeding, you confirm that you accept the proposed scope of work and quoted price.

        </div>

        <form method="POST">

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

<?php require __DIR__ . '/layouts/footer.php'; ?>