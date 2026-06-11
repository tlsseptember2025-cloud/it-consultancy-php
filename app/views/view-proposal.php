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

require __DIR__ . '/layouts/header.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Service Proposal
        </h2>

        <p>

            <strong>Quoted Price:</strong>

            <?= number_format($proposal['quoted_price'], 2) ?>

        </p>

        <hr>

        <pre><?= nl2br(htmlspecialchars($proposal['proposal'])) ?></pre>

        <a
            href="?page=accept-proposal-confirm&request_id=<?= $requestId ?>"
            class="btn btn-success">

            Accept Proposal

        </a>

        <a
            href="?page=reject-proposal&request_id=<?= $requestId ?>"
            class="btn btn-danger">

            Reject Proposal

        </a>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>