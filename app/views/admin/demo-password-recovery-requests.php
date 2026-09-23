<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once CONFIG_PATH . '/demo-database.php';

$stmt = $demoPdo->query("
    SELECT
        id,
        demo_tenant_id,
        account_type,
        account_id,
        username,
        email,
        reason,
        status,
        requested_at
    FROM demo_password_recovery_requests
    ORDER BY
        CASE
            WHEN status = 'Pending' THEN 1
            ELSE 2
        END,
        requested_at DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="mb-4">
        <h2 class="mb-1">
            Demo Password Recovery Requests
        </h2>

        <p class="text-muted mb-0">
            Review password recovery requests submitted by Demo users.
        </p>
    </div>

    <div class="card shadow-sm">

        <div class="card-header">
            <strong>Recovery Requests</strong>
        </div>

        <div class="card-body p-0">

            <?php if (empty($requests)): ?>

                <div class="p-4 text-muted">
                    No Demo password recovery requests found.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped mb-0">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Account Type</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($requests as $request): ?>

                                <tr>

                                    <td>
                                        #<?= (int) $request['id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(ucfirst($request['account_type'])) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['username']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['email']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['reason'] ?? '') ?: '-' ?>
                                    </td>

                                    <td>

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

                                    </td>

                                    <td style="white-space: nowrap;">
                                        <?= htmlspecialchars($request['requested_at']) ?>
                                    </td>

                                    <td style="white-space: nowrap;">

    <?php if ($request['status'] === 'Pending'): ?>

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
                class="btn btn-success btn-sm">
                Approve
            </button>

        </form>

        <button
            type="button"
            class="btn btn-danger btn-sm"
            onclick="rejectRecoveryRequest(<?= (int) $request['id'] ?>)">
            Reject
        </button>

    <?php else: ?>

        -

    <?php endif; ?>

</td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

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

    form.innerHTML = `
        <input type="hidden" name="request_id" value="${requestId}">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="rejection_reason" value="${reason.replace(/"/g, '&quot;')}">
    `;

    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>