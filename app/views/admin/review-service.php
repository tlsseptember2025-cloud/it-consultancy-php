<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        r.*,

        c.name,
        c.email,
        c.phone,

        s.title AS service_title,

        sb.slot_id,

        ss.service_date,
        ss.service_time

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id

    LEFT JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE r.id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {
    die('Request not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $notes = trim($_POST['service_review_notes'] ?? '');
    $decision = $_POST['decision'] ?? '';

    if ($decision === 'approve') {

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            service_review_notes = ?,
            workflow_stage = 'Service Active'
        WHERE id = ?
    ");

    $stmt->execute([
        $notes,
        $id
    ]);

    /*
|--------------------------------------------------------------------------
| Record Service Started Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $id,
    RequestEventHelper::EVENT_SERVICE_STARTED,
    RequestEventHelper::TYPE_SERVICE,
    'Service Started',
    'The administrator approved the service appointment and the service is now active.',
    true
);

    header('Location: ?page=requests');
    exit;
}

}

require dirname(__DIR__) . '/layouts/header-admin.php';
?>

<div class="container mt-4">

    <h2 class="mb-4">Review Service</h2>
    <form method="POST">

    <!-- Customer Information -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Customer Information</strong>
        </div>

        <div class="card-body">

            <p><strong>Name:</strong> <?= htmlspecialchars($request['name']) ?></p>

            <p><strong>Email:</strong> <?= htmlspecialchars($request['email']) ?></p>

            <p><strong>Phone:</strong> <?= htmlspecialchars($request['phone']) ?></p>

        </div>
    </div>

    <!-- Service Information -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Service Information</strong>
        </div>

        <div class="card-body">

            <p><strong>Service:</strong> <?= htmlspecialchars($request['service_title']) ?></p>

            <p><strong>Date:</strong> <?= date('M d, Y', strtotime($request['service_date'])) ?></p>

            <p><strong>Time:</strong> <?= date('h:i A', strtotime($request['service_time'])) ?></p>

            <p><strong>Status:</strong> <?= htmlspecialchars($request['workflow_stage']) ?></p>

        </div>
    </div>

    <!-- Service Review -->
<div class="card mb-4">

    <div class="card-header">
        <strong>Service Review</strong>
    </div>

    <div class="card-body">

        <div class="mb-3">
            <label for="service_review_notes" class="form-label">
                Review Notes
            </label>

            <textarea
                id="service_review_notes"
                name="service_review_notes"
                class="form-control"
                rows="5"
                placeholder="Enter your review notes here..."></textarea>

        </div>

    </div>

</div>


    <!-- Decision -->
<div class="card mb-4">

    <div class="card-header">
        <strong>Decision</strong>
    </div>

    <div class="card-body">

        <div class="form-check mb-3">

            <input
                class="form-check-input"
                type="radio"
                name="decision"
                id="approve"
                value="approve"
                checked>

            <label class="form-check-label" for="approve">
                Approve Service
            </label>

        </div>

        <div class="form-check">

            <input
                class="form-check-input"
                type="radio"
                name="decision"
                id="return"
                value="return">

            <label class="form-check-label" for="return">
                Return for Rescheduling
            </label>

        </div>

    </div>

</div>

<hr>
<div class="d-flex justify-content-end gap-2">

    <a href="?page=requests"
       class="btn btn-secondary">
        Cancel
    </a>

    <button
        type="submit"
        class="btn btn-success">
        Continue
    </button>

</div>

    </div>

</form>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>