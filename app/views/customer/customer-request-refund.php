<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/security.php';

$customerId = (int) $_SESSION['customer']['id'];

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;
verifyCustomerRequest($pdo, $requestId);

$stmt = $pdo->prepare("
    SELECT id
    FROM requests
    WHERE id = ?
      AND customer_id = ?
");

$stmt->execute([
    $requestId,
    $customerId
]);

if (!$stmt->fetch()) {

    header('Location: ?page=customer-requests');
    exit;

}

// Load the scheduled service for this request
$stmt = $pdo->prepare("
    SELECT
        ss.service_date,
        ss.service_time
    FROM service_bookings sb
    JOIN service_slots ss
        ON ss.id = sb.slot_id
    WHERE sb.request_id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$serviceSchedule = $stmt->fetch(PDO::FETCH_ASSOC);

// Load current workflow stage
$stmt = $pdo->prepare("
    SELECT workflow_stage
    FROM requests
    WHERE id = ?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reasonType = trim($_POST['reason_type']);
    $reasonDetails = trim($_POST['reason_details']);

    // Prevent duplicate refund requests
    $check = $pdo->prepare("
        SELECT COUNT(*)
    FROM refund_requests
    WHERE request_id = ?
    AND status IN ('Pending', 'Approved')
    ");

    $check->execute([$requestId]);

    if ($check->fetchColumn() > 0) {

        $error = 'A refund request has already been submitted for this service.';

    } else {

        $reasonType = $_POST['reason_type'];

   if ($reasonType === 'Cancellation') {

    // Rule 1: Cannot cancel after completion
    if (
        isset($request['workflow_stage']) &&
        $request['workflow_stage'] === 'Completed'
    ) {

        $error = 'Cancellation refunds are not available after the service has been completed.';

    }
    // Rule 2: Must be MORE than 48 hours before the service
    elseif (
        !empty($serviceSchedule['service_date']) &&
        !empty($serviceSchedule['service_time'])
    ) {

        $serviceDateTime = strtotime(
            $serviceSchedule['service_date'] . ' ' .
            $serviceSchedule['service_time']
        );

        if (time() >= ($serviceDateTime - (48 * 60 * 60))) {

            $error = 'Cancellation refunds must be requested more than 48 hours before the scheduled service.';
        }
    }
}

        if (empty($error)) {

            $stmt = $pdo->prepare("
            INSERT INTO refund_requests
            (
                request_id,
                reason_type,
                reason_details,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'Pending'
            )
        ");

        $stmt->execute([
    $requestId,
    $reasonType,
    $reasonDetails
    ]);

                header('Location: ?page=customer-refunds');
                exit;
    }
    }
}

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Request Refund
        </h2>

        <?php if (!empty($error)): ?>

    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>

        <form method="POST">

            <div class="mb-3">

    <label class="form-label">
        Refund Reason
    </label>

    <select
        name="reason_type"
        class="form-select"
        required>

        <option value="">
            -- Select a reason --
        </option>

        <option value="Cancellation">
            Cancellation (more than 48 hours before scheduled service)
        </option>

        <option value="Duplicate Payment">
            Duplicate Payment
        </option>

        <option value="Not Satisfied">
            Not Satisfied with Service
        </option>

        <option value="Other">
            Other
        </option>

    </select>

</div>

<div class="mb-3">

    <label class="form-label">
        Additional Details
    </label>

    <textarea
        name="reason_details"
        class="form-control"
        rows="5"
        placeholder="Please provide any additional information..."
        required></textarea>

</div>

            <button
                type="submit"
                class="btn btn-danger">

                Submit Refund Request

            </button>

            <a
                href="?page=customer-requests"
                class="btn btn-secondary ms-2">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>