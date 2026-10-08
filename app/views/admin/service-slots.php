<?php

require_once APP_PATH . '/helpers/auth.php';

requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}

// service_slots is global and has no demo_tenant_id.
// Demo Admins must not view or modify global slots.
if (isset($_SESSION['demo_user'])) {
    header('Location: ?page=dashboard');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$slotsPdo = $pdo;

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header('Location: ?page=service-slots');
        exit;
    }

    $serviceDate = trim($_POST['service_date'] ?? '');
    $serviceTime = trim($_POST['service_time'] ?? '');

    $dateObj = DateTime::createFromFormat('Y-m-d', $serviceDate);
    $timeObj = DateTime::createFromFormat('H:i', $serviceTime);

    if (
        !$dateObj ||
        $dateObj->format('Y-m-d') !== $serviceDate ||
        !$timeObj ||
        $timeObj->format('H:i') !== $serviceTime
    ) {
        $_SESSION['error'] = 'Please provide a valid service date and time.';
        header('Location: ?page=service-slots');
        exit;
    }

    $check = $slotsPdo->prepare("
        SELECT id
        FROM service_slots
        WHERE service_date = ?
          AND service_time = ?
        LIMIT 1
    ");
    $check->execute([$serviceDate, $serviceTime]);

    if ($check->fetchColumn()) {
        $_SESSION['error'] = 'This service slot already exists.';
        header('Location: ?page=service-slots');
        exit;
    }

    $stmt = $slotsPdo->prepare("
        INSERT INTO service_slots (service_date, service_time)
        VALUES (?, ?)
    ");

    if (!$stmt->execute([$serviceDate, $serviceTime])) {
        $_SESSION['error'] = 'Failed to add the service slot.';
        header('Location: ?page=service-slots');
        exit;
    }

    $_SESSION['success'] = 'Service slot added successfully.';
    header('Location: ?page=service-slots');
    exit;
}

$slots = $slotsPdo->query("
    SELECT *
    FROM service_slots
    ORDER BY service_date, service_time
")->fetchAll();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">
    <div class="card-body">

        <h2 class="mb-4">Service Slots</h2>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form method="POST" class="row g-3 mb-4">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="col-md-5">
                <input
                    type="date"
                    name="service_date"
                    class="form-control"
                    required
                >
            </div>

            <div class="col-md-5">
                <input
                    type="time"
                    name="service_time"
                    class="form-control"
                    required
                >
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
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
                        <td><?= (int) $slot['id'] ?></td>
                        <td><?= htmlspecialchars($slot['service_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($slot['service_time'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= !empty($slot['is_booked']) ? 'Booked' : 'Available' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
