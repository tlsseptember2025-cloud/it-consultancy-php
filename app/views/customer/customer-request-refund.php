<?php

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/security.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

/*
|--------------------------------------------------------------------------
| Customer database / authentication context
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $refundPdo = $demoPdo;

    $customerId = (int) (
        $_SESSION['demo_customer']['id'] ?? 0
    );

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($customerId <= 0 || $demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Demo Customer
    |--------------------------------------------------------------------------
    */

    $demoCustomerCheck = $refundPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $demoCustomerCheck->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$demoCustomerCheck->fetchColumn()) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=public-login');
        exit;
    }

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $refundPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}

/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) (
    $_GET['request_id'] ?? 0
);

if ($requestId <= 0) {

    header('Location: ?page=customer-requests');
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Request Belongs to Customer
|--------------------------------------------------------------------------
|
| For Demo:
|   Customer -> Request -> Demo Service -> same Demo tenant
|
| For Normal:
|   Customer -> Request
|
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $refundPdo->prepare("
        SELECT
            r.id
        FROM requests r
        JOIN customers c
            ON c.id = r.customer_id
        JOIN services s
            ON s.id = r.service_id
        WHERE r.id = ?
          AND r.customer_id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
          
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $refundPdo->prepare("
        SELECT id
        FROM requests
        WHERE id = ?
          AND customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId
    ]);
}

if (!$stmt->fetch()) {

    header('Location: ?page=customer-requests');
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Scheduled Service
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $refundPdo->prepare("
        SELECT
            ss.service_date,
            ss.service_time
        FROM service_bookings sb
        JOIN service_slots ss
            ON ss.id = sb.slot_id
        JOIN requests r
            ON r.id = sb.request_id
        JOIN customers c
            ON c.id = r.customer_id
        JOIN services s
            ON s.id = r.service_id
        JOIN agents a
            ON a.id = sb.agent_id
        WHERE sb.request_id = ?
          AND r.customer_id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
          
          AND a.demo_tenant_id = c.demo_tenant_id
          AND a.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $refundPdo->prepare("
        SELECT
            ss.service_date,
            ss.service_time
        FROM service_bookings sb
        JOIN service_slots ss
            ON ss.id = sb.slot_id
        WHERE sb.request_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $requestId
    ]);
}

$serviceSchedule = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load Current Workflow Stage / Customer / Service
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $refundPdo->prepare("
        SELECT
            r.workflow_stage,
            c.name AS customer_name,
            s.title AS service_title
        FROM requests r

        JOIN customers c
            ON c.id = r.customer_id

        JOIN services s
            ON s.id = r.service_id

        WHERE r.id = ?
          AND r.customer_id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
          

        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $refundPdo->prepare("
        SELECT
            r.workflow_stage,
            c.name AS customer_name,
            s.title AS service_title
        FROM requests r

        JOIN customers c
            ON c.id = r.customer_id

        JOIN services s
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

    header('Location: ?page=customer-requests');
    exit;
}

$error = null;

/*
|--------------------------------------------------------------------------
| Submit Refund Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reasonType = trim(
        $_POST['reason_type'] ?? ''
    );

    $reasonDetails = trim(
        $_POST['reason_details'] ?? ''
    );

    if ($reasonType === '') {

        $error = 'Please select a refund reason.';

    } elseif ($reasonDetails === '') {

        $error =
            'Please provide additional details.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Refund Requests
        |--------------------------------------------------------------------------
        */

        $check = $refundPdo->prepare("
            SELECT COUNT(*)
            FROM refund_requests
            WHERE request_id = ?
              AND status IN ('Pending', 'Approved')
        ");

        $check->execute([
            $requestId
        ]);

        if ((int) $check->fetchColumn() > 0) {

            $error =
                'A refund request has already been submitted for this service.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Cancellation Rules
            |--------------------------------------------------------------------------
            */

            if ($reasonType === 'Cancellation') {

                /*
                |--------------------------------------------------------------------------
                | Rule 1: Cannot cancel after completion
                |--------------------------------------------------------------------------
                */

                if (
                    isset($request['workflow_stage'])
                    &&
                    $request['workflow_stage'] === 'Completed'
                ) {

                    $error =
                        'Cancellation refunds are not available after the service has been completed.';

                }

                /*
                |--------------------------------------------------------------------------
                | Rule 2: Must be MORE than 48 hours before service
                |--------------------------------------------------------------------------
                */

                elseif (
                    !empty($serviceSchedule['service_date'])
                    &&
                    !empty($serviceSchedule['service_time'])
                ) {

                    $serviceDateTime = strtotime(
                        $serviceSchedule['service_date']
                        . ' '
                        . $serviceSchedule['service_time']
                    );

                    if (
                        $serviceDateTime !== false
                        &&
                        time() >= (
                            $serviceDateTime
                            - (48 * 60 * 60)
                        )
                    ) {

                        $error =
                            'Cancellation refunds must be requested more than 48 hours before the scheduled service.';
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create Refund Request
            |--------------------------------------------------------------------------
            */

            if (empty($error)) {

                try {

                    $refundPdo->beginTransaction();

                    /*
                    |--------------------------------------------------------------------------
                    | Re-check Duplicate Refund Inside Transaction
                    |--------------------------------------------------------------------------
                    */

                    $check = $refundPdo->prepare("
                        SELECT id
                        FROM refund_requests
                        WHERE request_id = ?
                          AND status IN ('Pending', 'Approved')
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $check->execute([
                        $requestId
                    ]);

                    if ($check->fetch()) {

                        throw new RuntimeException(
                            'A refund request has already been submitted for this service.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Insert Refund Request
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $refundPdo->prepare("
                        INSERT INTO refund_requests
                        (
                            request_id,
                            reason_type,
                            reason_details,
                            status
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            'Pending'
                        )
                    ");

                    $stmt->execute([
                        $requestId,
                        $reasonType,
                        $reasonDetails
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Record Refund Requested Event
                    |--------------------------------------------------------------------------
                    */

                    RequestEventHelper::addCurrentUser(
                        $refundPdo,
                        $requestId,
                        'REFUND_REQUESTED',
                        RequestEventHelper::TYPE_REFUND,
                        'Refund Requested',
                        'The customer requested a refund for the service.',
                        true
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Notify Admin
                    |--------------------------------------------------------------------------
                    */

                    createNotification(
                        $refundPdo,
                        'admin',
                        null,
                        'New Refund Request',
                        $request['customer_name']
                            . ' has requested a refund for "'
                            . $request['service_title']
                            . '".',
                        '?page=refund-requests'
                    );

                    $refundPdo->commit();

                    header(
                        'Location: ?page=customer-refunds'
                    );

                    exit;

                } catch (Throwable $e) {

                    if ($refundPdo->inTransaction()) {

                        $refundPdo->rollBack();
                    }

                    $error =
                        $e->getMessage();
                }
            }
        }
    }
}

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Request Refund
        </h2>

        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Refund Reason
                </label>

                <select
                    name="reason_type"
                    class="form-select"
                    required>

                    <option value="">
                        -- Select a reason --
                    </option>

                    <option value="Cancellation">
                        Cancellation (more than 48 hours before scheduled service)
                    </option>

                    <option value="Duplicate Payment">
                        Duplicate Payment
                    </option>

                    <option value="Not Satisfied">
                        Not Satisfied with Service
                    </option>

                    <option value="Other">
                        Other
                    </option>

                </select>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Additional Details
                </label>

                <textarea
                    name="reason_details"
                    class="form-control"
                    rows="5"
                    placeholder="Please provide any additional information..."
                    required></textarea>

            </div>

            <button
                type="submit"
                class="btn btn-danger">

                Submit Refund Request

            </button>

            <a
                href="?page=customer-requests"
                class="btn btn-secondary ms-2">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>