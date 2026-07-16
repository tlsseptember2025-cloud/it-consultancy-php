<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$requestId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        c.email,
        c.phone,
        a.name AS agent_name,
        s.title AS service_name,
        cs.id AS slot_id,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link
    FROM requests r
    INNER JOIN customers c ON c.id = r.customer_id
    INNER JOIN agents a ON a.id = r.agent_id
    INNER JOIN services s ON s.id = r.service_id
    LEFT JOIN consultation_bookings cb ON cb.request_id = r.id
    LEFT JOIN consultation_slots cs ON cs.id = cb.slot_id
    WHERE r.id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$consultation) {
    die('Consultation not found.');
}

$agentStmt = $pdo->query("
    SELECT
        id,
        name
    FROM agents
    ORDER BY name
");

$agents = $agentStmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="row mb-4">

        <div class="col-md-8">

            <h2>Manage Consultation Reschedule</h2>

            <p class="text-muted">

                Request #<?= $consultation['id'] ?>

            </p>

        </div>

    </div>

    <!-- Customer + Service -->

    <div class="row">

        <div class="col-md-6 mb-4">

            <div class="card shadow-sm h-100">

                <div class="card-header">

                    Customer Information

                </div>

                <div class="card-body">

                    <p>

                        <strong>Name:</strong>

                        <?= htmlspecialchars($consultation['customer_name']) ?>

                    </p>

                    <p>

                        <strong>Email:</strong>

                        <?= htmlspecialchars($consultation['email']) ?>

                    </p>

                    <p class="mb-0">

                        <strong>Phone:</strong>

                        <?= htmlspecialchars($consultation['phone']) ?>

                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-6 mb-4">

            <div class="card shadow-sm h-100">

                <div class="card-header">

                    Service Information

                </div>

                <div class="card-body">

                    <p>

                        <strong>Service:</strong>

                        <?= htmlspecialchars($consultation['service_name']) ?>

                    </p>

                    <p class="mb-0">

                        <strong>Quoted Price:</strong>

                        AED <?= number_format($consultation['quoted_price'],2) ?>

                    </p>

                </div>

            </div>

        </div>

    </div>

    <!-- Current Appointment -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-secondary text-white">

            Current Appointment

        </div>

        <div class="card-body">

    <!-- Row 1 -->

    <div class="row mb-4">

        <div class="col-md-4">

            <strong>Date</strong><br>

            <?= date('d M Y', strtotime($consultation['slot_date'])) ?>

        </div>

        <div class="col-md-4">

            <strong>Time</strong><br>

            <?= date('h:i A', strtotime($consultation['slot_time'])) ?>

        </div>

        <div class="col-md-4">

            <strong>Assigned Agent</strong><br>

            <?= htmlspecialchars($consultation['agent_name']) ?>

        </div>

    </div>

    <!-- Row 2 -->

    <div class="row">

        <div class="col-md-6">

            <strong>Meeting Method</strong><br>

            <?= !empty($consultation['consultation_method'])
                ? htmlspecialchars($consultation['consultation_method'])
                : 'Not Assigned'; ?>

        </div>

        <div class="col-md-6">

            <strong>Meeting Link</strong><br>

            <?php if (!empty($consultation['meeting_link'])): ?>

                <a href="<?= htmlspecialchars($consultation['meeting_link']) ?>"
                   target="_blank"
                   class="btn btn-outline-primary btn-sm mt-2">

                    Join Meeting

                </a>

            <?php else: ?>

                <span class="text-muted">

                    Not Available

                </span>

            <?php endif; ?>

        </div>

    </div>

</div>

    </div>

    <div class="card shadow-sm mb-4 border-danger">

    <div class="card-header bg-danger text-white">

        Consultation Review

    </div>

    <div class="card-body">

        <p class="mb-3">

            This consultation requires a new appointment because it could not be completed.

        </p>

        <strong>Reason</strong>

        <div class="border rounded bg-light p-3 mt-2">

            <?= htmlspecialchars($consultation['incomplete_reason']) ?>

        </div>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-warning">

        Agent Notes

    </div>

    <div class="card-body">

        <?php if(!empty($consultation['completion_notes'])): ?>

            <div class="border rounded p-3 bg-light">

                <?= nl2br(htmlspecialchars($consultation['completion_notes'])) ?>

            </div>

        <?php else: ?>

            <span class="text-muted">

                No notes were provided by the assigned agent.

            </span>

        <?php endif; ?>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-success text-white">

        New Appointment

    </div>

    <div class="card-body">

        <p class="text-muted">

            Choose a new consultation schedule.

        </p>

        <div class="row">

            <div class="col-md-6 mb-3">

                <label class="form-label">

                    New Date

                </label>

                <input

                    type="date"

                    class="form-control"

                    name="new_date">

            </div>

            <div class="col-md-6 mb-3">

                <label class="form-label">

                    New Time

                </label>

                <select

                    class="form-select"

                    name="slot">

                    <option>

                        -- Select Date First --

                    </option>

                </select>

            </div>

            <div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Assigned Agent

        </label>

        <select class="form-select" name="agent_id">

            <?php foreach ($agents as $agent): ?>

                <option
                    value="<?= $agent['id'] ?>"
                    <?= $agent['id'] == $consultation['agent_id'] ? 'selected' : '' ?>>

                    <?= htmlspecialchars($agent['name']) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>

    <div class="col-md-6 mb-3">

    <label class="form-label">

        Meeting Method

    </label>

    <select class="form-select" name="consultation_method">

        <option value="">Select Meeting Method</option>

        <option value="Google Meet">Google Meet</option>

        <option value="Microsoft Teams">Microsoft Teams</option>

        <option value="Zoom">Zoom</option>

    </select>

</div>

</div>



        </div>

    </div>



</div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>