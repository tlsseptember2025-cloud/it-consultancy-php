<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

$requestId = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $proposalText = trim($_POST['proposal_text']);
    $price = $_POST['proposed_price'];

    $stmt = $pdo->prepare("
        INSERT INTO service_proposals
        (
            request_id,
            proposal_text,
            proposed_price
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $requestId,
        $proposalText,
        $price
    ]);

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            quoted_price = ?,
            workflow_stage = 'Proposal Sent'
        WHERE id = ?
    ");

    $stmt->execute([
        $price,
        $requestId
    ]);

    header('Location: ?page=requests');
    exit;
}

require __DIR__ . '/layouts/header.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>Create Proposal</h2>

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Proposed Price
                </label>

                <input
                    type="number"
                    step="0.01"
                    name="proposed_price"
                    class="form-control"
                    required>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Proposal Details
                </label>

                <textarea
                    name="proposal_text"
                    class="form-control"
                    rows="8"
                    required></textarea>

            </div>

            <button
                type="submit"
                class="btn btn-primary">

                Save Proposal

            </button>

            <a
                        href="?page=requests"
                        class="btn btn-secondary ms-2">

                        Cancel

            </a>

        </form>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>