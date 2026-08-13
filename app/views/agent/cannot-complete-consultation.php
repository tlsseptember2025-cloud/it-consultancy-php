<?php

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$requestId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        s.title AS service_name
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.id=?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch();

if (!$request) {

    die('Consultation not found.');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $reason = trim($_POST['reason']);

    $notes = trim($_POST['notes']);

    $update = $pdo->prepare("
        UPDATE requests
        SET
            job_status='Could Not Complete',
            workflow_stage='Needs Admin Review',
            incomplete_reason=?,
            completion_notes=?
        WHERE id=?
    ");

    $update->execute([
        $reason,
        $notes,
        $requestId
    ]);

    /*
|--------------------------------------------------------------------------
| Record Consultation Incomplete Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $requestId,
    RequestEventHelper::EVENT_CONSULTATION_INCOMPLETE,
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Could Not Be Completed',
    'The assigned agent could not complete the consultation. Reason: ' . $reason,
    false
);

    header("Location:?page=view-consultation&id=".$requestId);
    exit;
}

require VIEW_PATH.'/layouts/header-agent.php';

?>

<div class="container py-4">

    <h2 class="mb-4">

        Report Incomplete Consultation

    </h2>

    <div class="card shadow-sm">

        <div class="card-body">

            <p>

                <strong>Customer:</strong>

                <?= htmlspecialchars($request['customer_name']) ?>

            </p>

            <p>

    <strong>Service:</strong>

    <?= htmlspecialchars($request['service_name']) ?>

</p>

<p>

    <strong>Current Status:</strong>

    <span class="badge bg-primary">

        <?= htmlspecialchars($request['job_status']) ?>

    </span>

</p>

            <div class="alert alert-warning">

    <strong>Important</strong>

    <ul class="mb-0 mt-2">

        <li>This consultation will be marked as <strong>Could Not Complete</strong>.</li>

        <li>The Administrator will review your reason.</li>

        <li>The Administrator may reschedule or reassign the consultation.</li>

    </ul>

</div>

            <form method="POST">

                <div class="mb-3">

                   

                    <div class="mb-3">

    <label class="form-label">

        Reason

    </label>

    <select
        name="reason"
        class="form-select"
        required>

        <option value="">Select a reason...</option>

        <option value="Customer unavailable">
            Customer unavailable
        </option>

        <option value="Customer requested reschedule">
            Customer requested reschedule
        </option>

        <option value="Missing documents">
            Missing documents
        </option>

        <option value="Technical issue">
            Technical issue
        </option>

        <option value="Customer declined consultation">
            Customer declined consultation
        </option>

        <option value="Other">
            Other
        </option>

    </select>

</div>

                </div>

                <div class="mb-3">

                    <label class="form-label">

                        Additional Notes

                    </label>

                    <textarea
                        name="notes"
                        rows="6"
                        class="form-control"></textarea>

                </div>

                <div class="d-flex justify-content-between mt-4">

    <a
        href="?page=view-consultation&id=<?= $requestId ?>"
        class="btn btn-secondary">

        ← Return to Consultation

    </a>

    <button
        type="submit"
        class="btn btn-danger">

        Mark as Could Not Complete

    </button>

</div>

            </form>

        </div>

    </div>

</div>

<?php require VIEW_PATH.'/layouts/footer.php'; ?>