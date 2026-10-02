<?php

require_once CONFIG_PATH . '/demo-database.php';

if (!isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-login');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$superAdminId = (int) ($_SESSION['demo_super_admin']['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $reviewNotes = trim((string) ($_POST['review_notes'] ?? ''));

    if ($submittedToken === '' || !hash_equals($csrfToken, $submittedToken)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } elseif ($requestId <= 0 || !in_array($decision, ['Approved', 'Rejected'], true)) {
        $error = 'Invalid extension request decision.';
    } else {
        try {
            $demoPdo->beginTransaction();

            $requestStmt = $demoPdo->prepare("
                SELECT
                    er.id,
                    er.demo_tenant_id,
                    er.status,
                    t.started_at,
                    t.expires_at,
                    t.status AS tenant_status
                FROM demo_extension_requests er
                INNER JOIN demo_tenants t
                    ON t.id = er.demo_tenant_id
                WHERE er.id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $requestStmt->execute([$requestId]);
            $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new RuntimeException('Extension request not found.');
            }

            if (($request['status'] ?? '') !== 'Pending') {
                throw new RuntimeException('This extension request has already been reviewed.');
            }

            if ($decision === 'Approved') {
                $startedAt = strtotime((string) $request['started_at']);
                $currentExpiry = strtotime((string) $request['expires_at']);

                if ($startedAt === false || $currentExpiry === false) {
                    throw new RuntimeException('The Demo tenant dates are invalid.');
                }

                // Hard cap: the Demo may never exceed 10 days from its original start.
                $maximumExpiry = strtotime('+10 days', $startedAt);
                $extendedExpiry = min(
                    strtotime('+5 days', $currentExpiry),
                    $maximumExpiry
                );

                if ($extendedExpiry <= $currentExpiry) {
                    throw new RuntimeException('The Demo tenant is already at its maximum 10-day period.');
                }

                $updateTenant = $demoPdo->prepare("
                    UPDATE demo_tenants
                    SET expires_at = ?, status = 'Active'
                    WHERE id = ?
                    LIMIT 1
                ");
                $updateTenant->execute([
                    date('Y-m-d H:i:s', $extendedExpiry),
                    (int) $request['demo_tenant_id']
                ]);
            }

            $updateRequest = $demoPdo->prepare("
                UPDATE demo_extension_requests
                SET
                    status = ?,
                    reviewed_by_user_id = ?,
                    reviewed_at = CURRENT_TIMESTAMP,
                    review_notes = ?
                WHERE id = ?
                  AND status = 'Pending'
                LIMIT 1
            ");
            $updateRequest->execute([
                $decision,
                $superAdminId > 0 ? $superAdminId : null,
                $reviewNotes !== '' ? $reviewNotes : null,
                $requestId
            ]);

            $demoPdo->commit();

            $success = $decision === 'Approved'
                ? 'The extension was approved and the Demo expiry was extended by 5 days.'
                : 'The extension request was rejected.';

        } catch (Throwable $e) {
            if ($demoPdo->inTransaction()) {
                $demoPdo->rollBack();
            }

            error_log('Demo extension review failed: ' . $e->getMessage());
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The extension request could not be processed.';
        }
    }
}

$requestsStmt = $demoPdo->query("
    SELECT
        er.*,
        t.company_name,
        t.company_domain,
        t.started_at,
        t.expires_at,
        t.status AS tenant_status,
        u.username AS requested_by_username
    FROM demo_extension_requests er
    INNER JOIN demo_tenants t
        ON t.id = er.demo_tenant_id
    LEFT JOIN users u
        ON u.id = er.requested_by_user_id
    ORDER BY
        CASE er.status WHEN 'Pending' THEN 0 ELSE 1 END,
        er.requested_at DESC,
        er.id DESC
");
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1">Demo Extension Requests</h1>
            <p class="text-muted mb-0">
                Review extension requests from Demo company administrators. All requests are stored in the Demo database.
            </p>
        </div>
        <a href="?page=demo-super-admin" class="btn btn-outline-secondary">Demo Companies</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (empty($requests)): ?>
        <div class="alert alert-secondary">No Demo extension requests have been submitted.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($requests as $request): ?>
                <?php
                    $status = (string) ($request['status'] ?? 'Pending');
                    $badgeClass = match ($status) {
                        'Approved' => 'bg-success',
                        'Rejected' => 'bg-danger',
                        default => 'bg-warning text-dark',
                    };
                ?>
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong><?= htmlspecialchars($request['company_name'] ?? 'Demo Company', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($request['company_domain'])): ?>
                                        <span class="text-white-50 ms-2"><?= htmlspecialchars($request['company_domain'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="small text-muted">Requested By</div>
                                    <div class="fw-semibold"><?= htmlspecialchars($request['requested_by_username'] ?? 'Demo Admin', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-md-3">
    <div class="small text-muted">Requested At</div>

    <?php
        try {
            $requestedAtDate = new DateTimeImmutable(
                (string) ($request['requested_at'] ?? ''),
                new DateTimeZone('Asia/Dubai')
            );

            $requestedAtDisplay = $requestedAtDate->format(
                'd/m/Y h:i A'
            );
        } catch (Exception $e) {
            $requestedAtDisplay = $request['requested_at'] ?? '—';
        }
    ?>

    <div>
        <?= htmlspecialchars(
            $requestedAtDisplay,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
</div>

<div class="col-md-3">
    <div class="small text-muted">Started</div>

    <?php
        try {
            $startedDate = new DateTimeImmutable(
                (string) ($request['started_at'] ?? ''),
                new DateTimeZone('Asia/Dubai')
            );

            $startedDisplay = $startedDate->format(
                'd/m/Y h:i A'
            );
        } catch (Exception $e) {
            $startedDisplay = $request['started_at'] ?? '—';
        }
    ?>

    <div>
        <?= htmlspecialchars(
            $startedDisplay,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
</div>

<div class="col-md-3">
    <div class="small text-muted">Current Expiry</div>

    <?php
        try {
            $expiryDate = new DateTimeImmutable(
                (string) ($request['expires_at'] ?? ''),
                new DateTimeZone('Asia/Dubai')
            );

            $expiryDisplay = $expiryDate->format(
                'd/m/Y h:i A'
            );
        } catch (Exception $e) {
            $expiryDisplay = $request['expires_at'] ?? '—';
        }
    ?>

    <div class="fw-semibold">
        <?= htmlspecialchars(
            $expiryDisplay,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
</div>
                            </div>

                            <?php if (!empty($request['review_notes'])): ?>
                                <div class="alert alert-light border mt-4 mb-0">
                                    <strong>Review note:</strong><br>
                                    <?= nl2br(htmlspecialchars($request['review_notes'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($status === 'Pending'): ?>
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Super Admin Note</label>
                                        <textarea name="review_notes" class="form-control" rows="2" maxlength="2000" placeholder="Optional note for the Demo Admin"></textarea>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" name="decision" value="Approved" class="btn btn-success">
                                            <i class="bi bi-check-circle me-1"></i> Approve 5-Day Extension
                                        </button>
                                        <button type="submit" name="decision" value="Rejected" class="btn btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i> Reject Request
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
