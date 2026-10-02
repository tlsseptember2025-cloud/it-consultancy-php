<?php

require_once CONFIG_PATH . '/demo-database.php';

if (!isset($_SESSION['demo_user'])) {
    header('Location: ?page=demo-login');
    exit;
}

$adminId = (int) ($_SESSION['demo_user']['id'] ?? 0);
$tenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

if ($adminId <= 0 || $tenantId <= 0) {
    unset($_SESSION['demo_user']);
    header('Location: ?page=demo-login');
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Demo tenant
|--------------------------------------------------------------------------
*/
$tenantStmt = $demoPdo->prepare("
    SELECT
        id,
        company_name,
        started_at,
        expires_at,
        status
    FROM demo_tenants
    WHERE id = ?
    LIMIT 1
");

$tenantStmt->execute([$tenantId]);
$tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant || ($tenant['status'] ?? '') !== 'Active') {
    /*
     * An extension request that was already submitted may be approved
     * after the original 5-day period ends, so the normal expired-demo
     * login protection remains separate from the extension request itself.
     */
    unset($_SESSION['demo_user']);
    header('Location: ?page=demo-login');
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Demo Admin account
|--------------------------------------------------------------------------
*/
$adminStmt = $demoPdo->prepare("
    SELECT id
    FROM users
    WHERE id = ?
      AND demo_tenant_id = ?
      AND is_demo_account = 1
      AND is_super_admin = 0
    LIMIT 1
");

$adminStmt->execute([$adminId, $tenantId]);

if (!$adminStmt->fetchColumn()) {
    unset($_SESSION['demo_user']);
    header('Location: ?page=demo-login');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Existing extension request
|--------------------------------------------------------------------------
*/
$requestStmt = $demoPdo->prepare("
    SELECT *
    FROM demo_extension_requests
    WHERE demo_tenant_id = ?
    LIMIT 1
");

$requestStmt->execute([$tenantId]);
$extensionRequest = $requestStmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Calculate current Demo period
|--------------------------------------------------------------------------
*/
$startedAt = $tenant['started_at'] ?? null;
$expiresAt = $tenant['expires_at'] ?? null;

$startedTimestamp = $startedAt ? strtotime($startedAt) : false;
$expiresTimestamp = $expiresAt ? strtotime($expiresAt) : false;

$now = time();

$secondsRemaining = null;
$daysRemaining = null;
$isFinalDay = false;
$isExpired = false;

if ($expiresTimestamp !== false) {
    $secondsRemaining = $expiresTimestamp - $now;

    $isExpired = $secondsRemaining <= 0;

    if (!$isExpired) {
        $daysRemaining = max(
            1,
            (int) ceil($secondsRemaining / 86400)
        );

        /*
         * Extension requests are allowed only during the
         * final 24 hours of the current Demo period.
         */
        $isFinalDay = $secondsRemaining <= 86400;
    }
}

/*
|--------------------------------------------------------------------------
| POST - Submit extension request
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($submittedToken === '' || !hash_equals($csrfToken, $submittedToken)) {

        $error = 'Invalid security token. Please refresh the page and try again.';

    } elseif ($extensionRequest) {

        $error = 'An extension request has already been submitted for this Demo company. Only one extension request is allowed.';

    } elseif ($expiresTimestamp === false) {

        $error = 'The Demo expiry date is invalid. Please contact the Demo Super Admin.';

    } elseif ($isExpired) {

        $error = 'The Demo period has already expired. An extension request cannot be submitted after expiration.';

    } elseif (!$isFinalDay) {

        $error = 'An extension request can only be submitted during the final day of the Demo period.';

    } else {

        try {

            $insertStmt = $demoPdo->prepare("
                INSERT INTO demo_extension_requests
                    (
                        demo_tenant_id,
                        requested_by_user_id,
                        status
                    )
                VALUES
                    (
                        ?,
                        ?,
                        'Pending'
                    )
            ");

            $insertStmt->execute([
                $tenantId,
                $adminId
            ]);

            $success = 'Your 5-day extension request has been sent to the Demo Super Admin for review.';

            /*
             * Reload request after successful submission.
             */
            $requestStmt->execute([$tenantId]);
            $extensionRequest = $requestStmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {

                $error = 'An extension request has already been submitted for this Demo company. Only one extension request is allowed.';

            } else {

                error_log(
                    'Demo extension request failed: ' .
                    $e->getMessage()
                );

                $error = 'The extension request could not be submitted. Please try again later.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Recalculate remaining time after POST
|--------------------------------------------------------------------------
*/
$expiresAt = $tenant['expires_at'] ?? null;
$expiresTimestamp = $expiresAt ? strtotime($expiresAt) : false;

$secondsRemaining = null;
$daysRemaining = null;
$isFinalDay = false;
$isExpired = false;

if ($expiresTimestamp !== false) {

    $secondsRemaining = $expiresTimestamp - time();

    $isExpired = $secondsRemaining <= 0;

    if (!$isExpired) {

        $daysRemaining = max(
            1,
            (int) ceil($secondsRemaining / 86400)
        );

        $isFinalDay = $secondsRemaining <= 86400;
    }
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1">Demo Extension Request</h1>
            <p class="text-muted mb-0">
                Request one additional 5-day Demo period from the Demo Super Admin.
            </p>
        </div>

        <a href="?page=dashboard" class="btn btn-outline-secondary">
            Back to Dashboard
        </a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Demo Period -->
        <div class="col-lg-7">

            <div class="card shadow-sm h-100">

                <div class="card-header bg-dark text-white">
                    <strong>Demo Period</strong>
                </div>

                <div class="card-body">

                    <dl class="row mb-0">

                        <dt class="col-sm-4">Company</dt>
                        <dd class="col-sm-8">
                            <?= htmlspecialchars(
                                $tenant['company_name'] ?? 'Demo Company',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </dd>

                        <dt class="col-sm-4">Started</dt>
<dd class="col-sm-8">
    <?php
        try {
            $startedDisplayDate = new DateTimeImmutable(
                (string) ($tenant['started_at'] ?? ''),
                new DateTimeZone('Asia/Dubai')
            );

            $startedDisplay = $startedDisplayDate->format(
                'd/m/Y h:i A'
            );
        } catch (Exception $e) {
            $startedDisplay = $tenant['started_at'] ?? '—';
        }
    ?>

    <?= htmlspecialchars(
        $startedDisplay,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</dd>

<dt class="col-sm-4">Current Expiry</dt>
<dd class="col-sm-8 fw-semibold">
    <?php
        try {
            $expiryDisplayDate = new DateTimeImmutable(
                (string) ($expiresAt ?? ''),
                new DateTimeZone('Asia/Dubai')
            );

            $expiryDisplay = $expiryDisplayDate->format(
                'd/m/Y h:i A'
            );
        } catch (Exception $e) {
            $expiryDisplay = $expiresAt ?? '—';
        }
    ?>

    <?= htmlspecialchars(
        $expiryDisplay,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</dd>

                        <dt class="col-sm-4">Approx. Days Remaining</dt>
                        <dd class="col-sm-8">
                            <?= $daysRemaining === null
                                ? '—'
                                : number_format($daysRemaining) ?>
                        </dd>

                    </dl>

                </div>
            </div>

        </div>

        <!-- Extension -->
        <div class="col-lg-5">

            <div class="card shadow-sm border-primary">

                <div class="card-header bg-primary text-white">
                    <strong>Extension</strong>
                </div>

                <div class="card-body">

                    <p>
                        The standard Demo period is 5 days.
                        A company may request one additional 5-day period.
                        The maximum total Demo period is 10 days.
                    </p>

                    <?php if ($extensionRequest): ?>

                        <?php
                        $status = (string) (
                            $extensionRequest['status'] ?? 'Pending'
                        );

                        $badgeClass = match ($status) {
                            'Approved' => 'bg-success',
                            'Rejected' => 'bg-danger',
                            default => 'bg-warning text-dark',
                        };
                        ?>

                        <div class="alert alert-light border mb-0">

                            <div class="mb-2">

                                <strong>Request status:</strong>

                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>

                            <div class="small text-muted">
    <?php
        try {
            $requestedDisplayDate = new DateTimeImmutable(
                (string) ($extensionRequest['requested_at'] ?? ''),
                new DateTimeZone('Asia/Dubai')
            );

            $requestedDisplay = $requestedDisplayDate->format(
                'd/m/Y h:i A'
            );
        } catch (Exception $e) {
            $requestedDisplay = $extensionRequest['requested_at'] ?? '—';
        }
    ?>

    Requested <?= htmlspecialchars(
        $requestedDisplay,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</div>

                            <?php if (!empty($extensionRequest['review_notes'])): ?>

                                <div class="mt-2">

                                    <strong>Super Admin note:</strong><br>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $extensionRequest['review_notes'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    <?php elseif ($isFinalDay && !$isExpired): ?>

                        <div class="alert alert-warning">

                            <strong>This is your last day.</strong>

                            <div class="mt-1">
                                You can request a 5-day extension.
                            </div>

                        </div>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $csrfToken,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-calendar-plus me-1"></i>
                                Request 5-Day Extension
                            </button>

                        </form>

                    <?php elseif (!$isExpired && $daysRemaining === 2): ?>

                        <div class="alert alert-info mb-0">

                            <strong>You have 2 days left.</strong>

                            <div class="mt-1">
                                You can request a 5-day extension on your last day.
                            </div>

                        </div>

                    <?php elseif (!$isExpired && $daysRemaining !== null && $daysRemaining > 2): ?>

                        <div class="alert alert-secondary mb-0">

                            Extension requests become available during
                            the final day of the Demo period.

                        </div>

                    <?php else: ?>

                        <div class="alert alert-warning mb-0">

                            The Demo period has expired and can no longer
                            be extended through a new request.

                        </div>

                    <?php endif; ?>

                </div>
            </div>

        </div>

    </div>

</div>