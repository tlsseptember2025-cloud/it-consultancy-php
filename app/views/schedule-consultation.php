<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;

$stmt = $pdo->query("
    SELECT *
    FROM consultation_slots
    WHERE is_booked = 0
    ORDER BY slot_date, slot_time
");

$slots = $stmt->fetchAll();

require __DIR__ . '/layouts/header.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Schedule Consultation
        </h2>

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Date</th>
                    <th>Time</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($slots as $slot): ?>

                    <tr>

                        <td>
                            <?= date(
                                'M d, Y',
                                strtotime($slot['slot_date'])
                            ) ?>
                        </td>

                        <td>
                            <?= date(
                                'h:i A',
                                strtotime($slot['slot_time'])
                            ) ?>
                        </td>

                        <td>

                            <a
                                href="?page=confirm-consultation&request_id=<?= $requestId ?>&slot_id=<?= $slot['id'] ?>"
                                class="btn btn-success btn-sm">

                                Book

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>