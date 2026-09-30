<?php

require_once HELPER_PATH . '/security.php';
require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/DateHelper.php';


/*
|--------------------------------------------------------------------------
| Determine Customer Environment
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);
$isMainCustomer = isset($_SESSION['customer']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainCustomer && !$isDemoCustomer) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

    $customerId = (int) $_SESSION['demo_customer']['id'];

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Demo Customer
    |--------------------------------------------------------------------------
    */

    $customerCheckStmt = $customerPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND is_demo_account = 1
          AND demo_tenant_id = ?
        LIMIT 1
    ");

    $customerCheckStmt->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$customerCheckStmt->fetch(PDO::FETCH_ASSOC)) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $customerPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) ($_GET['request_id'] ?? 0);

if ($requestId <= 0) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Customer Request
|--------------------------------------------------------------------------
*/

if (!$isDemoCustomer) {

    verifyCustomerRequest(
        $customerPdo,
        $requestId
    );
}


/*
|--------------------------------------------------------------------------
| Load Request + Current Booking
|--------------------------------------------------------------------------
|
| Demo:
| - authenticated Demo customer
| - Demo tenant
| - Demo service
| - assigned Demo Agent
|
| Normal:
| - authenticated customer
|
*/

if ($isDemoCustomer) {

    $stmt = $customerPdo->prepare("
        SELECT
            r.consultation_reschedules,
            r.workflow_stage,
            r.job_status,
            r.admin_instruction,
            r.admin_review_comments,
            cb.slot_id,
            cb.agent_id,
            cs.slot_date,
            cs.slot_time
        FROM requests r
        JOIN consultation_bookings cb
            ON cb.request_id = r.id
        JOIN consultation_slots cs
            ON cs.id = cb.slot_id
        JOIN customers c
            ON c.id = r.customer_id
        JOIN services s
            ON s.id = r.service_id
        JOIN agents a
            ON a.id = cb.agent_id
        WHERE r.id = ?
          AND r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id = ?
          AND s.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
          AND a.is_demo_account = 1
          AND a.demo_tenant_id = c.demo_tenant_id
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $customerPdo->prepare("
        SELECT
            r.consultation_reschedules,
            r.workflow_stage,
            r.job_status,
            r.admin_instruction,
            r.admin_review_comments,
            cb.slot_id,
            cb.agent_id,
            cs.slot_date,
            cs.slot_time
        FROM requests r
        JOIN consultation_bookings cb
            ON cb.request_id = r.id
        JOIN consultation_slots cs
            ON cs.id = cb.slot_id
        WHERE r.id = ?
          AND r.customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId
    ]);
}


$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {

    $_SESSION['error'] =
        'Invalid request!';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Assigned Agent
|--------------------------------------------------------------------------
*/

$assignedAgentId = (int) $request['agent_id'];

if ($assignedAgentId <= 0) {

    $_SESSION['error'] =
        'No consultation agent is currently assigned to this request.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Unlimited Reschedules When Approved By Administrator
|--------------------------------------------------------------------------
*/

$isAdminReschedule = (
    $request['workflow_stage'] === 'Awaiting Customer Reschedule'
    ||
    $request['admin_instruction'] === '__RESCHEDULE_ALLOWED__'
);


/*
|--------------------------------------------------------------------------
| Normal Customer Reschedule: Only One Allowed
|--------------------------------------------------------------------------
*/

if (
    !$isAdminReschedule
    &&
    (int) $request['consultation_reschedules'] >= 1
) {

    $_SESSION['error'] =
        'You have already used your one allowed consultation reschedule.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Must Be More Than 24 Hours Before Consultation
|--------------------------------------------------------------------------
|
| Unless an administrator has already approved the reschedule.
|
*/

$currentConsultation = strtotime(
    $request['slot_date'] . ' ' . $request['slot_time']
);

$isMissedConsultation = (
    $request['workflow_stage'] === 'Missed Consultation'
);

if (
    !$isMissedConsultation
    &&
    !$isAdminReschedule
    &&
    time() >= ($currentConsultation - (24 * 60 * 60))
) {

    $_SESSION['error'] =
        'Consultations can only be rescheduled more than 24 hours before the scheduled time.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Show Available Slots
|--------------------------------------------------------------------------
|
| consultation_slots does not contain Demo tenant columns.
|
| Therefore:
| - Normal: use slots assigned to the current consultation Agent.
| - Demo: use slots assigned to the authenticated Demo Agent
|         belonging to the same Demo tenant.
|
*/

if ($isDemoCustomer) {

    $stmt = $customerPdo->prepare("
        SELECT cs.*
        FROM consultation_slots cs
        JOIN agents a
            ON a.id = cs.agent_id
        WHERE cs.agent_id = ?
          AND cs.is_booked = 0
          AND TIMESTAMP(cs.slot_date, cs.slot_time)
              >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
          AND a.is_demo_account = 1
          AND a.demo_tenant_id = ?
        ORDER BY cs.slot_date, cs.slot_time
    ");

    $stmt->execute([
        $assignedAgentId,
        $demoTenantId
    ]);

} else {

    $stmt = $customerPdo->prepare("
        SELECT *
        FROM consultation_slots
        WHERE agent_id = ?
          AND is_booked = 0
          AND TIMESTAMP(slot_date, slot_time)
              >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
        ORDER BY slot_date, slot_time
    ");

    $stmt->execute([
        $assignedAgentId
    ]);
}


$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);


$availableDates = [];

foreach ($slots as $slot) {

    if ((int) $slot['id'] === (int) $request['slot_id']) {
        continue;
    }

    if (!isset($availableDates[$slot['slot_date']])) {

        $availableDates[$slot['slot_date']] = true;
    }
}


$selectedDate = $_GET['date'] ?? null;

if (!$selectedDate && !empty($availableDates)) {

    $selectedDate = array_key_first($availableDates);
}


require dirname(__DIR__) . '/layouts/header-customer.php';

?>


<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="mb-1">

            Reschedule Consultation

        </h2>

        <p class="text-muted mb-0">

            Choose a new consultation appointment.

        </p>

    </div>

    <a
        href="?page=customer-requests"
        class="btn btn-outline-secondary">

        ← Back

    </a>

</div>


<?php if (

    !empty($request['admin_instruction'])
    &&
    $request['admin_instruction'] !== '__RESCHEDULE_ALLOWED__'

): ?>

<div class="alert alert-info shadow-sm mb-4">

    <h5 class="mb-3">

        Administrator Instructions

    </h5>

    <p class="mb-0">

        <?= nl2br(
            htmlspecialchars(
                $request['admin_instruction'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>

    </p>

</div>

<?php endif; ?>


<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>Current Consultation</strong>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-5 px-3">

                <p>

                    <strong>Date</strong><br>

                    <?= date(
                        'M d, Y',
                        strtotime($request['slot_date'])
                    ) ?>

                </p>

            </div>

            <div class="col-md-5 px-3">

                <p>

                    <strong>Time</strong><br>

                    <?= formatTime($request['slot_time']) ?>

                </p>

            </div>

        </div>

    </div>

</div>


<div class="card shadow-sm mb-4 border-warning">

    <div class="card-header bg-warning">

        <strong>Reason for Rescheduling</strong>

    </div>

    <div class="card-body">

        <p class="mb-3">

            Your previous consultation was not completed, as you reported to
            the administrator. After reviewing the case, the administrator has
            approved a new consultation appointment and reassigned your request
            to another agent.

        </p>

        <hr>

        <strong>Administrator's Comments:</strong>

        <div class="mt-2">

            <?= nl2br(
                htmlspecialchars(
                    $request['admin_review_comments'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) ?>

        </div>

    </div>

</div>


<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>

            Step 1 - Choose a Date

        </strong>

    </div>

    <div class="card-body">

        <div class="row">

            <?php foreach ($availableDates as $date => $dummy): ?>

                <div class="col-md-3 mb-3">

                    <a
                        href="?page=reschedule-consultation&request_id=<?= $requestId ?>&date=<?= urlencode($date) ?>"
                        class="btn <?= $selectedDate == $date ? 'btn-primary' : 'btn-outline-primary' ?> w-100">

                        <?= date(
                            'M d, Y',
                            strtotime($date)
                        ) ?>

                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>


<div class="card shadow-sm">

    <div class="card-header">

        <strong>Step 2 - Choose a Time</strong>

    </div>

    <div class="card-body">

        <div
            class="row justify-content-center g-4 mx-auto"
            style="max-width:850px;">

            <?php

            $displayedSlots = [];

            foreach ($slots as $slot):

                if ($slot['slot_date'] != $selectedDate) {
                    continue;
                }

                if ((int) $slot['id'] === (int) $request['slot_id']) {
                    continue;
                }

                $key =
                    $slot['slot_date'] .
                    '_' .
                    $slot['slot_time'];

                if (isset($displayedSlots[$key])) {
                    continue;
                }

                $displayedSlots[$key] = true;

            ?>

                <div class="col-md-6 px-4">

                    <button
                        type="button"
                        class="booking-card select-slot"
                        data-slot="<?= (int) $slot['id'] ?>">

                        <div class="booking-icon">

                            <i class="bi bi-clock-fill"></i>

                        </div>

                        <div class="booking-time">

                            <?= formatTime($slot['slot_time']) ?>

                        </div>

                        <div class="booking-text">

                            Available

                        </div>

                    </button>

                </div>

            <?php endforeach; ?>


            <input
                type="hidden"
                id="selectedSlotId"
                value="">


            <div class="text-center mt-4">

                <button
                    type="button"
                    id="continueBooking"
                    class="btn btn-primary btn-lg px-5"
                    disabled>

                    Continue →

                </button>

            </div>

        </div>

    </div>

</div>


<script>

const continueButton =
    document.getElementById('continueBooking');

const selectedSlot =
    document.getElementById('selectedSlotId');


document.querySelectorAll('.select-slot').forEach(card => {

    card.addEventListener('click', function () {

        document
            .querySelectorAll('.select-slot')
            .forEach(item => {

                item.classList.remove('selected');

                const text =
                    item.querySelector('.booking-text');

                if (text) {
                    text.textContent = 'Available';
                }

            });


        this.classList.add('selected');

        selectedSlot.value =
            this.dataset.slot;


        const text =
            this.querySelector('.booking-text');

        if (text) {
            text.textContent = 'Selected';
        }


        continueButton.disabled = false;

    });

});


continueButton.addEventListener('click', function () {

    const slotId =
        document.getElementById('selectedSlotId').value;

    if (!slotId) {

        alert(
            'Please select a consultation time.'
        );

        return;
    }


    window.location =
        '?page=confirm-reschedule-consultation'
        + '&request_id=<?= $requestId ?>'
        + '&slot_id='
        + encodeURIComponent(slotId);

});

</script>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>