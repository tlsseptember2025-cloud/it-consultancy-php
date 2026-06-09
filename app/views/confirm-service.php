<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;
$slotId = $_GET['slot_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM service_slots
    WHERE id = ?
");

$stmt->execute([$slotId]);

$slot = $stmt->fetch();

if (!$slot) {

    die('Consultation slot not found.');
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
            'Sorry, this consultation slot is no longer available.';

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
                href="?page=schedule-consultation&request_id=<?= $requestId ?>"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>