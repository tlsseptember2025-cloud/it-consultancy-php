<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/retention_review_helper.php';
require_once APP_PATH . '/helpers/retention_extension_helper.php';
require_once APP_PATH . '/helpers/legal_hold_helper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once APP_PATH . '/helpers/legal_hold_release_helper.php';
require_once APP_PATH . '/helpers/retention_export_helper.php';

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Extend Retention
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['extend_retention'])
) {

    $success = extendRetention($pdo, $requestId);

    if (!$success) {
        die('This request is not eligible for a retention extension.');
    }


    /*
    |--------------------------------------------------------------------------
    | Identify Which Retention Extension Was Approved
    |--------------------------------------------------------------------------
    */

    $extensionStmt = $pdo->prepare("
        SELECT
            retention_extension_years,
            retention_review_at,
            retention_expires_at
        FROM requests
        WHERE id = ?
        LIMIT 1
    ");

    $extensionStmt->execute([$requestId]);

    $extensionData = $extensionStmt->fetch(PDO::FETCH_ASSOC);

    $extensionNumber = (int) (
        $extensionData['retention_extension_years'] ?? 0
    );

    if ($extensionNumber === 1) {

        $eventTitle = 'Retention Extension 1 of 2';

        $eventDescription =
            'Administrator approved the first one-year retention extension. '
            . '5-year retention → 6-year retention. '
            . 'Next review: '
            . (
                !empty($extensionData['retention_review_at'])
                    ? formatDateTime(
                        $extensionData['retention_review_at']
                    )
                    : '-'
            )
            . '.';

    } elseif ($extensionNumber === 2) {

        $eventTitle = 'Retention Extension 2 of 2';

        $eventDescription =
            'Administrator approved the second one-year retention extension. '
            . '6-year retention → 7-year maximum retention. '
            . 'Final expiry: '
            . (
                !empty($extensionData['retention_expires_at'])
                    ? formatDateTime(
                        $extensionData['retention_expires_at']
                    )
                    : '-'
            )
            . '.';

    } else {

        $eventTitle = 'Retention Extension';

        $eventDescription =
            'Administrator approved a one-year retention extension.';
    }


    /*
    |--------------------------------------------------------------------------
    | Record Retention Extension Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::add(
        $pdo,
        $requestId,
        'RETENTION_EXTENDED',
        RequestEventHelper::TYPE_SYSTEM,
        $eventTitle,
        $eventDescription,
        RequestEventHelper::SOURCE_ADMINISTRATOR,
        null
    );


    header(
        'Location: ?page=review-retention&id='
        . $requestId
        . '&success=retention-extended'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Place Legal Hold
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['place_legal_hold'])
) {

    $reason = trim($_POST['legal_hold_reason'] ?? '');

    if ($reason === '') {
        die('A Legal Hold reason is required.');
    }


    $success = placeLegalHold(
        $pdo,
        $requestId,
        $reason,
        null
    );

    if (!$success) {
        die('This request is not eligible for Legal Hold.');
    }


    RequestEventHelper::add(
        $pdo,
        $requestId,
        'LEGAL_HOLD_PLACED',
        RequestEventHelper::TYPE_SYSTEM,
        'Legal Hold Placed',
        'Administrator placed the request under Legal Hold. Reason: '
            . $reason,
        RequestEventHelper::SOURCE_ADMINISTRATOR,
        null
    );


    header(
        'Location: ?page=review-retention&id='
        . $requestId
        . '&success=legal-hold-placed'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Release Legal Hold
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['release_legal_hold'])
) {

    $success = releaseLegalHold(
        $pdo,
        $requestId,
        null
    );

    if (!$success) {
        die('This request does not have an active Legal Hold.');
    }


    RequestEventHelper::add(
        $pdo,
        $requestId,
        'LEGAL_HOLD_RELEASED',
        RequestEventHelper::TYPE_SYSTEM,
        'Legal Hold Released',
        'Administrator released the Legal Hold on this request.',
        RequestEventHelper::SOURCE_ADMINISTRATOR,
        null
    );


    header(
        'Location: ?page=review-retention&id='
        . $requestId
        . '&success=legal-hold-released'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Load Archived Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        c.email,
        c.phone,
        s.title AS service_title,
        a.name AS agent_name

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    WHERE r.id = ?
      AND r.workflow_stage = 'Archived'

    LIMIT 1
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Archived request not found.');
}


require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<div class="container-fluid mt-4">

    <?php if (
    isset($_GET['success'])
    && $_GET['success'] === 'retention-exported'
): ?>

    <div
        class="alert alert-success alert-dismissible fade show"
        role="alert"
    >

        Retention export created successfully.

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>

    </div>

<?php endif; ?>


    <?php if (
        isset($_GET['success'])
        && $_GET['success'] === 'retention-extended'
    ): ?>

        <div class="alert alert-success alert-dismissible fade show" role="alert">

            Retention has been extended by one year.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['success'])
        && $_GET['success'] === 'legal-hold-placed'
    ): ?>

        <div class="alert alert-warning alert-dismissible fade show" role="alert">

            Legal Hold has been placed on this request.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

    <?php endif; ?>


    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Retention Review
            </h2>

            <p class="text-muted mb-0">
                Read-only retention information for Request
                #<?= (int) $request['id'] ?>.
            </p>

        </div>

        <span class="badge bg-warning text-dark">
            Retention Review
        </span>

    </div>


    <!-- Request Information -->

    <div class="card mb-4">

        <div class="card-header bg-dark text-white">
            Request Information
        </div>

        <div class="card-body">

            <p>
                <strong>Request ID:</strong>
                #<?= (int) $request['id'] ?>
            </p>

            <p>
                <strong>Customer:</strong>
                <?= htmlspecialchars($request['customer_name']) ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= htmlspecialchars($request['email']) ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?= htmlspecialchars($request['phone']) ?>
            </p>

            <p>
                <strong>Service:</strong>
                <?= htmlspecialchars($request['service_title']) ?>
            </p>

            <p>
                <strong>Assigned Agent:</strong>

                <?= !empty($request['agent_name'])
                    ? htmlspecialchars($request['agent_name'])
                    : '-' ?>

            </p>

            <p>
                <strong>Workflow Stage:</strong>

                <span class="badge bg-secondary">
                    Archived
                </span>

            </p>

            <p class="mb-0">
                <strong>Description:</strong>
            </p>

            <div class="border rounded p-3 mt-2">

                <?= !empty($request['description'])
                    ? nl2br(
                        htmlspecialchars(
                            $request['description']
                        )
                    )
                    : '<span class="text-muted">
                        No description recorded.
                       </span>' ?>

            </div>

        </div>

    </div>


    <!-- Retention Information -->

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            Retention Information
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6 mb-3">

                    <strong>Archived At</strong>

                    <div class="mt-1">

                        <?= !empty($request['archived_at'])
                            ? formatDateTime(
                                $request['archived_at']
                            )
                            : '-' ?>

                    </div>

                </div>


                <div class="col-md-6 mb-3">

                    <strong>Retention Review At</strong>

                    <div class="mt-1">

                        <?= !empty($request['retention_review_at'])
                            ? formatDateTime(
                                $request['retention_review_at']
                            )
                            : '-' ?>

                    </div>

                </div>


                <div class="col-md-6 mb-3">

                    <strong>Retention Expires At</strong>

                    <div class="mt-1">

                        <?= !empty($request['retention_expires_at'])
                            ? formatDateTime(
                                $request['retention_expires_at']
                            )
                            : '-' ?>

                    </div>

                </div>


                <div class="col-md-6 mb-3">

                    <strong>Retention Extension</strong>

                    <div class="mt-1">

                        <?= (int) (
                            $request['retention_extension_years'] ?? 0
                        ) ?>

                        year(s)

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Legal Hold Status -->

    <div class="card mb-4">

        <div class="card-header bg-warning">
            Legal Hold
        </div>

        <div class="card-body">

            <?php if (!empty($request['legal_hold'])): ?>

                <span class="badge bg-danger">
                    Legal Hold Active
                </span>

                <?php if (!empty($request['legal_hold_at'])): ?>

                    <p class="mt-2 mb-1">

                        <strong>Placed At:</strong>

                        <?= formatDateTime(
                            $request['legal_hold_at']
                        ) ?>

                    </p>

                <?php endif; ?>


                <?php if (!empty($request['legal_hold_reason'])): ?>

                    <p class="mb-0">

                        <strong>Reason:</strong>

                        <?= nl2br(
                            htmlspecialchars(
                                $request['legal_hold_reason']
                            )
                        ) ?>

                    </p>

                <?php endif; ?>


<hr class="my-3">

<form method="POST">

    <p class="mb-2">
        <strong>Release Legal Hold</strong>
    </p>

    <p class="text-muted small">
        Releasing the Legal Hold does not delete this request.
        The request will remain archived.
    </p>

    <button
        type="submit"
        name="release_legal_hold"
        value="1"
        class="btn btn-danger"
        onclick="return confirm(
            'Are you sure you want to release the Legal Hold?'
        );"
    >
        Release Legal Hold
    </button>

</form>

            <?php else: ?>

                <span class="badge bg-success">
                    No Legal Hold
                </span>

                <p class="text-muted mt-2 mb-0">

                    This request is not currently under
                    Legal Hold.

                </p>

            <?php endif; ?>

        </div>

    </div>

    <!-- Retention Decision -->

<div class="card mb-4">

    <div class="card-header bg-success text-white">
        Retention Decision
    </div>

    <div class="card-body">

        <?php

        $extensionYears = (int) (
            $request['retention_extension_years'] ?? 0
        );

        $now = new DateTime();

        $reviewDate = !empty(
            $request['retention_review_at']
        )
            ? new DateTime(
                $request['retention_review_at']
            )
            : null;


        /*
        |--------------------------------------------------------------------------
        | Retention Extension Available
        |--------------------------------------------------------------------------
        */

        $canExtend = (
            $reviewDate
            && $now >= $reviewDate
            && empty($request['legal_hold'])
            && $extensionYears < 2
        );


        /*
        |--------------------------------------------------------------------------
        | 7-Year Final Review
        |--------------------------------------------------------------------------
        |
        | Only applies when the request has NOT already had a
        | Legal Hold released.
        |
        */

        $finalReviewDue = (
            $reviewDate
            && $now >= $reviewDate
            && $extensionYears === 2
            && empty($request['legal_hold'])
            && empty($request['legal_hold_released_at'])
        );


        /*
        |--------------------------------------------------------------------------
        | Final Disposition Required
        |--------------------------------------------------------------------------
        |
        | Applies after a Legal Hold has been released.
        |
        */

        $finalDispositionDue = (
            $reviewDate
            && $now >= $reviewDate
            && $extensionYears === 2
            && empty($request['legal_hold'])
            && !empty($request['legal_hold_released_at'])
        );

        ?>


        <?php if ($canExtend): ?>

            <p>

                This request is eligible for a
                <strong>
                    one-year retention extension
                </strong>.

            </p>


            <form method="POST">

                <button
                    type="submit"
                    name="extend_retention"
                    value="1"
                    class="btn btn-success"
                    onclick="return confirm(
                        'Are you sure you want to extend retention by one year?'
                    );"
                >

                    Extend Retention 1 Year

                </button>

            </form>


        <?php elseif (!empty($request['legal_hold'])): ?>

            <p class="text-muted mb-0">

                This request is currently under
                <strong>Legal Hold</strong>.

            </p>


        <?php elseif ($finalDispositionDue): ?>

    <h5 class="mb-3">
        Final Disposition Required
    </h5>

    <p class="text-muted">

        The 7-year maximum retention period has been reached
        and the Legal Hold has been released.

    </p>

    <p>
        This request now requires a final retention
        disposition.
    </p>


    <?php if (canExportRetentionRequest($request)): ?>

        <div class="alert alert-info">

            <strong>Step 1 — Export Record</strong>

            <div class="mt-1">
                Create a permanent PDF preservation copy
                before the request is considered for deletion.
            </div>

        </div>


        <a
            href="?page=export-retention&id=<?= (int) $request['id'] ?>"
            class="btn btn-primary"
        >
            Export Retention Record
        </a>

    <?php else: ?>

        <div class="alert alert-warning mb-0">

            This request is not currently eligible
            for final retention export.

        </div>

    <?php endif; ?>


        <?php elseif ($finalReviewDue): ?>

            <h5 class="mb-3">
                7-Year Final Review
            </h5>


            <p class="text-muted">

                The normal 7-year retention period has
                been reached.

            </p>


            <p>

                Legal Hold may be used when the request
                must be preserved beyond normal retention.

            </p>


            <form method="POST">

                <div class="mb-3">

                    <label
                        for="legal_hold_reason"
                        class="form-label"
                    >
                        Legal Hold Reason
                    </label>


                    <textarea
                        name="legal_hold_reason"
                        id="legal_hold_reason"
                        class="form-control"
                        rows="4"
                        required
                    ></textarea>

                </div>


                <button
                    type="submit"
                    name="place_legal_hold"
                    value="1"
                    class="btn btn-warning"
                    onclick="return confirm(
                        'Place this request under Legal Hold?'
                    );"
                >

                    Place Legal Hold

                </button>

            </form>


        <?php else: ?>

            <p class="text-muted mb-0">

                No retention action is currently available.

            </p>

        <?php endif; ?>

    </div>

</div>

    <!-- Completion Information -->

    <div class="card mb-4">

        <div class="card-header bg-secondary text-white">
            Completion Information
        </div>

        <div class="card-body">

            <p>

                <strong>Completed At:</strong>

                <?= !empty($request['completed_at'])
                    ? formatDateTime(
                        $request['completed_at']
                    )
                    : '-' ?>

            </p>


            <p class="mb-0">

                <strong>Completion Notes:</strong>

            </p>


            <div class="border rounded p-3 mt-2">

                <?= !empty($request['completion_notes'])
                    ? nl2br(
                        htmlspecialchars(
                            $request['completion_notes']
                        )
                    )
                    : '<span class="text-muted">
                        No completion notes recorded.
                       </span>' ?>

            </div>

        </div>

    </div>


    <a
        href="?page=retention-review"
        class="btn btn-secondary"
    >
        Back to Retention Review
    </a>


</div>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>