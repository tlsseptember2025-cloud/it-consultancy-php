<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/security.php';

$requestId = $_GET['request_id'] ?? 0;
$slotId = $_GET['slot_id'] ?? 0;
verifyCustomerRequest($pdo, $requestId);

$stmt = $pdo->prepare("
    SELECT *
    FROM service_slots
    WHERE id = ?
");

$stmt->execute([$slotId]);

$slot = $stmt->fetch();

if (!$slot) {

    die('Service slot not found.');

}

$serviceDateTime = strtotime(
    $slot['service_date'] . ' ' . $slot['service_time']
);

if ($serviceDateTime < strtotime('+72 hours')) {

    header('Location: ?page=schedule-service&request_id=' . $requestId);
    exit;

}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $checkStmt = $pdo->prepare("
        SELECT is_booked
        FROM service_slots
        WHERE id = ?
    ");

    $checkStmt->execute([$slotId]);

    $isBooked = $checkStmt->fetchColumn();

    if ($isBooked) {

        $error =
            'Sorry, this service slot is no longer available.';

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO service_bookings
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
            UPDATE service_slots
            SET is_booked = 1
            WHERE id = ?
        ");

        $stmt->execute([$slotId]);

        $stmt = $pdo->prepare("
            UPDATE requests
            SET workflow_stage = 'Service Scheduled'
            WHERE id = ?
        ");

        $stmt->execute([$requestId]);

        $stmt = $pdo->prepare("
        SELECT
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

        $stmt->execute([$requestId]);

        $request = $stmt->fetch();

        sendEmail(
            $request['email'],
            'Service Scheduled',
            "
            <h2>Hello {$request['name']},</h2>

            <p>Your service has been successfully scheduled.</p>

            <p><strong>Service:</strong> {$request['service_title']}</p>

            <p><strong>Date:</strong> " .
                date('M d, Y', strtotime($slot['service_date'])) .
            "</p>

            <p><strong>Time:</strong> " .
                date('h:i A', strtotime($slot['service_time'])) .
            "</p>

            <p>Your booking request has been received and is awaiting final confirmation 
                from our team. We will notify you as soon as it is approved.</p>

            <p>Thank you for choosing our IT Consultancy services.</p>

            <p>Kind regards,<br>IT Consultancy Team</p>
            "
        );

        header('Location: ?page=customer-requests');
        exit;
    }
}

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
          
            Confirm Service Booking

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
                strtotime($slot['service_date'])
            ) ?>

        </p>

        <p>

            <strong>Time:</strong>

            <?= date(
                'h:i A',
                strtotime($slot['service_time'])
            ) ?>

        </p>

        <form method="POST">

            <button
                type="submit"
                class="btn btn-success">

                Confirm Booking

            </button>

            <a
                href="?page=schedule-service&request_id=<?= $requestId ?>"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>