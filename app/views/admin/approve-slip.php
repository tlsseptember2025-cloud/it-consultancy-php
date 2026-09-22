<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';


/*
|--------------------------------------------------------------------------
| Determine Admin Type
|--------------------------------------------------------------------------
*/

$isDemoAdmin =
    isset($_SESSION['demo_user']);

$isDemoSuperAdmin =
    isset($_SESSION['demo_super_admin']);

$isMainAdmin =
    isset($_SESSION['user']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainAdmin && !$isDemoAdmin && !$isDemoSuperAdmin) {

    header("Location: ?page=login");
    exit;
}


/*
|--------------------------------------------------------------------------
| Payment Slip ID
|--------------------------------------------------------------------------
*/

$id = (int) ($_GET['id'] ?? 0);


if ($id <= 0) {

    die('Invalid payment slip.');
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

$approvalPdo = $pdo;
$demoTenantId = null;

if ($isDemoAdmin || $isDemoSuperAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $approvalPdo = $demoPdo;
}


/*
|--------------------------------------------------------------------------
| Demo Admin Tenant
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $demoTenantId =
        (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {

        die('Invalid Demo tenant.');
    }
}


/*
|--------------------------------------------------------------------------
| Load Payment Slip + Customer + Request Details
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    /*
    |--------------------------------------------------------------------------
    | Demo Admin - Tenant Restricted
    |--------------------------------------------------------------------------
    */

    $stmt = $approvalPdo->prepare("
        SELECT
            ps.id,
            ps.status AS slip_status,
            ps.request_id,
            c.id AS customer_id,
            c.name,
            c.email,
            r.quoted_price,
            s.title AS service_title
        FROM payment_slips ps
        INNER JOIN customers c
            ON c.id = ps.customer_id
        INNER JOIN requests r
            ON r.id = ps.request_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE ps.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $demoTenantId
    ]);

} elseif ($isDemoSuperAdmin) {

    /*
    |--------------------------------------------------------------------------
    | Demo Super Admin - Full Demo Database Access
    |--------------------------------------------------------------------------
    */

    $stmt = $approvalPdo->prepare("
        SELECT
            ps.id,
            ps.status AS slip_status,
            ps.request_id,
            c.id AS customer_id,
            c.name,
            c.email,
            r.quoted_price,
            s.title AS service_title
        FROM payment_slips ps
        INNER JOIN customers c
            ON c.id = ps.customer_id
        INNER JOIN requests r
            ON r.id = ps.request_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE ps.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

} else {

    /*
    |--------------------------------------------------------------------------
    | Main Admin
    |--------------------------------------------------------------------------
    */

    $stmt = $approvalPdo->prepare("
        SELECT
            ps.id,
            ps.status AS slip_status,
            ps.request_id,
            c.id AS customer_id,
            c.name,
            c.email,
            r.quoted_price,
            s.title AS service_title
        FROM payment_slips ps
        INNER JOIN customers c
            ON c.id = ps.customer_id
        INNER JOIN requests r
            ON r.id = ps.request_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE ps.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);
}


$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {

    die('Payment slip not found.');
}


/*
|--------------------------------------------------------------------------
| Only Pending Payment Slips Can Be Approved
|--------------------------------------------------------------------------
*/

if (($request['slip_status'] ?? '') !== 'Pending') {

    header('Location: ?page=requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Prevent Duplicate Paid Payment
|--------------------------------------------------------------------------
|
| A request can only have one Paid payment record.
|
*/

$paidPaymentStmt = $approvalPdo->prepare("
    SELECT id
    FROM payments
    WHERE request_id = ?
      AND status = 'Paid'
    LIMIT 1
");

$paidPaymentStmt->execute([
    $request['request_id']
]);


if ($paidPaymentStmt->fetch()) {

    die(
        'Payment has already been recorded as Paid for this request. ' .
        'This payment slip cannot be approved again.'
    );
}


/*
|--------------------------------------------------------------------------
| Approve Payment Slip + Record Payment + Update Request
|--------------------------------------------------------------------------
*/

try {

    $approvalPdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Re-check Paid Status Inside Transaction
    |--------------------------------------------------------------------------
    */

    $paidPaymentStmt = $approvalPdo->prepare("
        SELECT id
        FROM payments
        WHERE request_id = ?
          AND status = 'Paid'
        LIMIT 1
        FOR UPDATE
    ");

    $paidPaymentStmt->execute([
        $request['request_id']
    ]);


    if ($paidPaymentStmt->fetch()) {

        throw new RuntimeException(
            'Payment has already been recorded as Paid for this request.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Approve Payment Slip
    |--------------------------------------------------------------------------
    */

    if ($isDemoAdmin) {

        $stmt = $approvalPdo->prepare("
            UPDATE payment_slips
            SET status = 'Approved'
            WHERE id = ?
              AND customer_id IN (
                  SELECT id
                  FROM customers
                  WHERE demo_tenant_id = ?
                    AND is_demo_account = 1
              )
              AND status = 'Pending'
        ");

        $stmt->execute([
            $id,
            $demoTenantId
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | Main Admin OR Demo Super Admin
        |--------------------------------------------------------------------------
        */

        $stmt = $approvalPdo->prepare("
            UPDATE payment_slips
            SET status = 'Approved'
            WHERE id = ?
              AND status = 'Pending'
        ");

        $stmt->execute([
            $id
        ]);
    }


    if ($stmt->rowCount() !== 1) {

        throw new RuntimeException(
            'The payment slip could not be approved. ' .
            'It may already have been processed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Record Full Payment
    |--------------------------------------------------------------------------
    |
    | Full payment only.
    | The amount comes from the request quoted price.
    |
    */

    $stmt = $approvalPdo->prepare("
        INSERT INTO payments
        (
            request_id,
            amount,
            status,
            payment_date,
            notes
        )
        VALUES (?, ?, 'Paid', NOW(), ?)
    ");

    $stmt->execute([
        $request['request_id'],
        $request['quoted_price'],
        'Payment approved from payment receipt review'
    ]);


    /*
    |--------------------------------------------------------------------------
    | Record Payment Received Event
    |--------------------------------------------------------------------------
    */

    if ($isDemoAdmin || $isDemoSuperAdmin) {

        $adminId = $isDemoAdmin
            ? (int) ($_SESSION['demo_user']['id'] ?? 0)
            : (int) ($_SESSION['demo_super_admin']['id'] ?? 0);

        RequestEventHelper::add(
            $approvalPdo,
            (int) $request['request_id'],
            RequestEventHelper::EVENT_PAYMENT_RECEIVED,
            RequestEventHelper::TYPE_PAYMENT,
            'Payment Received',
            'The customer payment was received and approved by the administrator.',
            RequestEventHelper::SOURCE_ADMINISTRATOR,
            $adminId,
            true
        );

    } else {

        RequestEventHelper::addCurrentUser(
            $approvalPdo,
            (int) $request['request_id'],
            RequestEventHelper::EVENT_PAYMENT_RECEIVED,
            RequestEventHelper::TYPE_PAYMENT,
            'Payment Received',
            'The customer payment was received and approved by the administrator.',
            true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Request Workflow
    |--------------------------------------------------------------------------
    */

    if ($isDemoAdmin) {

        $stmt = $approvalPdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Awaiting Service Scheduling',
                status = 'Approved'
            WHERE id = ?
              AND customer_id IN (
                  SELECT id
                  FROM customers
                  WHERE demo_tenant_id = ?
                    AND is_demo_account = 1
              )
        ");

        $stmt->execute([
            $request['request_id'],
            $demoTenantId
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | Main Admin OR Demo Super Admin
        |--------------------------------------------------------------------------
        */

        $stmt = $approvalPdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Awaiting Service Scheduling',
                status = 'Approved'
            WHERE id = ?
        ");

        $stmt->execute([
            $request['request_id']
        ]);
    }


    if ($stmt->rowCount() !== 1) {

        throw new RuntimeException(
            'The request workflow could not be updated.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Record Payment Approved Event
    |--------------------------------------------------------------------------
    */

    if ($isDemoAdmin || $isDemoSuperAdmin) {

        $adminId = $isDemoAdmin
            ? (int) ($_SESSION['demo_user']['id'] ?? 0)
            : (int) ($_SESSION['demo_super_admin']['id'] ?? 0);

        RequestEventHelper::add(
            $approvalPdo,
            (int) $request['request_id'],
            RequestEventHelper::EVENT_PAYMENT_APPROVED,
            RequestEventHelper::TYPE_PAYMENT,
            'Payment Approved',
            'The administrator approved the customer payment receipt.',
            RequestEventHelper::SOURCE_ADMINISTRATOR,
            $adminId,
            true
        );

    } else {

        RequestEventHelper::addCurrentUser(
            $approvalPdo,
            (int) $request['request_id'],
            RequestEventHelper::EVENT_PAYMENT_APPROVED,
            RequestEventHelper::TYPE_PAYMENT,
            'Payment Approved',
            'The administrator approved the customer payment receipt.',
            true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $approvalPdo->commit();


} catch (Throwable $e) {

    if ($approvalPdo->inTransaction()) {

        $approvalPdo->rollBack();
    }

    die($e->getMessage());
}


/*
|--------------------------------------------------------------------------
| Customer Email
|--------------------------------------------------------------------------
*/

$loginBaseUrl = APP_URL;

if ($isDemoAdmin || $isDemoSuperAdmin) {

    $loginBaseUrl =
        getenv('DEMO_APP_URL') ?: APP_URL;
}


sendEmail(
    $request['email'],
    'Payment Approved - Schedule Your Service',
    "
    <h2>
        Hello " .
        htmlspecialchars(
            $request['name'],
            ENT_QUOTES,
            'UTF-8'
        ) .
    ",
    </h2>

    <p>
        We are pleased to inform you that your payment has been approved.
    </p>

    <p>
        <strong>Service:</strong>
        " .
        htmlspecialchars(
            $request['service_title'],
            ENT_QUOTES,
            'UTF-8'
        ) .
    "
    </p>

    <p>
        You can now log in to your account and schedule your service
        at a convenient date and time.
    </p>

    <p>

        <a
            href='" .
                htmlspecialchars(
                    $loginBaseUrl . '/?page=public-login',
                    ENT_QUOTES,
                    'UTF-8'
                ) .
            "'
            style='
                background:#198754;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '
        >
            Schedule Service
        </a>

    </p>

    <p>
        Thank you for choosing our IT Consultancy services.
    </p>

    <p>
        IT Consultancy Team
    </p>
    "
);


/*
|--------------------------------------------------------------------------
| Create Customer Notification
|--------------------------------------------------------------------------
*/

createNotification(
    $approvalPdo,
    'customer',
    (int) $request['customer_id'],
    'Payment Approved',
    'Your payment has been approved. You may now schedule your service.',
    '?page=customer-requests'
);


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;