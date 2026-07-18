<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';

$requestId = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        proposal,
        quoted_price
    FROM requests
    WHERE id = ?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch();

$isRevision = !empty($request['proposal']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $proposalText = trim($_POST['proposal_text']);
    $price = $_POST['proposed_price'];

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            proposal = ?,
            quoted_price = ?,
            workflow_stage = 'Proposal Draft'
        WHERE id = ?
    ");

    $stmt->execute([
        $proposalText,
        $price,
        $requestId
    ]);

    header('Location: ?page=requests');
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>
            <?= $isRevision ? 'Revise Proposal' : 'Create Proposal' ?>
        </h2>

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Proposed Price
                </label>

                <input
                    type="number"
                    step="0.01"
                    name="proposed_price"
                    value="<?= htmlspecialchars($request['quoted_price'] ?? '') ?>"
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
                    required><?=
                    htmlspecialchars($request['proposal'] ?? '')?>
                </textarea>

            </div>

            <button
                type="submit"
                class="btn btn-primary">

                <?= $isRevision ? 'Update Proposal' : 'Save Proposal' ?>

            </button>

            <a
                        href="?page=requests"
                        class="btn btn-secondary ms-2">

                        Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>