<?php

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=agent-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$requestId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT

        r.*,

        c.name  AS customer_name,
        c.email,
        c.phone,

        s.title AS service_name,

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link

    FROM requests r

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE r.id = ?
    AND cb.agent_id = ?

    LIMIT 1
");

$stmt->execute([
    $requestId,
    $_SESSION['agent']['id']
]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_notes'])) {

        $notes = trim($_POST['agent_notes']);

        $update = $pdo->prepare("
            UPDATE requests
            SET completion_notes = ?
            WHERE id = ?
        ");

        $update->execute([
            $notes,
            $requestId
        ]);

        header("Location: ?page=view-consultation&id=".$requestId);
        exit;
    }

    if (isset($_POST['complete_consultation'])) {

    $notes = trim($_POST['agent_notes']);

    $update = $pdo->prepare("
        UPDATE requests
        SET
            completion_notes = ?,
            job_status = 'Completed',
            completed_at = NOW()
        WHERE id = ?
    ");

    $update->execute([
        $notes,
        $requestId
    ]);

    header("Location: ?page=view-consultation&id=".$requestId);
    exit;
}

    if (isset($_POST['start_consultation'])) {

        $update = $pdo->prepare("
            UPDATE requests
            SET job_status = 'In Progress'
            WHERE id = ?
        ");

        $update->execute([$requestId]);

        header("Location: ?page=view-consultation&id=" . $requestId);
        exit;
    }

}

if (!$consultation) {

    die('Consultation not found.');
}

$status = $consultation['job_status'];

$badge = 'secondary';

switch ($status) {

    case 'Pending':
        $badge = 'warning';
        break;

    case 'In Progress':
        $badge = 'primary';
        break;

    case 'Completed':
        $badge = 'success';
        break;

    case 'Could Not Complete':
        $badge = 'danger';
        break;
}

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">

    <div>

        <h2 class="mb-1">

            Consultation Details

        </h2>

        <p class="text-muted mb-0">

            Request #<?= $consultation['id'] ?>

        </p>

    </div>

    <div class="text-end">


    <span class="badge bg-<?= $badge ?> fs-5 px-4 py-2">

        <?= htmlspecialchars($status) ?>

    </span>

    <?php if (
    $consultation['job_status'] == 'Completed' ||
    $consultation['job_status'] == 'Could Not Complete'
): ?>

        <small class="text-muted d-block mt-2">

            Completed on<br>

            <?= date('d M Y h:i A', strtotime($consultation['completed_at'])) ?>

        </small>

    <?php endif; ?>

    <?php if ($consultation['job_status'] == 'Could Not Complete'): ?>

        <small class="text-danger d-block mt-2">

            Reason

            <br>

            <?= htmlspecialchars($consultation['incomplete_reason']) ?>

        </small>

    <?php endif; ?>

</div>

</div>

    <div class="row">

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    Customer Information

                </div>

                <div class="card-body">

                    <p><strong>Name:</strong> <?= htmlspecialchars($consultation['customer_name']) ?></p>

                    <p><strong>Email:</strong> <?= htmlspecialchars($consultation['email']) ?></p>

                    <p><strong>Phone:</strong> <?= htmlspecialchars($consultation['phone']) ?></p>

                </div>

            </div>

        </div>

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    Service Information

                </div>

                <div class="card-body">

                    <p><strong>Service:</strong> <?= htmlspecialchars($consultation['service_name']) ?></p>

                    <p><strong>Quoted Price:</strong> AED <?= number_format($consultation['quoted_price'],2) ?></p>

                </div>

            </div>

        </div>

    </div>

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Meeting Information

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-3">

                    <strong>Date</strong><br>

                    <?= date('d M Y', strtotime($consultation['slot_date'])) ?>

                </div>

                <div class="col-md-3">

                    <strong>Time</strong><br>

                    <?= date('h:i A', strtotime($consultation['slot_time'])) ?>

                </div>

                <div class="col-md-3">

                    <strong>Method</strong><br>

                    <?= $consultation['consultation_method'] ?: 'Not Assigned' ?>

                </div>

                <div class="col-md-3">

                    <strong>Meeting</strong><br>

                    <?php if (!empty($consultation['meeting_link'])): ?>

                        <a
                            href="<?= htmlspecialchars($consultation['meeting_link']) ?>"
                            target="_blank"
                            class="btn btn-success btn-sm mt-2">

                            Join Meeting

                        </a>

                    <?php else: ?>

                        Not Available

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Customer Request

        </div>

        <div class="card-body">

            <div class="bg-light border rounded p-3">

                <?= nl2br(htmlspecialchars($consultation['description'])) ?>

            </div>

        </div>

    </div>

    <?php if ($consultation['job_status'] == 'Could Not Complete'): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-danger text-white">

        Incomplete Reason

    </div>

    <div class="card-body">

        <p>

            <strong>Reason:</strong>

            <?= htmlspecialchars($consultation['incomplete_reason']) ?>

        </p>

    </div>

</div>

<?php endif; ?>

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Consultation Notes

        </div>

        <div class="card-body">

           <form method="POST">

                <textarea
                    class="form-control"
                    rows="8"
                    name="agent_notes"
                    >

                <?= htmlspecialchars($consultation['completion_notes']) ?>

                </textarea>

        </div>

    </div>

   <div class="d-flex justify-content-between mt-4">

    <a
        href="?page=agent-consultations"
        class="btn btn-secondary px-4">

        ← Back

    </a>

    <div>

        <?php if ($consultation['job_status'] == 'Pending'): ?>

            <button
                type="submit"
                name="start_consultation"
                class="btn btn-success">

                ▶ Start Consultation

            </button>

        <?php endif; ?>

        <?php if ($consultation['job_status'] == 'In Progress'): ?>

    <button
        type="submit"
        name="save_notes"
        class="btn btn-primary">

        💾 Save Notes

    </button>

    <button
        type="submit"
        name="complete_consultation"
        class="btn btn-success">

        ✅ Complete Consultation

    </button>

    <a
        href="?page=cannot-complete-consultation&id=<?= $consultation['id'] ?>"
        class="btn btn-danger">

        ❌ Could Not Complete

    </a>

<?php endif; ?>

    </div>

</div>

</form>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>