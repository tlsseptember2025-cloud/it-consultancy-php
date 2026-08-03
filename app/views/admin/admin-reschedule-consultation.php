<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;

}

require_once CONFIG_PATH . '/database.php';

$requestId = (int)($_GET['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Load Consultation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        r.*,

        c.name  AS customer_name,
        c.email,
        c.phone,

        s.title AS service_name,

        a.name  AS agent_name,

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE r.id = ?

    LIMIT 1
");

$stmt->execute([$requestId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {

    die('Consultation not found.');

}

/*
|--------------------------------------------------------------------------
| Send Customer To Reschedule
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['send_to_customer'])
) {

    $adminInstruction = trim($_POST['admin_instruction'] ?? '');

    if ($adminInstruction === '') {

        $adminInstruction = '__RESCHEDULE_ALLOWED__';

    }

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            admin_instruction = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $adminInstruction,
        $consultation['id']
    ]);

    header(
        'Location: ?page=needs-admin-review&success=The customer has been invited to schedule a new consultation.'
    );

    exit;

}

require VIEW_PATH . '/layouts/header-admin.php';

?>


<div class="container py-4">


<!-- Header -->

<div class="row mb-4">

    <div class="col-md-8">

        <h2>

            Request Customer Reschedule

        </h2>

        <p class="text-muted">

            Request #<?= $consultation['id'] ?>

        </p>

    </div>


</div>



<!-- Customer + Service -->


<div class="row mb-4">


    <div class="col-md-6">


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



    <div class="col-md-6">


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


    <div class="card-body bg-light">


        <div class="row mb-4">


            <div class="col-md-4">

                <strong>Date</strong><br>

                <?= formatDate($consultation['slot_date']) ?>

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



        <div class="row">


            <div class="col-md-6">

                <strong>Meeting Method</strong><br>

                <?= !empty($consultation['consultation_method'])

                    ? htmlspecialchars($consultation['consultation_method'])

                    : 'Not Assigned';

                ?>

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

<!-- Consultation Review -->

<div class="card shadow-sm mb-4 border-danger">

    <div class="card-header bg-danger text-white">

        Consultation Review

    </div>


    <div class="card-body">


        <p>

            This consultation requires a new appointment because it could not be completed.

        </p>


        <strong>Reason</strong>


        <div class="border rounded bg-light p-3 mt-2">

            <?= htmlspecialchars($consultation['incomplete_reason']) ?>

        </div>


    </div>

</div>



<!-- Agent Notes -->


<div class="card shadow-sm mb-4">


    <div class="card-header bg-warning">

        Agent Notes

    </div>


    <div class="card-body">


        <?php if (!empty($consultation['completion_notes'])): ?>


            <div class="border rounded bg-light p-3">

                <?= nl2br(htmlspecialchars($consultation['completion_notes'])) ?>

            </div>


        <?php else: ?>


            <span class="text-muted">

                No notes were provided by the assigned agent.

            </span>


        <?php endif; ?>


    </div>


</div>

<form method="POST">

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            Administrator Instructions

        </div>

        <div class="card-body">

            <p class="text-muted">

                The consultation could not be completed.

                Send this request back to the customer so they can choose
                a new consultation date and time using the existing
                reschedule workflow.

            </p>

            <div class="mb-4">

                <label class="form-label fw-bold">

                    Administrator Instructions (Optional)

                </label>

                <textarea
                    name="admin_instruction"
                    class="form-control"
                    rows="7"
                    placeholder="Example: Please choose another appointment that better suits your availability.">

<?= htmlspecialchars($consultation['admin_instruction'] ?? '') ?>

                </textarea>

                <div class="form-text">

                    These instructions will be displayed to the customer 
                    before they select a new consultation date and time.

                </div>

            </div>

            <div class="d-flex justify-content-between">

                <a
                    href="?page=needs-admin-review"
                    class="btn btn-secondary">

                    Cancel

                </a>

                <button
                    type="submit"
                    name="send_to_customer"
                    class="btn btn-success">

                    Send Reschedule Request

                </button>

            </div>

        </div>

    </div>

</form>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>