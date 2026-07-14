<?php

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

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
        workflow_stage = 'Proposal Sent'
    WHERE id = ?
");

$stmt->execute([
    $proposalText,
    $price,
    $requestId
]);

// Load customer details
$customerStmt = $pdo->prepare("
    SELECT
        r.*,
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.id = ?
");

$customerStmt->execute([$requestId]);

$request = $customerStmt->fetch();

sendEmail(
    $request['email'],
    'Proposal Ready',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        Your proposal is now ready for review.
    </p>

    <p>
        <strong>Service:</strong>
        {$request['service_title']}
    </p>

    <p>
        <strong>Proposed Price:</strong>
        AED " . number_format($price, 2) . "
    </p>

    <p>
        Please log in to review your proposal and continue with the next steps.
    </p>

    <p>
        <a
            href='https://ramiphp.com/?page=customer-login'
            style='
                background:#0d6efd;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '
        >
            View Proposal
        </a>
    </p>

    <p>
        IT Consultancy Team
    </p>
    "
);

createNotification(
    $pdo,
    'customer',
    $request['customer_id'],
    'Proposal Ready',
    'Your proposal is ready for review.',
    '?page=view-proposal&request_id=' . $requestId
);
  
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