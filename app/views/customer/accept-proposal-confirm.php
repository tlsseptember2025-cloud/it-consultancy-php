<?php

/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['customer']) &&
    !isset($_SESSION['demo_customer'])
) {
    if (!empty($_SESSION['demo_logged_out'])) {
        header('Location: ?page=demo-login');
    } else {
        header('Location: ?page=public-login');
    }
    exit;
}

require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Determine Customer Environment
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

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
    | Verify Demo Customer Belongs to Its Tenant
    |--------------------------------------------------------------------------
    */

    $demoCustomerCheck = $customerPdo->prepare("
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

    requireCustomerLogin();

    $customerPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

require_once HELPER_PATH . '/payment_request.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) (
    $_GET['request_id'] ??
    $_GET['id'] ??
    0
);

if ($requestId <= 0) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Proposal
|--------------------------------------------------------------------------
|
| The request must belong to the authenticated customer.
|
| Demo requests do not contain Demo tenant columns themselves.
| Therefore Demo ownership is established through the linked
| Demo customer and Demo service records.
|
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $customerPdo->prepare("
        SELECT
            r.id,
            r.quoted_price,
            r.proposal,
            r.workflow_stage,
            c.name AS customer_name,
            c.email,
            s.title AS service_title
        FROM requests r

        JOIN customers c
            ON c.id = r.customer_id
           AND c.demo_tenant_id = ?
           AND c.is_demo_account = 1

        JOIN services s
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

    $stmt = $customerPdo->prepare("
        SELECT
            r.id,
            r.quoted_price,
            r.proposal,
            r.workflow_stage,
            c.name AS customer_name,
            c.email,
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


/*
|--------------------------------------------------------------------------
| Request Not Found / Not Owned
|--------------------------------------------------------------------------
*/

if (!$request) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Payment Request PDF Path
|--------------------------------------------------------------------------
*/

$paymentDir =
    dirname(__DIR__, 2) .
    '/storage/payment_requests';

if (!is_dir($paymentDir)) {

    mkdir(
        $paymentDir,
        0777,
        true
    );
}

$paymentRequestPath =
    $paymentDir .
    '/PAY-' .
    str_pad(
        $request['id'],
        6,
        '0',
        STR_PAD_LEFT
    ) .
    '.pdf';


/*
|--------------------------------------------------------------------------
| Accept Proposal
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['agree_rules'])) {

        die(
            'You must agree to the Rules & Regulations before accepting the proposal.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Request Ownership Again Before UPDATE
    |--------------------------------------------------------------------------
    |
    | This prevents changing a request simply by manipulating request_id.
    |
    |--------------------------------------------------------------------------
    */

    if ($isDemoCustomer) {

        $updateStmt = $customerPdo->prepare("
            UPDATE requests r

            JOIN customers c
                ON c.id = r.customer_id
               AND c.demo_tenant_id = ?
               AND c.is_demo_account = 1

            JOIN services s
                ON s.id = r.service_id
               AND s.demo_tenant_id = ?
               

            SET r.workflow_stage = 'Proposal Accepted'

            WHERE r.id = ?
              AND r.customer_id = ?
        ");

        $updateStmt->execute([
            $demoTenantId,
            $demoTenantId,
            $requestId,
            $customerId
        ]);

    } else {

        $updateStmt = $customerPdo->prepare("
            UPDATE requests

            SET workflow_stage = 'Proposal Accepted'

            WHERE id = ?
              AND customer_id = ?
        ");

        $updateStmt->execute([
            $requestId,
            $customerId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm UPDATE Actually Happened
    |--------------------------------------------------------------------------
    */

    if ($updateStmt->rowCount() < 1) {

        header('Location: ?page=customer-requests');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Record Proposal Accepted Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::addCurrentUser(
        $customerPdo,
        $requestId,
        RequestEventHelper::EVENT_PROPOSAL_ACCEPTED,
        RequestEventHelper::TYPE_PROPOSAL,
        'Proposal Accepted',
        'The customer accepted the proposal.',
        true
    );


    /*
    |--------------------------------------------------------------------------
    | Generate Payment Request PDF
    |--------------------------------------------------------------------------
    */

    generatePaymentRequestPdf(
        $request,
        $paymentRequestPath
    );


    /*
    |--------------------------------------------------------------------------
    | Send Payment Request Email
    |--------------------------------------------------------------------------
    */

    sendPaymentRequestEmail(
        $request['email'],
        $request['customer_name'],
        $request['service_title'],
        $paymentRequestPath
    );


    /*
    |--------------------------------------------------------------------------
    | Notify Administrator
    |--------------------------------------------------------------------------
    */

    createNotification(
        $customerPdo,
        'admin',
        null,
        '✅ Proposal Accepted',
        $request['customer_name'] .
            ' has accepted the proposal.',
        '?page=view-request&id=' . $requestId
    );


    /*
    |--------------------------------------------------------------------------
    | Move Request to Awaiting Payment
    |--------------------------------------------------------------------------
    */

    if ($isDemoCustomer) {

        $stageStmt = $customerPdo->prepare("
            UPDATE requests r

            JOIN customers c
                ON c.id = r.customer_id
               AND c.demo_tenant_id = ?
               AND c.is_demo_account = 1

            JOIN services s
                ON s.id = r.service_id
               AND s.demo_tenant_id = ?
               

            SET r.workflow_stage = 'Awaiting Payment'

            WHERE r.id = ?
              AND r.customer_id = ?
        ");

        $stageStmt->execute([
            $demoTenantId,
            $demoTenantId,
            $requestId,
            $customerId
        ]);

    } else {

        $stageStmt = $customerPdo->prepare("
            UPDATE requests

            SET workflow_stage = 'Awaiting Payment'

            WHERE id = ?
              AND customer_id = ?
        ");

        $stageStmt->execute([
            $requestId,
            $customerId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header('Location: ?page=customer-requests');
    exit;
}


require dirname(__DIR__) .
    '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>Proposal Acceptance</h2>

        <p>

            <strong>Quoted Price:</strong>

            <?= number_format(
                $request['quoted_price'],
                2
            ) ?>

        </p>

        <div class="alert alert-warning">

            By proceeding, you confirm that you accept the proposed scope of work and quoted price.

        </div>

        <form method="POST">

            <div class="form-check mb-3">

                <input
                    class="form-check-input"
                    type="checkbox"
                    name="agree_rules"
                    id="agree_rules"
                    required>

                <label
                    class="form-check-label"
                    for="agree_rules">

                    I have read and agree to the

                    <a
                        href="?page=rules"
                        target="_blank">

                        Rules & Regulations

                    </a>.

                </label>

            </div>

            <button
                type="submit"
                class="btn btn-success">

                Confirm Acceptance

            </button>

            <a
                href="?page=view-proposal&request_id=<?= $requestId ?>"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php
require dirname(__DIR__) .
    '/layouts/footer.php';
?>