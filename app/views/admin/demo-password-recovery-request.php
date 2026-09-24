<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once CONFIG_PATH . '/demo-database.php';
require_once CONFIG_PATH . '/database.php';

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    header('Location: ?page=demo-password-recovery-requests');
    exit;
}

$stmt = $demoPdo->prepare("
    SELECT
        r.*,
        t.company_name,
        t.company_domain
    FROM demo_password_recovery_requests r
    INNER JOIN demo_tenants t
        ON t.id = r.demo_tenant_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('Location: ?page=demo-password-recovery-requests');
    exit;
}

$reviewedBy = null;

if (!empty($request['reviewed_by_admin_id'])) {
    $reviewerStmt = $pdo->prepare("
        SELECT id, email
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $reviewerStmt->execute([
        (int) $request['reviewed_by_admin_id']
    ]);

    $reviewedBy = $reviewerStmt->fetch(PDO::FETCH_ASSOC);
}

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                Demo Password Recovery Request #<?= (int) $request['id'] ?>
            </h2>

            <p class="text-muted mb-0">
                Review the details of this Demo password recovery request.
            </p>
        </div>

        <a
            href="?page=demo-password-recovery-requests"
            class="btn btn-outline-secondary">
            Back to Requests
        </a>
    </div>


    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <strong>Request Details</strong>
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
                    <strong>Status</strong>
                    <div class="mt-1">
                        <?php if ($request['status'] === 'Pending'): ?>

                            <span class="badge bg-warning text-dark">
                                Pending
                            </span>

                        <?php elseif ($request['status'] === 'Approved'): ?>

                            <span class="badge bg-success">
                                Approved
                            </span>

                        <?php elseif ($request['status'] === 'Rejected'): ?>

                            <span class="badge bg-danger">
                                Rejected
                            </span>

                        <?php else: ?>

                            <span class="badge bg-secondary">
                                <?= htmlspecialchars($request['status']) ?>
                            </span>

                        <?php endif; ?>
                    </div>
                </div>


                <div class="col-md-6">
                    <strong>Account Type</strong>
                    <div>
                        <?= htmlspecialchars(ucfirst($request['account_type'])) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong>Username</strong>
                    <div>
                        <?= htmlspecialchars($request['username'] ?? '-') ?>
                    </div>
                </div>


                <div class="col-md-6">
                    <strong>Email</strong>
                    <div>
                        <?= htmlspecialchars($request['email']) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong>Demo Company</strong>
                    <div>
                        <?= htmlspecialchars($request['company_name']) ?>
                    </div>
                </div>


                <div class="col-md-6">
                    <strong>Company Domain</strong>
                    <div>
                        <?= htmlspecialchars($request['company_domain']) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong>Requested At</strong>
                    <div>
                        <?= htmlspecialchars($request['requested_at']) ?>
                    </div>
                </div>


                <div class="col-12">
                    <strong>Reason</strong>

                    <div class="border rounded p-3 mt-1 bg-light">
                        <?php if (!empty($request['reason'])): ?>
                            <?= nl2br(htmlspecialchars($request['reason'])) ?>
                        <?php else: ?>
                            <span class="text-muted">
                                No reason provided.
                            </span>
                        <?php endif; ?>
                    </div>
                </div>


                <?php if (!empty($request['reviewed_at'])): ?>

                    <div class="col-md-6">
                        <strong>Reviewed At</strong>
                        <div>
                            <?= htmlspecialchars($request['reviewed_at']) ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <strong>Reviewed By</strong>
                        <div>
                            <?php if ($reviewedBy): ?>

                                <?= htmlspecialchars(
                                    $reviewedBy['email']
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    Admin #<?= (int) $request['reviewed_by_admin_id'] ?>
                                </span>

                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>


                <?php if ($request['status'] === 'Rejected'): ?>

                    <div class="col-12">
                        <strong>Rejection Reason</strong>

                        <div class="border border-danger rounded p-3 mt-1 bg-light">
                            <?= nl2br(
                                htmlspecialchars(
                                    $request['rejection_reason'] ?? ''
                                )
                            ) ?>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        </div>
    </div>


    <?php if ($request['status'] === 'Pending'): ?>

        <div class="card shadow-sm">

            <div class="card-header">
                <strong>Admin Action</strong>
            </div>

            <div class="card-body">

                <form
                    method="POST"
                    action="?page=admin-demo-password-recovery"
                    class="d-inline"
                    onsubmit="return confirm('Approve this Demo password recovery request?');">

                    <input
                        type="hidden"
                        name="request_id"
                        value="<?= (int) $request['id'] ?>">

                    <input
                        type="hidden"
                        name="action"
                        value="approve">

                    <button
                        type="submit"
                        class="btn btn-success">
                        Approve Recovery Request
                    </button>

                </form>


                <button
                    type="button"
                    class="btn btn-danger ms-2"
                    onclick="rejectRecoveryRequest(<?= (int) $request['id'] ?>)">
                    Reject Recovery Request
                </button>

            </div>
        </div>

    <?php endif; ?>

</div>


<script>
function rejectRecoveryRequest(requestId) {

    const reason = prompt(
        'Enter the reason for rejecting this recovery request:'
    );

    if (reason === null) {
        return;
    }

    if (reason.trim() === '') {
        alert('A rejection reason is required.');
        return;
    }

    const form = document.createElement('form');

    form.method = 'POST';
    form.action = '?page=admin-demo-password-recovery';

    const requestInput = document.createElement('input');
    requestInput.type = 'hidden';
    requestInput.name = 'request_id';
    requestInput.value = requestId;

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'reject';

    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'rejection_reason';
    reasonInput.value = reason;

    form.appendChild(requestInput);
    form.appendChild(actionInput);
    form.appendChild(reasonInput);

    document.body.appendChild(form);
    form.submit();
}
</script>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>
