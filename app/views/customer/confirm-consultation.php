<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/security.php';
require_once HELPER_PATH . '/meeting.php';

$requestId = $_GET['request_id'] ?? 0;
$slotId = $_GET['slot_id'] ?? 0;
verifyCustomerRequest($pdo, $requestId);

$stmt = $pdo->prepare("
    SELECT agent_id
    FROM requests
    WHERE id = ?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

$assignedAgentId = $request['agent_id'];

$stmt = $pdo->prepare("
    SELECT
        agent_id
    FROM consultation_slots
    WHERE id = ?
");

$stmt->execute([$slotId]);

$selectedSlot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$selectedSlot || $selectedSlot['agent_id'] != $assignedAgentId) {

    die('Invalid consultation slot.');

}

$agentId = $selectedSlot['agent_id'];

$stmt->execute([$slotId]);

$selectedSlot = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM consultation_slots
    WHERE id = ?
");

$stmt->execute([$slotId]);

$slot = $stmt->fetch();

if (!$slot) {

    die('Consultation slot not found.');

}

$consultationDateTime = strtotime(
    $slot['slot_date'] . ' ' . $slot['slot_time']
);

if ($consultationDateTime < strtotime('+48 hours')) {

    header('Location: ?page=schedule-consultation&request_id=' . $requestId);
    exit;

}

$stmtCustomer = $pdo->prepare("
    SELECT
        customers.name,
        customers.email
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    WHERE requests.id = ?
");

$stmtCustomer->execute([$requestId]);

$customer = $stmtCustomer->fetch();

if (!$slot) {

    die('Consultation slot not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $consultationMethod = trim($_POST['consultation_method'] ?? '');

    if ($consultationMethod === '') {

        $error = 'Please select a meeting method.';

    } else {

        $consultationMethod = trim($_POST['consultation_method'] ?? '');

    if ($consultationMethod === '') {

        $error = 'Please select a meeting method.';

    }

    $checkStmt = $pdo->prepare("
        SELECT is_booked
        FROM consultation_slots
        WHERE id = ?
    ");

    $checkStmt->execute([$slotId]);

    $isBooked = $checkStmt->fetchColumn();

    if ($isBooked) {

        $error =
            'Sorry, this consultation slot is no longer available.';

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO consultation_bookings
            (
                request_id,
                slot_id,
                agent_id
            )
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $requestId,
            $slotId,
            $agentId
        ]);

        $stmt = $pdo->prepare("
    UPDATE consultation_slots
    SET
        is_booked = 1,
        consultation_method = ?
    WHERE id = ?
");

$stmt->execute([
    $consultationMethod,
    $slotId
]);

        $stmt = $pdo->prepare("
            UPDATE requests
            SET workflow_stage = 'Consultation Scheduled'
            WHERE id = ?
        ");

        $stmt->execute([$requestId]);

    /*
|--------------------------------------------------------------------------
| Record Consultation Scheduled Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    $requestId,
    RequestEventHelper::EVENT_CONSULTATION_SCHEDULED,
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Scheduled',
    'The customer scheduled a consultation appointment.',
    true
);

        if ($customer && !empty($customer['email'])) {

    $date = date(
    'M d, Y',
    strtotime($slot['slot_date'])
);

$time = date(
    'h:i A',
    strtotime($slot['slot_time'])
);

$body = "
    <h2>Hello {$customer['name']},</h2>

    <p>Your consultation has been scheduled.</p>

    <p>
        <strong>Date:</strong> {$date}
    </p>

    <p>
        <strong>Time:</strong> {$time}
    </p>

    <p>
        <strong>Method:</strong> {$consultationMethod}
    </p>
";

$body .= "
<p>
    We will notify you once your consultation has been confirmed by our team.
</p>

<p>
    IT Consultancy Team
</p>
";

sendEmail(
    $customer['email'],
    'Consultation Scheduled',
    $body
);
}

        header('Location: ?page=customer-requests');
        exit;
    }
    
    }
}

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Confirm Consultation
        </h2>

        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <?= $error ?>

            </div>

        <?php endif; ?>

        <p>

    <strong>Date:</strong>

    <?= formatDate($slot['slot_date']) ?>

</p>

        <p>

            <strong>Time:</strong>

            <?= date(
                'h:i A',
                strtotime($slot['slot_time'])
            ) ?>

        </p>


        <p>

    <strong>Meeting Method:</strong>

</p>

<form method="POST">

    <div class="mb-3">

        <div class="form-check">

            <input
                class="form-check-input"
                type="radio"
                name="consultation_method"
                id="google_meet"
                value="Google Meet"
                checked>

            <label
                class="form-check-label"
                for="google_meet">

                Google Meet

            </label>

        </div>

        <div class="form-check">

            <input
                class="form-check-input"
                type="radio"
                name="consultation_method"
                id="zoom"
                value="Zoom">

            <label
                class="form-check-label"
                for="zoom">

                Zoom

            </label>

        </div>

    </div>


        <form method="POST">

            <button
                type="submit"
                class="btn btn-success">

                Confirm Booking

            </button>

            <a
                href="?page=schedule-consultation&request_id=<?= $requestId ?>"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>