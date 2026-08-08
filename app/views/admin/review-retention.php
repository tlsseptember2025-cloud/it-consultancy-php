<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/retention_review_helper.php';

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
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
                    ? nl2br(htmlspecialchars($request['description']))
                    : '<span class="text-muted">No description recorded.</span>' ?>

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
                            ? formatDateTime($request['archived_at'])
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


    <!-- Legal Hold -->

    <div class="card mb-4">

        <div class="card-header bg-warning">
            Legal Hold
        </div>

        <div class="card-body">

            <?php if (!empty($request['legal_hold'])): ?>

                <span class="badge bg-danger">
                    Legal Hold Active
                </span>

                <p class="text-muted mt-2 mb-0">
                    This request is currently protected from normal
                    retention expiry processing.
                </p>

            <?php else: ?>

                <span class="badge bg-success">
                    No Legal Hold
                </span>

                <p class="text-muted mt-2 mb-0">
                    This request is not currently under Legal Hold.
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
                    ? formatDateTime($request['completed_at'])
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
                    : '<span class="text-muted">No completion notes recorded.</span>' ?>

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