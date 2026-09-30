<?php
require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}

$isDemoAdmin = isset($_SESSION['demo_user']);
if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $slotsPdo = $demoPdo;
    $adminTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($adminTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $slotsPdo->prepare("
        SELECT id FROM demo_tenants
        WHERE id = ?
          AND status = 'active'
          AND (expires_at IS NULL OR expires_at >= CURDATE())
        LIMIT 1
    ");
    $tenantStmt->execute([$adminTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminStmt = $slotsPdo->prepare("
        SELECT id FROM users
        WHERE id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
          AND demo_tenant_id = ?
        LIMIT 1
    ");
    $adminStmt->execute([
        (int) ($_SESSION['demo_user']['id'] ?? 0),
        $adminTenantId
    ]);

    if (!$adminStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
} else {
    require_once CONFIG_PATH . '/database.php';
    $slotsPdo = $pdo;
}

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
        SELECT id FROM service_slots
        WHERE service_date = ? AND service_time = ?
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
    $stmt->execute([$serviceDate, $serviceTime]);

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

        <h2 class="mb-4">
            Service Slots
        </h2>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form method="POST" class="row g-3 mb-4">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrfToken) ?>">

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

                        <td><?= (int) $slot['id'] ?></td>

                        <td><?= htmlspecialchars($slot['service_date']) ?></td>

                        <td><?= htmlspecialchars($slot['service_time']) ?></td>

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