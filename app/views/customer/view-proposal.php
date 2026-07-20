<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

$requestId = (int)($_GET['request_id'] ?? $_GET['id'] ?? 0);


$stmt = $pdo->prepare("
    SELECT
        proposal,
        quoted_price,
        workflow_stage
    FROM requests
    WHERE id = ?
");

$stmt->execute([$requestId]);

$proposal = $stmt->fetch();

if (
    $proposal &&
    $proposal['workflow_stage'] === 'Proposal Sent'
) {

    $update = $pdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Proposal Viewed'
        WHERE id = ?
    ");

    $update->execute([$requestId]);

    // Keep the page in sync without refreshing
    $proposal['workflow_stage'] = 'Proposal Viewed';
}

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<p class="mb-3">

    <strong>Status:</strong>

    <span class="badge bg-primary">

        <?= htmlspecialchars($proposal['workflow_stage']) ?>

    </span>

</p>

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

        <?php if (
    $proposal['workflow_stage'] === 'Proposal Viewed' ||
    $proposal['workflow_stage'] === 'Proposal Sent'
): ?>

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

<?php endif; ?>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>