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

    if (empty($_POST['agree_rules'])) {

        die('You must agree to the Rules & Regulations before accepting the proposal.');

    }

<<<<<<< HEAD
   $stmt = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = 'Proposal Accepted'
    WHERE id = ?
");
=======
    $stmt = $pdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Proposal Accepted'
        WHERE id = ?
    ");
>>>>>>> fd63020bd82f2bea8d519b8c432465f188af48b4

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

                    <div class="form-check mb-3">

            <input
                class="form-check-input"
                type="checkbox"
                name="agree_rules"
                id="agree_rules"
                required>

            <label
                class="form-check-label"
                for="agree_rules">

                I have read and agree to the
                <a href="?page=rules" target="_blank">
                    Rules & Regulations
                </a>.

            </label>

        </div>

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