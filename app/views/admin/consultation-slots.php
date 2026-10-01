<?php
// CSRF protection for all state-changing POST requests.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}


require_once APP_PATH . '/helpers/DateHelper.php';
require_once HELPER_PATH . '/auth.php';

$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();
    require_once CONFIG_PATH . '/demo-database.php';

    $slotPdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}
    require_once CONFIG_PATH . '/database.php';

    $slotPdo = $pdo;

} else {

    header('Location: ?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $slotPdo->prepare("
        INSERT INTO consultation_slots
        (
            slot_date,
            slot_time,
            consultation_method,
            meeting_link
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $_POST['slot_date'] ?? '',
        $_POST['slot_time'] ?? '',
        $_POST['consultation_method'] ?? '',
        trim($_POST['meeting_link'] ?? '')
    ]);
}

if ($isDemoAdmin) {

    /*
     * consultation_slots has no demo_tenant_id column in the current schema.
     * Existing booked slots are therefore isolated through their assigned
     * Demo Agent. Unbooked slots remain visible so they can be assigned.
     */
    $stmt = $slotPdo->prepare("
        SELECT cs.*
        FROM consultation_slots cs
        WHERE cs.is_booked = 0
           OR EXISTS (
                SELECT 1
                FROM consultation_bookings cb
                INNER JOIN agents a
                    ON a.id = cb.agent_id
                WHERE cb.slot_id = cs.id
                  AND a.demo_tenant_id = ?
                  AND a.is_demo_account = 1
           )
        ORDER BY cs.slot_date, cs.slot_time
    ");
    $stmt->execute([$demoTenantId]);
    $slots = $stmt->fetchAll();

} else {

    $slots = $slotPdo->query("
        SELECT *
        FROM consultation_slots
        ORDER BY slot_date, slot_time
    ")->fetchAll();
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Consultation Slots
        </h2>

        <form method="POST" class="row g-3 mb-4">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="col-md-2">

        <input
            type="date"
            name="slot_date"
            class="form-control"
            required>

    </div>

    <div class="col-md-2">

        <select
            name="consultation_method"
            class="form-select"
            required>

            <option value="">
                Select Method
            </option>

            <option value="Google Meet">
                Google Meet
            </option>

            <option value="Zoom">
                Zoom
            </option>

        </select>

    </div>

    <div class="col-md-3">

    <input
        type="text"
        name="meeting_link"
        class="form-control"
        placeholder="Meeting Link (optional)">

    </div>

    <div class="col-md-2">

        <input
            type="time"
            name="slot_time"
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
                    <th>Method</th>
                    <th>Meeting Link</th>
                    <th>Time</th>
                    <th>Status</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($slots as $slot): ?>

                    <tr>

    <td><?= $slot['id'] ?></td>

    <td><?= formatDate($slot['slot_date']) ?></td>

    <td><?= htmlspecialchars($slot['consultation_method']) ?></td>

    <td>
        <?php if (
    !empty($slot['meeting_link'])
    &&
    shouldShowMeetingLink(
        $consultation['slot_date'],
        $consultation['slot_time']
    )
): ?>

            <a
                href="<?= htmlspecialchars($slot['meeting_link']) ?>"
                target="_blank">
                Open Link
            </a>

        <?php else: ?>

            -

        <?php endif; ?>
    </td>

    <td><?= formatTime($slot['slot_time']) ?></td>

    <td>
        <?= $slot['is_booked'] ? 'Booked' : 'Available' ?>
    </td>

</tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>