<?php

if (!isset($_SESSION['customer'])) {
    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/WorkflowHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$customerId = (int) $_SESSION['customer']['id'];
$requestId = (int) ($_GET['request_id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Customer Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.*,
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

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}



/*
|--------------------------------------------------------------------------
| Only Closed / Archived Requests
|--------------------------------------------------------------------------
*/

if (
    $request['workflow_stage'] !== 'Closed'
    && $request['workflow_stage'] !== 'Archived'
) {
    die('This request is not an inactive request.');
}

/*
|--------------------------------------------------------------------------
| Only Closed / Archived Requests
|--------------------------------------------------------------------------
*/

if (
    $request['workflow_stage'] !== 'Closed'
    && $request['workflow_stage'] !== 'Archived'
) {
    die('This request is not an inactive request.');
}

/*
|--------------------------------------------------------------------------
| Customer-Visible Request History
|--------------------------------------------------------------------------
*/

$events = RequestEventHelper::getCustomerVisible(
    $pdo,
    $requestId
);

/*
|--------------------------------------------------------------------------
| Customer-Visible Request History
|--------------------------------------------------------------------------
*/

/*
$events = RequestEventHelper::getCustomerVisible(
    $pdo,
    $requestId
);
*/

/*
|--------------------------------------------------------------------------
| Page Status
|--------------------------------------------------------------------------
*/

$isArchived = ($request['workflow_stage'] === 'Archived');

?>

<?php require dirname(__DIR__) . '/layouts/header-customer.php'; ?>


<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h1 class="mb-1">
                Request #<?= (int) $request['id'] ?>
            </h1>

            <div class="text-muted">
                <?= htmlspecialchars($request['service_title']) ?>
            </div>

        </div>

        <?php if ($isArchived): ?>

            <span class="badge bg-secondary fs-6">
                Archived
            </span>

        <?php else: ?>

            <span class="badge bg-success fs-6">
                Closed
            </span>

        <?php endif; ?>

    </div>


    <!-- Request Information -->

    <div class="card mb-4">

        <div class="card-header">
            <strong>Request Information</strong>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-6">

                    <strong>Request #</strong>

                    <div>
                        #<?= (int) $request['id'] ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Service</strong>

                    <div>
                        <?= htmlspecialchars($request['service_title']) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Status</strong>

                    <div>
                        <?= htmlspecialchars($request['job_status']) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Workflow Stage</strong>

                    <div>

                        <?php if ($isArchived): ?>

                            <span class="badge bg-secondary">
                                Archived
                            </span>

                        <?php else: ?>

                            <span class="badge bg-success">
                                Closed
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Request Date</strong>

                    <div>
                        <?= formatDateTime($request['created_at']) ?>
                    </div>

                </div>


                <?php if (!empty($request['quoted_price'])): ?>

                    <div class="col-md-6">

                        <strong>Quoted Price</strong>

                        <div>
                            AED
                            <?= number_format(
                                (float) $request['quoted_price'],
                                2
                            ) ?>
                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- Inactive Request Notice -->

    <div class="alert alert-secondary">

        <?php if ($isArchived): ?>

            <strong>This request is archived.</strong>

            <br>

            This request is retained as part of the company's
            record retention process and is available for viewing
            only.

        <?php else: ?>

            <strong>This request is closed.</strong>

            <br>

            This request is no longer active and is available
            for viewing only.

        <?php endif; ?>

    </div>

    <?php if (!empty($events)): ?>

    <div class="card mt-4">

        <div class="card-header bg-primary text-white">
            <strong>Request History</strong>
        </div>

        <div class="card-body">

            <?php foreach ($events as $event): ?>

                <div class="border-bottom pb-3 mb-3">

                    <div class="fw-bold">
                        <?= htmlspecialchars($event['event_title']) ?>
                    </div>

                    <div class="small text-muted">
                        <?= date('d M Y h:i A', strtotime($event['created_at'])) ?>
                    </div>

                    <?php
$customerDescription = match ($event['event_code']) {

    'CLOSURE_AGREEMENT_SENT' =>
        'The Consultation Closure Agreement was sent for your review.',

    'CLOSURE_AGREEMENT_RESENT' =>
        'The Consultation Closure Agreement was resent for your review.',

    default =>
        $event['event_description'] ?? ''
};
?>

<?php if ($customerDescription !== ''): ?>

    <div class="mt-2">
        <?= nl2br(htmlspecialchars($customerDescription)) ?>
    </div>

<?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

<?php endif; ?>


    <!-- Customer Actions -->

    <div class="mt-4">

        <a
            href="?page=customer-requests"
            class="btn btn-secondary">

            ← Back to My Requests

        </a>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>