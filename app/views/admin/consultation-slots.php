<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$stmt = $pdo->prepare("
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
    $_POST['slot_date'],
    $_POST['slot_time'],
    $_POST['consultation_method'],
    $_POST['meeting_link']
]);   

}

$slots = $pdo->query("
    SELECT *
    FROM consultation_slots
    ORDER BY slot_date, slot_time
")->fetchAll();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Consultation Slots
        </h2>

        <form method="POST" class="row g-3 mb-4">

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

    <td><?= $slot['slot_date'] ?></td>

    <td><?= htmlspecialchars($slot['consultation_method']) ?></td>

    <td>
        <?php if (!empty($slot['meeting_link'])): ?>

            <a
                href="<?= htmlspecialchars($slot['meeting_link']) ?>"
                target="_blank">
                Open Link
            </a>

        <?php else: ?>

            -

        <?php endif; ?>
    </td>

    <td><?= $slot['slot_time'] ?></td>

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