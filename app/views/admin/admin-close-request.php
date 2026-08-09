<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/email.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once APP_PATH . '/helpers/contact_history_helper.php';

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        c.email,
        c.phone,
        a.name AS agent_name,
        s.title AS service_name
    FROM requests r
    INNER JOIN customers c
        ON c.id = r.customer_id
    LEFT JOIN agents a
        ON a.id = r.agent_id
    INNER JOIN services s
        ON s.id = r.service_id
    WHERE r.id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}


/*
|--------------------------------------------------------------------------
| Get Logged-in Administrator
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$_SESSION['user']]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die('Administrator not found.');
}

$adminId = $admin['id'];


/*
|--------------------------------------------------------------------------
| Check Existing Closure Agreement
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM consultation_closure_agreements
    WHERE request_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$requestId]);

$existingAgreement = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Determine Page State
|--------------------------------------------------------------------------
*/

$agreementSubmitted = (
    $existingAgreement !== false
);

$agreementSent = (
    $request['workflow_stage'] === 'Closure Agreement Sent'
);


/*
|--------------------------------------------------------------------------
| Send / Resend Closure Agreement
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        isset($_POST['send_closure_agreement'])
        || isset($_POST['resend_closure_agreement'])
    )
) {

    $isResend = isset($_POST['resend_closure_agreement']);

    $closureNotes = trim(
        $_POST['closure_notes'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Do Not Resend After Customer Submission
    |--------------------------------------------------------------------------
    */

    if ($agreementSubmitted) {

        die(
            'The customer has already submitted a Closure Agreement. '
            . 'The agreement must now be reviewed by the administrator.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Notes
    |--------------------------------------------------------------------------
    */

    if ($closureNotes === '') {

        /*
        | On resend, use the existing request notes if the
        | administrator did not enter anything new.
        */

        $closureNotes = trim(
            $request['closure_notes'] ?? ''
        );

    }

    if ($closureNotes === '') {

        die('Closure notes are required.');

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Workflow
    |--------------------------------------------------------------------------
    */

    if (!$isResend) {

        if (
            $request['workflow_stage'] !== 'Needs Admin Review'
        ) {

            die(
                'This request is no longer awaiting administrator review.'
            );

        }

    } else {

        if (
            $request['workflow_stage'] !== 'Closure Agreement Sent'
        ) {

            die(
                'This request does not currently have a Closure Agreement awaiting customer response.'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Build Correct Application URL
    |--------------------------------------------------------------------------
    */

    $scheme = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (
            isset($_SERVER['SERVER_PORT'])
            && (int) $_SERVER['SERVER_PORT'] === 443
        )
    )
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        die('Unable to determine application host.');
    }


    /*
    |--------------------------------------------------------------------------
    | Application Base Path
    |--------------------------------------------------------------------------
    */

    $basePath = rtrim(
        dirname($_SERVER['SCRIPT_NAME']),
        '/'
    );


    $closureUrl =
        $scheme
        . '://'
        . $host
        . $basePath
        . '/?page=consultation-closure-agreement&request_id='
        . $requestId;


    /*
    |--------------------------------------------------------------------------
    | Email Subject
    |--------------------------------------------------------------------------
    */

    if ($isResend) {

        $subject =
            'Reminder: Consultation Closure Agreement - Request #'
            . $requestId;

    } else {

        $subject =
            'Consultation Closure Agreement - Request #'
            . $requestId;

    }


    /*
    |--------------------------------------------------------------------------
    | Email Body
    |--------------------------------------------------------------------------
    */

    $body = '

<h2>Consultation Closure Agreement</h2>

<p>
    Dear <strong>'
    . htmlspecialchars($request['customer_name'])
    . '</strong>,
</p>

<p>
    Following our recent telephone conversation regarding your
    consultation request, please review and confirm the
    Consultation Closure Agreement.
</p>

<h3>Request Details</h3>

<table
    cellpadding="8"
    cellspacing="0"
    border="1"
    style="border-collapse:collapse;width:100%;">

    <tr>
        <td><strong>Request Number</strong></td>
        <td>#'
        . $requestId
        . '</td>
    </tr>

    <tr>
        <td><strong>Customer</strong></td>
        <td>'
        . htmlspecialchars($request['customer_name'])
        . '</td>
    </tr>

    <tr>
        <td><strong>Service</strong></td>
        <td>'
        . htmlspecialchars($request['service_name'])
        . '</td>
    </tr>

</table>

<br>

<p>
    Please review the Consultation Closure Agreement by clicking
    the button below.
</p>

<p>
    <a
        href="'
        . htmlspecialchars($closureUrl)
        . '"
        style="
            display:inline-block;
            padding:12px 20px;
            background:#dc3545;
            color:#ffffff;
            text-decoration:none;
            border-radius:5px;
        ">
        Review Closure Agreement
    </a>
</p>

<p>
    If you did not request closure of this consultation request,
    please contact WAHBIB Consultancy immediately.
</p>

<hr>

<p>
    <strong>WAHBIB Consultancy</strong><br>
    Professional IT Consultancy & Digital Solutions
</p>

';


    /*
    |--------------------------------------------------------------------------
    | Send Email
    |--------------------------------------------------------------------------
    */

    $emailSent = sendEmail(
        $request['email'],
        $subject,
        $body
    );


    if (!$emailSent) {

        die(
            'The Closure Agreement email could not be sent.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE requests
        SET
            closure_notes = ?,
            workflow_stage = ?
        WHERE id = ?
    ");

    $update->execute([
        $closureNotes,
        'Closure Agreement Sent',
        $requestId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Contact History
    |--------------------------------------------------------------------------
    */

    addContactHistory(
        $pdo,
        $requestId,
        null,
        $adminId,
        'admin',
        $isResend
            ? 'closure_agreement_resent'
            : 'closure_agreement_sent',
        $isResend
            ? 'Administrator resent the Consultation Closure Agreement.'
            : 'Administrator contacted the customer and sent the Consultation Closure Agreement.'
    );


    /*
    |--------------------------------------------------------------------------
    | Request Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::add(
        $pdo,
        $requestId,
        $isResend
            ? 'CLOSURE_AGREEMENT_RESENT'
            : 'CLOSURE_AGREEMENT_SENT',
        RequestEventHelper::TYPE_SYSTEM,
        $isResend
            ? 'Closure Agreement Resent'
            : 'Closure Agreement Sent',
        $isResend
            ? 'The administrator resent the Consultation Closure Agreement to the customer.'
            : 'The administrator contacted the customer and sent the Consultation Closure Agreement.',
        RequestEventHelper::SOURCE_ADMINISTRATOR,
        $adminId
    );


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ?page=needs-admin-review'
        . '&success='
        . (
            $isResend
                ? 'closure-agreement-resent'
                : 'closure-agreement-sent'
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = 'Customer Requested Closure';

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Customer Requested Closure
            </h2>

            <p class="text-muted mb-0">
                Review the customer's request before sending
                the Closure Agreement.
            </p>

        </div>

        <div>

            <?php if ($agreementSubmitted): ?>

                <span class="badge bg-info">
                    Agreement Submitted
                </span>

            <?php elseif ($agreementSent): ?>

                <span class="badge bg-warning text-dark">
                    Awaiting Customer
                </span>

            <?php else: ?>

                <span class="badge bg-warning text-dark">
                    Needs Admin Review
                </span>

            <?php endif; ?>

        </div>

    </div>


    <!-- Request Information -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            <h5 class="mb-0">
                Request Information
            </h5>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6 mb-3">

                    <strong>
                        Request Number
                    </strong>

                    <div class="mt-1">
                        #<?= (int) $request['id'] ?>
                    </div>

                </div>


                <div class="col-md-6 mb-3">

                    <strong>
                        Customer
                    </strong>

                    <div class="mt-1">
                        <?= htmlspecialchars(
                            $request['customer_name']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6 mb-3">

                    <strong>
                        Email
                    </strong>

                    <div class="mt-1">
                        <?= htmlspecialchars(
                            $request['email']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6 mb-3">

                    <strong>
                        Phone
                    </strong>

                    <div class="mt-1">
                        <?= htmlspecialchars(
                            $request['phone']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6 mb-3">

                    <strong>
                        Service
                    </strong>

                    <div class="mt-1">
                        <?= htmlspecialchars(
                            $request['service_name']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-6 mb-3">

                    <strong>
                        Assigned Agent
                    </strong>

                    <div class="mt-1">
                        <?= htmlspecialchars(
                            $request['agent_name'] ?? 'Not assigned'
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Closure Information -->

    <div class="card shadow-sm mb-4 border-danger">

        <div class="card-header bg-danger text-white">

            <h5 class="mb-0">
                Customer Requested Closure
            </h5>

        </div>

        <div class="card-body">


            <?php if ($agreementSubmitted): ?>

                <div class="alert alert-success">

                    <strong>
                        Closure Agreement Submitted
                    </strong>

                    <br><br>

                    The customer has already submitted the
                    Consultation Closure Agreement.

                    <br><br>

                    <strong>
                        Do not resend the agreement.
                    </strong>

                    The next step is administrator review.

                </div>


                <div class="d-flex justify-content-between">

                    <a
                        href="?page=closure-agreements"
                        class="btn btn-primary">

                        Review Closure Agreements →

                    </a>

                </div>


            <?php elseif ($agreementSent): ?>

                <div class="alert alert-warning">

                    <strong>
                        Closure Agreement Sent
                    </strong>

                    <br><br>

                    The Closure Agreement has already been sent
                    to the customer.

                    <br><br>

                    If the customer has not received it, you may
                    resend the agreement using the button below.

                    <br><br>

                    The request will remain:

                    <strong>
                        Closure Agreement Sent
                    </strong>

                    until the customer submits the agreement.

                </div>


                <form method="post">

                    <div class="mb-4">

                        <label
                            for="closure_notes"
                            class="form-label fw-bold">

                            Closure Notes
                            <span class="text-danger">*</span>

                        </label>

                        <textarea
                            name="closure_notes"
                            id="closure_notes"
                            class="form-control"
                            rows="5"
                            required><?= htmlspecialchars(
                                $request['closure_notes'] ?? ''
                            ) ?></textarea>

                        <div class="form-text">

                            You may update the notes before
                            resending the agreement.

                        </div>

                    </div>


                    <div class="d-flex justify-content-between">

                        <a
                            href="?page=needs-admin-review"
                            class="btn btn-secondary">

                            ← Back

                        </a>


                        <button
                            type="submit"
                            name="resend_closure_agreement"
                            value="1"
                            class="btn btn-warning"
                            onclick="return confirm(
                                'Resend the Consultation Closure Agreement to the customer?'
                            );">

                            ✉️ Resend Closure Agreement

                        </button>

                    </div>

                </form>


            <?php else: ?>

                <div class="alert alert-warning">

                    <strong>
                        Important:
                    </strong>

                    The customer has requested closure of this
                    consultation request.

                    <br><br>

                    <strong>
                        Do not close the request at this stage.
                    </strong>

                    The administrator must first contact the
                    customer and confirm the request by telephone.

                    After the call, the customer must receive and
                    complete the existing Consultation Closure Agreement.

                </div>


                <form method="post">

                    <div class="mb-4">

                        <label
                            for="closure_notes"
                            class="form-label fw-bold">

                            Closure Notes
                            <span class="text-danger">*</span>

                        </label>

                        <textarea
                            name="closure_notes"
                            id="closure_notes"
                            class="form-control"
                            rows="5"
                            required
                            placeholder="Record the details of the conversation with the customer and any relevant administrative notes."></textarea>

                        <div class="form-text">

                            These notes are saved with the request
                            and remain available during the closure review.

                        </div>

                    </div>


                    <div class="d-flex justify-content-between">

                        <a
                            href="?page=needs-admin-review"
                            class="btn btn-secondary">

                            ← Cancel

                        </a>


                        <button
                            type="submit"
                            name="send_closure_agreement"
                            value="1"
                            class="btn btn-danger"
                            onclick="return confirm(
                                'Have you contacted the customer and confirmed the closure request?'
                            );">

                            ✉️ Send Closure Agreement

                        </button>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    </div>

</div>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>