<?php

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/security.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $db = $demoPdo;

    $customerId = (int) (
        $_SESSION['demo_customer']['id'] ?? 0
    );

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if (
        $customerId <= 0 ||
        $demoTenantId <= 0
    ) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Demo Customer
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            id,
            demo_tenant_id,
            is_demo_account
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $customerId,
        $demoTenantId
    ]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=public-login');
        exit;
    }

    require_once CONFIG_PATH . '/database.php';

    $db = $pdo;

    $customerId = (int) (
        $_SESSION['customer']['id'] ?? 0
    );

    if ($customerId <= 0) {

        unset($_SESSION['customer']);

        header('Location: ?page=public-login');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Request / Slot
|--------------------------------------------------------------------------
*/

$requestId = (int) (
    $_GET['request_id'] ?? 0
);

$slotId = (int) (
    $_GET['slot_id'] ?? 0
);

if (
    $requestId <= 0 ||
    $slotId <= 0
) {

    $_SESSION['error'] =
        'Invalid service request or service slot.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
|
| Verify that the request belongs to the authenticated customer.
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            r.id,
            r.customer_id,
            r.service_id,
            r.workflow_stage,
            s.title AS service_title

        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id
           AND c.demo_tenant_id = ?
           AND c.is_demo_account = 1

        INNER JOIN services s
            ON s.id = r.service_id
           AND s.demo_tenant_id = ?
           

        WHERE r.id = ?
          AND r.customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $demoTenantId,
        $demoTenantId,
        $requestId,
        $customerId
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            r.id,
            r.customer_id,
            r.service_id,
            r.workflow_stage,
            s.title AS service_title

        FROM requests r

        INNER JOIN services s
            ON s.id = r.service_id

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
        'Invalid service request.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Selected Service Slot
|--------------------------------------------------------------------------
|
| The slot must belong to the same service context as the request.
| service_slots are assigned to an agent, so Demo isolation is verified
| through the assigned Demo Agent.
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            ss.id,
            ss.agent_id,
            ss.is_booked,
            ss.service_date,
            ss.service_time

        FROM service_slots ss

        INNER JOIN agents a
            ON a.id = ss.agent_id
           AND a.demo_tenant_id = ?
           AND a.is_demo_account = 1

        INNER JOIN services s
            ON s.id = ?
           AND s.demo_tenant_id = ?
           

        WHERE ss.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $demoTenantId,
        $request['service_id'],
        $demoTenantId,
        $slotId
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            id,
            agent_id,
            is_booked,
            service_date,
            service_time

        FROM service_slots

        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $slotId
    ]);
}

$slot = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$slot) {

    $_SESSION['error'] =
        'Service slot not found.';

    header(
        'Location: ?page=schedule-service&request_id='
        . $requestId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Slot Availability
|--------------------------------------------------------------------------
*/

if ((int) $slot['is_booked'] === 1) {

    $_SESSION['error'] =
        'Sorry, this service slot is no longer available.';

    header(
        'Location: ?page=schedule-service&request_id='
        . $requestId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Service Slot Date
|--------------------------------------------------------------------------
*/

$serviceDateTime = strtotime(
    $slot['service_date']
    . ' '
    . $slot['service_time']
);

if (
    $serviceDateTime === false
    ||
    $serviceDateTime < strtotime('+72 hours')
) {

    header(
        'Location: ?page=schedule-service&request_id='
        . $requestId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Process Booking
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $db->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Lock Selected Slot
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT
                id,
                agent_id,
                is_booked,
                service_date,
                service_time
            FROM service_slots
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $slotId
        ]);

        $lockedSlot = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$lockedSlot) {

            throw new Exception(
                'Selected service slot was not found.'
            );
        }


        if ((int) $lockedSlot['is_booked'] === 1) {

            throw new Exception(
                'Sorry, this service slot is no longer available.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Recheck 72-Hour Rule
        |--------------------------------------------------------------------------
        */

        $lockedSlotDateTime = strtotime(
            $lockedSlot['service_date']
            . ' '
            . $lockedSlot['service_time']
        );

        if (
            $lockedSlotDateTime === false
            ||
            $lockedSlotDateTime < strtotime('+72 hours')
        ) {

            throw new Exception(
                'Service bookings must be scheduled at least 72 hours in advance.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Create Service Booking
        |--------------------------------------------------------------------------
        */

        $agentId = (int) $lockedSlot['agent_id'];

        if ($agentId <= 0) {

            throw new Exception(
                'The selected service slot is not assigned to an agent.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Demo Agent Verification
        |--------------------------------------------------------------------------
        */

        if ($isDemoCustomer) {

            $stmt = $db->prepare("
                SELECT id
                FROM agents
                WHERE id = ?
                  AND demo_tenant_id = ?
                  AND is_demo_account = 1
                LIMIT 1
            ");

            $stmt->execute([
                $agentId,
                $demoTenantId
            ]);

            if (!$stmt->fetchColumn()) {

                throw new Exception(
                    'The selected service slot is not available for this Demo account.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Booking
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT id
            FROM service_bookings
            WHERE request_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $requestId
        ]);

        if ($stmt->fetchColumn()) {

            throw new Exception(
                'This service request already has a service booking.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Booking
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO service_bookings
            (
                request_id,
                slot_id,
                agent_id
            )
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $requestId,
            $slotId,
            $agentId
        ]);


        /*
        |--------------------------------------------------------------------------
        | Book Slot
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            UPDATE service_slots
            SET is_booked = 1
            WHERE id = ?
              AND is_booked = 0
        ");

        $stmt->execute([
            $slotId
        ]);

        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                'The selected service slot is no longer available.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update Request Workflow
        |--------------------------------------------------------------------------
        */

        if ($isDemoCustomer) {

            $stmt = $db->prepare("
                UPDATE requests r

                INNER JOIN customers c
                    ON c.id = r.customer_id
                   AND c.demo_tenant_id = ?
                   AND c.is_demo_account = 1

                INNER JOIN services s
                    ON s.id = r.service_id
                   AND s.demo_tenant_id = ?
                   

                SET
                    r.workflow_stage = 'Service Scheduled'

                WHERE r.id = ?
                  AND r.customer_id = ?
            ");

            $stmt->execute([
                $demoTenantId,
                $demoTenantId,
                $requestId,
                $customerId
            ]);

        } else {

            $stmt = $db->prepare("
                UPDATE requests
                SET
                    workflow_stage = 'Service Scheduled'
                WHERE id = ?
                  AND customer_id = ?
            ");

            $stmt->execute([
                $requestId,
                $customerId
            ]);
        }

        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                'The service request could not be updated.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Record Service Scheduled Event
        |--------------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $db,
            $requestId,
            RequestEventHelper::EVENT_SERVICE_SCHEDULED,
            RequestEventHelper::TYPE_SERVICE,
            'Service Scheduled',
            'The customer successfully scheduled the service appointment.',
            true
        );


        /*
        |--------------------------------------------------------------------------
        | Load Customer Email
        |--------------------------------------------------------------------------
        */

        if ($isDemoCustomer) {

            $stmt = $db->prepare("
                SELECT
                    c.name,
                    c.email,
                    s.title AS service_title

                FROM requests r

                INNER JOIN customers c
                    ON c.id = r.customer_id
                   AND c.demo_tenant_id = ?
                   AND c.is_demo_account = 1

                INNER JOIN services s
                    ON s.id = r.service_id
                   AND s.demo_tenant_id = ?
                   

                WHERE r.id = ?
                  AND r.customer_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $demoTenantId,
                $demoTenantId,
                $requestId,
                $customerId
            ]);

        } else {

            $stmt = $db->prepare("
                SELECT
                    c.name,
                    c.email,
                    s.title AS service_title

                FROM requests r

                INNER JOIN customers c
                    ON c.id = r.customer_id

                INNER JOIN services s
                    ON s.id = r.service_id

                WHERE r.id = ?
                  AND r.customer_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $requestId,
                $customerId
            ]);
        }

        $requestEmail = $stmt->fetch(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $db->commit();


        /*
        |--------------------------------------------------------------------------
        | Customer Email
        |--------------------------------------------------------------------------
        */

        if ($requestEmail) {

            sendEmail(
                $requestEmail['email'],
                'Service Scheduled',
                "
                <h2>Hello "
                . htmlspecialchars(
                    $requestEmail['name'],
                    ENT_QUOTES,
                    'UTF-8'
                )
                . ",</h2>

                <p>Your service has been successfully scheduled.</p>

                <p>
                    <strong>Service:</strong>
                    "
                . htmlspecialchars(
                    $requestEmail['service_title'],
                    ENT_QUOTES,
                    'UTF-8'
                )
                . "
                </p>

                <p>
                    <strong>Date:</strong>
                    "
                . date(
                    'M d, Y',
                    strtotime($lockedSlot['service_date'])
                )
                . "
                </p>

                <p>
                    <strong>Time:</strong>
                    "
                . date(
                    'h:i A',
                    strtotime($lockedSlot['service_time'])
                )
                . "
                </p>

                <p>
                    Your booking request has been received and is awaiting
                    final confirmation from our team. We will notify you
                    as soon as it is approved.
                </p>

                <p>
                    Thank you for choosing our IT Consultancy services.
                </p>

                <p>
                    Kind regards,<br>
                    IT Consultancy Team
                </p>
                "
            );
        }


        $_SESSION['success'] =
            'Your service has been successfully scheduled.';

        header(
            'Location: ?page=customer-requests'
        );

        exit;


    } catch (Exception $e) {

        if ($db->inTransaction()) {

            $db->rollBack();
        }

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Customer View
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Confirm Service Booking
        </h2>


        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <p>

            <strong>Date:</strong>

            <?= date(
                'M d, Y',
                strtotime($slot['service_date'])
            ) ?>

        </p>


        <p>

            <strong>Time:</strong>

            <?= date(
                'h:i A',
                strtotime($slot['service_time'])
            ) ?>

        </p>


        <form method="POST">

            <button
                type="submit"
                class="btn btn-success">

                Confirm Booking

            </button>


            <a
                href="?page=schedule-service&request_id=<?= $requestId ?>"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>