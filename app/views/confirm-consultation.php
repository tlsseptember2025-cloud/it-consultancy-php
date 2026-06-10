<?php

require_once __DIR__ . '/../helpers/email.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;
$slotId = $_GET['slot_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM consultation_slots
    WHERE id = ?
");

$stmt->execute([$slotId]);

$slot = $stmt->fetch();

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
                slot_id
            )
            VALUES (?, ?)
        ");

        $stmt->execute([
            $requestId,
            $slotId
        ]);

        $stmt = $pdo->prepare("
            UPDATE consultation_slots
            SET is_booked = 1
            WHERE id = ?
        ");

        $stmt->execute([$slotId]);

        $stmt = $pdo->prepare("
            UPDATE requests
            SET workflow_stage = 'Consultation Scheduled'
            WHERE id = ?
        ");

        $stmt->execute([$requestId]);

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
        <strong>Method:</strong>
        {$slot['consultation_method']}
    </p>
";

if (!empty($slot['meeting_link'])) {

    $body .= "
        <p>
            <strong>Meeting Link:</strong><br>

            <a href='{$slot['meeting_link']}'>
                Join Meeting
            </a>
        </p>
    ";
}

$body .= "
    <p>
        Please be available at the scheduled time.
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

require __DIR__ . '/layouts/header.php';
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

            <?= date(
                'M d, Y',
                strtotime($slot['slot_date'])
            ) ?>

        </p>

        <p>

            <strong>Time:</strong>

            <?= date(
                'h:i A',
                strtotime($slot['slot_time'])
            ) ?>

        </p>

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

<?php require __DIR__ . '/layouts/footer.php'; ?>