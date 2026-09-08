<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once APP_PATH . '/helpers/DateHelper.php';


/*
|--------------------------------------------------------------------------
| Get Lead ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($id <= 0) {

    header("Location: ?page=pending-contract-leads");
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Lead
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM contract_leads
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$lead = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$lead) {

    die('Lead not found.');
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$error = '';


/*
|--------------------------------------------------------------------------
| Process Approval / Rejection
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['lead_action'])
) {

    $action = $_POST['lead_action'];


    /*
    |--------------------------------------------------------------------------
    | Security / Workflow Check
    |--------------------------------------------------------------------------
    |
    | Only Pending submissions can be approved or rejected.
    |
    */

    if ($lead['approval_status'] !== 'Pending') {

        $error =
            'This lead has already been processed and cannot be modified.';

    }


    /*
    |--------------------------------------------------------------------------
    | Approve Lead
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'approve') {

        $stmt = $pdo->prepare("
            UPDATE contract_leads
            SET
                approval_status = 'Approved',
                status = 'New'
            WHERE id = ?
            AND approval_status = 'Pending'
        ");

        $stmt->execute([$id]);


        if ($stmt->rowCount() === 0) {

            $error =
                'This lead has already been processed.';

        } else {

            header("Location: ?page=contract-leads");
            exit;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Reject / Delete Lead
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'reject') {

        $stmt = $pdo->prepare("
            DELETE FROM contract_leads
            WHERE id = ?
            AND approval_status = 'Pending'
        ");

        $stmt->execute([$id]);


        if ($stmt->rowCount() === 0) {

            $error =
                'This lead has already been processed.';

        } else {

            header("Location: ?page=pending-contract-leads");
            exit;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Invalid Action
    |--------------------------------------------------------------------------
    */

    else {

        $error =
            'Invalid lead action.';

    }

}


/*
|--------------------------------------------------------------------------
| Decode Services
|--------------------------------------------------------------------------
*/

$services = [];

if (!empty($lead['support_services'])) {

    $decodedServices =
        json_decode(
            $lead['support_services'],
            true
        );

    if (is_array($decodedServices)) {

        $services = $decodedServices;

    }

}


/*
|--------------------------------------------------------------------------
| Public Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<!--
|--------------------------------------------------------------------------
| Page Header
|--------------------------------------------------------------------------
-->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="mb-1">
            Review Company Lead
        </h2>

        <p class="text-muted mb-0">
            Review this submission before approving it as an active lead.
        </p>

    </div>


    <a
        href="?page=pending-contract-leads"
        class="btn btn-outline-secondary">

        ← Pending Leads

    </a>

</div>


<?php if ($error !== ''): ?>

    <div class="alert alert-danger">

        <strong>
            Unable to process lead
        </strong>

        <br>

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| Lead Information
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>
            Company Information
        </strong>

    </div>


    <div class="card-body">


        <div class="row">


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    Company Name
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['company_name']
                    ) ?>"
                    readonly>

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    Contact Person
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['contact_person']
                    ) ?>"
                    readonly>

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    Email Address
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['email']
                    ) ?>"
                    readonly>

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label">
                    Phone Number
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= !empty($lead['phone'])
                        ? htmlspecialchars($lead['phone'])
                        : 'Not provided'
                    ?>"
                    readonly>

            </div>


        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Service Requirements
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>
            Service Requirements
        </strong>

    </div>


    <div class="card-body">


        <div class="mb-4">

            <label class="form-label">
                Services Interested In
            </label>


            <div>

                <?php if (!empty($services)): ?>

                    <?php foreach ($services as $service): ?>

                        <span
                            class="badge bg-light text-dark border me-1 mb-1">

                            <?= htmlspecialchars($service) ?>

                        </span>

                    <?php endforeach; ?>

                <?php else: ?>

                    <span class="text-muted">
                        Not specified
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <div class="row">


            <div class="col-md-4 mb-3">

                <label class="form-label">
                    Contract Preference
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= !empty($lead['contract_term'])
                        ? htmlspecialchars($lead['contract_term'])
                        : 'Not specified'
                    ?>"
                    readonly>

            </div>


            <div class="col-md-4 mb-3">

                <label class="form-label">
                    Support Coverage
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= !empty($lead['support_coverage'])
                        ? htmlspecialchars($lead['support_coverage'])
                        : 'Not specified'
                    ?>"
                    readonly>

            </div>


            <div class="col-md-4 mb-3">

                <label class="form-label">
                    Start Timeframe
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= !empty($lead['start_timeframe'])
                        ? htmlspecialchars($lead['start_timeframe'])
                        : 'Not specified'
                    ?>"
                    readonly>

            </div>


        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Marketing Preference
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>
            Marketing Preference
        </strong>

    </div>


    <div class="card-body">

        <?php if ((int)$lead['marketing_consent'] === 1): ?>

            <span class="badge bg-success">
                Yes — opted in to updates and special offers
            </span>

        <?php else: ?>

            <span class="badge bg-secondary">
                No — did not opt in
            </span>

        <?php endif; ?>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Submission Information
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>
            Submission Information
        </strong>

    </div>


    <div class="card-body">


        <div class="row">


            <div class="col-md-6">

                <strong>
                    Submitted:
                </strong>

                <?= formatDateTime(
                    $lead['created_at']
                ) ?>

            </div>


            <div class="col-md-6">

                <strong>
                    Review Status:
                </strong>

                <span class="badge bg-warning text-dark">
                    Pending
                </span>

            </div>


        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Approval Actions
|--------------------------------------------------------------------------
-->

<?php if ($lead['approval_status'] === 'Pending'): ?>


    <div class="card shadow-sm border-warning mb-4">

        <div class="card-body">


            <h5 class="mb-3">
                Administrator Decision
            </h5>


            <p class="text-muted">

                Review the information above before deciding whether
                this submission is a genuine company lead.

            </p>


            <div class="d-flex gap-2">


                <!-- Approve -->

                <form
                    method="POST"
                    onsubmit="return confirm(
                        'Approve this company lead? It will be added to the active leads list as New.'
                    );">

                    <input
                        type="hidden"
                        name="lead_action"
                        value="approve">

                    <button
                        type="submit"
                        class="btn btn-success">

                        ✓ Approve Lead

                    </button>

                </form>


                <!-- Reject -->

                <form
                    method="POST"
                    onsubmit="return confirm(
                        'Reject this submission? It will be permanently deleted as spam or an unwanted submission.'
                    );">

                    <input
                        type="hidden"
                        name="lead_action"
                        value="reject">

                    <button
                        type="submit"
                        class="btn btn-danger">

                        ✕ Reject & Delete

                    </button>

                </form>


                <a
                    href="?page=pending-contract-leads"
                    class="btn btn-secondary">

                    Cancel

                </a>


            </div>

        </div>

    </div>


<?php else: ?>


    <div class="alert alert-warning">

        This lead has already been processed and cannot be modified.

    </div>


<?php endif; ?>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>