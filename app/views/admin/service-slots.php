<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        INSERT INTO service_slots
        (
            service_date,
            service_time
        )
        VALUES (?, ?)
    ");

    $stmt->execute([
        $_POST['service_date'],
        $_POST['service_time']
    ]);
}

$slots = $pdo->query("
    SELECT *
    FROM service_slots
    ORDER BY service_date, service_time
")->fetchAll();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Service Slots
        </h2>

        <form method="POST" class="row g-3 mb-4">

            <div class="col-md-5">

                <input
                    type="date"
                    name="service_date"
                    class="form-control"
                    required>

            </div>

            <div class="col-md-5">

                <input
                    type="time"
                    name="service_time"
                    class="form-control"
                    required>

            </div>

            <div class="col-md-2">

                <button
                    type="submit"
                    class="btn btn-primary w-100">

                    Add Slot

                </button>

            </div>

        </form>

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($slots as $slot): ?>

                    <tr>

                        <td><?= $slot['id'] ?></td>

                        <td><?= $slot['service_date'] ?></td>

                        <td><?= $slot['service_time'] ?></td>

                        <td>

                            <?= $slot['is_booked']
                                ? 'Booked'
                                : 'Available' ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>