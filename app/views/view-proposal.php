<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM service_proposals
    WHERE request_id = ?
    ORDER BY id DESC
    LIMIT 1
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

            $<?= number_format(
                $proposal['proposed_price'],
                2
            ) ?>

        </p>

        <hr>

        <pre><?= htmlspecialchars(
            $proposal['proposal_text']
        ) ?></pre>

        <a
            href="?page=accept-proposal-confirm&request_id=<?= $requestId ?>"
            class="btn btn-success">

            Accept Proposal

        </a>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>