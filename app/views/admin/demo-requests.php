<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/email.php';
require_once HELPER_PATH . '/demo-domain.php';

$demoEnvironment = getDemoEnvironment();

$successMessage = $_SESSION['demo_success'] ?? null;
unset($_SESSION['demo_success']);

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');

    if ($requestId <= 0) {

        $errorMessage = 'Invalid Demo request.';

    } else {

        try {

            if ($demoEnvironment !== null) {

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM demo_requests
                    WHERE id = ?
                      AND environment = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $requestId,
                    $demoEnvironment
                ]);

            } else {

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM demo_requests
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $requestId
                ]);
            }

            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new RuntimeException('Demo request not found.');
            }

            if ($action === 'approve') {

                if ($request['status'] !== 'Pending') {
                    throw new RuntimeException(
                        'Only pending Demo requests can be approved.'
                    );
                }

                $verificationToken =
                    bin2hex(random_bytes(32));

                $verificationTokenHash =
                    hash('sha256', $verificationToken);

                $verificationLink =
                    rtrim(APP_URL, '/')
                    . '/?page=demo-verify-email&token='
                    . urlencode($verificationToken);

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE demo_requests
                    SET
                        status = 'Approved',
                        approved_at = NOW(),
                        verification_status = 'Pending',
                        email_verification_token_hash = ?,
                        email_verification_expires_at =
                            DATE_ADD(NOW(), INTERVAL 24 HOUR),
                        email_verified_at = NULL,
                        demo_user_id = NULL,
                        workspace_id = NULL
                    WHERE id = ?
                      AND status = 'Pending'
                ");

                $stmt->execute([
                    $verificationTokenHash,
                    $requestId
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'This Demo request was already processed.'
                    );
                }

                $pdo->commit();

                $safeName = htmlspecialchars(
                    $request['full_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeCompany = htmlspecialchars(
                    $request['company_name'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                );

                $emailBody = "
                    <h2>Hello {$safeName},</h2>

                    <p>
                        Your request for access to our IT Consultancy
                        Demo Portal has been approved.
                    </p>

                    <p>
                        Before Demo access can be created, please verify
                        that you control this business email address.
                    </p>

                    " . (
                        $safeCompany !== ''
                            ? "<p><strong>Company:</strong> {$safeCompany}</p>"
                            : ''
                    ) . "

                    <p>
                        <a
                            href='{$verificationLink}'
                            style='
                                background:#0d6efd;
                                color:#ffffff;
                                padding:12px 22px;
                                text-decoration:none;
                                border-radius:6px;
                                display:inline-block;
                                font-weight:600;
                            '>
                            Verify Demo Email
                        </a>
                    </p>

                    <p>
                        This verification link is valid for 24 hours
                        and can only be used once.
                    </p>

                    <p>
                        No Demo account has been created yet.
                        The three Demo accounts will be created only
                        after successful email verification.
                    </p>

                    <p>
                        If you did not request Demo access, you can safely
                        ignore this email.
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>
                ";

                $emailSent = sendEmail(
                    $request['email'],
                    'Verify Your Demo Email Address',
                    $emailBody
                );

                $_SESSION['demo_success'] =
                    $emailSent
                        ? 'Demo request approved. A verification link has been sent to the applicant.'
                        : 'Demo request approved, but the verification email could not be sent. Please check the mail configuration.';

                header('Location: ?page=demo-requests');
                exit;
            }

            if ($action === 'reject') {

                if ($request['status'] !== 'Pending') {
                    throw new RuntimeException(
                        'Only pending Demo requests can be rejected.'
                    );
                }

                $reason = trim(
                    $_POST['rejection_reason'] ?? ''
                );

                if ($reason === '') {
                    throw new RuntimeException(
                        'Please provide a rejection reason.'
                    );
                }

                $stmt = $pdo->prepare("
                    UPDATE demo_requests
                    SET
                        status = 'Rejected',
                        rejection_reason = ?,
                        rejected_at = NOW(),
                        verification_status = 'Not Started',
                        email_verification_token_hash = NULL,
                        email_verification_expires_at = NULL,
                        email_verified_at = NULL
                    WHERE id = ?
                      AND status = 'Pending'
                ");

                $stmt->execute([
                    $reason,
                    $requestId
                ]);

                $safeName = htmlspecialchars(
                    $request['full_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeReason = nl2br(
                    htmlspecialchars(
                        $reason,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                );

                $emailBody = "
                    <h2>Hello {$safeName},</h2>

                    <p>
                        Thank you for your interest in our IT Consultancy
                        Demo Portal.
                    </p>

                    <p>
                        After review, we are unable to approve your Demo
                        access request at this time.
                    </p>

                    <p><strong>Reason:</strong></p>

                    <div
                        style='
                            border-left:4px solid #dc3545;
                            padding:12px 15px;
                            background:#f8f9fa;
                        '>
                        {$safeReason}
                    </div>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>
                ";

                $emailSent = sendEmail(
                    $request['email'],
                    'Your Demo Request Has Been Declined',
                    $emailBody
                );

                $_SESSION['demo_success'] =
                    $emailSent
                        ? 'Demo request rejected successfully. A rejection notification was also sent to the applicant.'
                        : 'Demo request rejected successfully, but the rejection email could not be sent.';

                header('Location: ?page=demo-requests');
                exit;
            }

            throw new RuntimeException('Invalid Demo action.');

        } catch (RuntimeException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errorMessage = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Demo request administration failed: '
                . $e->getMessage()
            );

            $errorMessage =
                'We could not process this Demo request right now. '
                . 'Please try again later.';
        }
    }
}

if ($demoEnvironment !== null) {

    $stmt = $pdo->prepare("
        SELECT
            dr.*,
            dc.domain AS company_domain_display,
            dc.demo_used,
            du.username AS demo_username,
            du.status AS demo_user_status
        FROM demo_requests dr
        LEFT JOIN demo_companies dc
            ON dc.id = dr.demo_company_id
        LEFT JOIN demo_users du
            ON du.id = dr.demo_user_id
        WHERE dr.environment = ?
        ORDER BY
            CASE
                WHEN dr.status = 'Pending' THEN 1
                WHEN dr.status = 'Approved'
                     AND dr.verification_status = 'Pending' THEN 2
                WHEN dr.status = 'Approved'
                     AND dr.verification_status = 'Verified' THEN 3
                ELSE 4
            END,
            dr.created_at DESC
    ");

    $stmt->execute([
        $demoEnvironment
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            dr.*,
            dc.domain AS company_domain_display,
            dc.demo_used,
            du.username AS demo_username,
            du.status AS demo_user_status
        FROM demo_requests dr
        LEFT JOIN demo_companies dc
            ON dc.id = dr.demo_company_id
        LEFT JOIN demo_users du
            ON du.id = dr.demo_user_id
        ORDER BY
            CASE
                WHEN dr.status = 'Pending' THEN 1
                WHEN dr.status = 'Approved'
                     AND dr.verification_status = 'Pending' THEN 2
                WHEN dr.status = 'Approved'
                     AND dr.verification_status = 'Verified' THEN 3
                ELSE 4
            END,
            dr.created_at DESC
    ");
}

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-admin.php';

?>

<style>
    .demo-requests-wrap {
        width: 100%;
    }

    .demo-requests-table {
        width: 100%;
        table-layout: fixed;
        font-size: 12px;
    }

    .demo-requests-table th,
    .demo-requests-table td {
        vertical-align: middle;
        overflow-wrap: anywhere;
        word-break: break-word;
        padding: 8px 7px;
    }

    .demo-requests-table th:nth-child(1) { width: 3%; }
    .demo-requests-table th:nth-child(2) { width: 10%; }
    .demo-requests-table th:nth-child(3) { width: 13%; }
    .demo-requests-table th:nth-child(4) { width: 8%; }
    .demo-requests-table th:nth-child(5) { width: 10%; }
    .demo-requests-table th:nth-child(6) { width: 13%; }
    .demo-requests-table th:nth-child(7) { width: 7%; }
    .demo-requests-table th:nth-child(8) { width: 9%; }
    .demo-requests-table th:nth-child(9) { width: 8%; }
    .demo-requests-table th:nth-child(10) { width: 8%; }
    .demo-requests-table th:nth-child(11) { width: 11%; }

    .demo-requests-table .badge {
        font-size: 10px;
        white-space: normal;
    }

    .demo-requests-table .btn {
        font-size: 11px;
        padding: 4px 7px;
    }

    .demo-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
</style>

<div class="container-fluid py-4 demo-requests-wrap">

    <div class="mb-4">
        <h2 class="mb-1">Demo Requests</h2>
        <p class="text-muted mb-0">
            Review and manage temporary Demo access requests.
        </p>
    </div>

    <?php if ($successMessage): ?>

        <div class="alert alert-success">
            <strong>Success!</strong>
            <?= htmlspecialchars($successMessage) ?>
        </div>

    <?php endif; ?>

    <?php if ($errorMessage): ?>

        <div class="alert alert-danger">
            <strong>Error:</strong>
            <?= htmlspecialchars($errorMessage) ?>
        </div>

    <?php endif; ?>

    <div class="card shadow-sm">

        <div class="card-header">
            <strong>Demo Access Requests</strong>
        </div>

        <div class="card-body p-0">

            <?php if (!$requests): ?>

                <div class="p-4 text-muted">
                    No Demo requests found.
                </div>

            <?php else: ?>

                <table class="table table-bordered table-striped mb-0 demo-requests-table">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Applicant</th>
                            <th>Contact</th>
                            <th>Company</th>
                            <th>Domain</th>
                            <th>Areas of Interest</th>
                            <th>Status</th>
                            <th>Verification</th>
                            <th>Demo Account</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($requests as $request): ?>

                        <?php
                        $verificationStatus =
                            $request['verification_status']
                            ?? 'Not Started';

                        $options = [];

                        if (!empty($request['explore_options'])) {

                            $decoded = json_decode(
                                $request['explore_options'],
                                true
                            );

                            if (is_array($decoded)) {
                                $options = $decoded;
                            }
                        }
                        ?>

                        <tr>

                            <td>
                                <?= (int) $request['id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $request['full_name']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $request['email']
                                ) ?>

                                <?php if (!empty($request['phone'])): ?>

                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            $request['phone']
                                        ) ?>
                                    </small>

                                <?php endif; ?>
                            </td>

                            <td>
                                <?= !empty($request['company_name'])
                                    ? htmlspecialchars(
                                        $request['company_name']
                                    )
                                    : '—'
                                ?>
                            </td>

                            <td>

                                <?= !empty($request['company_domain_display'])
                                    ? htmlspecialchars(
                                        $request['company_domain_display']
                                    )
                                    : (
                                        !empty($request['company_domain'])
                                            ? htmlspecialchars(
                                                $request['company_domain']
                                            )
                                            : '—'
                                    )
                                ?>

                                <?php if (
                                    isset($request['demo_used'])
                                    && (int) $request['demo_used'] === 1
                                ): ?>

                                    <br>
                                    <span class="badge bg-secondary">
                                        Demo Used
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ($options): ?>

                                    <?php foreach ($options as $option): ?>

                                        <span class="badge bg-info text-dark mb-1">
                                            <?= htmlspecialchars($option) ?>
                                        </span>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

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
                                        <?= htmlspecialchars(
                                            $request['status']
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ($verificationStatus === 'Verified'): ?>

                                    <span class="badge bg-success">
                                        Verified
                                    </span>

                                <?php elseif ($verificationStatus === 'Pending'): ?>

                                    <span class="badge bg-warning text-dark">
                                        Verification Pending
                                    </span>

                                <?php elseif ($verificationStatus === 'Expired'): ?>

                                    <span class="badge bg-secondary">
                                        Verification Expired
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-light text-dark">
                                        Not Started
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (!empty($request['demo_username'])): ?>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $request['demo_username']
                                        ) ?>
                                    </strong>

                                    <br>

                                    <?php if (
                                        $request['demo_user_status'] === 'Active'
                                    ): ?>

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    <?php elseif (
                                        $request['demo_user_status'] === 'Expired'
                                    ): ?>

                                        <span class="badge bg-secondary">
                                            Expired
                                        </span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Not created
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $request['created_at']
                                ) ?>
                            </td>

                            <td>

                                <?php if ($request['status'] === 'Pending'): ?>

                                    <div class="demo-actions">

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Approve this Demo request and send an email verification link?'
                                            );">

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
                                            data-bs-toggle="modal"
                                            data-bs-target="#rejectModal<?= (int) $request['id'] ?>">
                                            Reject
                                        </button>

                                    </div>

                                    <div
                                        class="modal fade"
                                        id="rejectModal<?= (int) $request['id'] ?>"
                                        tabindex="-1">

                                        <div class="modal-dialog">

                                            <div class="modal-content">

                                                <form method="POST">

                                                    <div class="modal-header">

                                                        <h5 class="modal-title">
                                                            Reject Demo Request
                                                        </h5>

                                                        <button
                                                            type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal">
                                                        </button>

                                                    </div>

                                                    <div class="modal-body">

                                                        <p>
                                                            Reject Demo request
                                                            from
                                                            <strong>
                                                                <?= htmlspecialchars(
                                                                    $request['full_name']
                                                                ) ?>
                                                            </strong>?
                                                        </p>

                                                        <input
                                                            type="hidden"
                                                            name="request_id"
                                                            value="<?= (int) $request['id'] ?>">

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="reject">

                                                        <div class="mb-3">

                                                            <label class="form-label">
                                                                Rejection Reason
                                                            </label>

                                                            <textarea
                                                                name="rejection_reason"
                                                                class="form-control"
                                                                rows="4"
                                                                required></textarea>

                                                        </div>

                                                    </div>

                                                    <div class="modal-footer">

                                                        <button
                                                            type="button"
                                                            class="btn btn-secondary"
                                                            data-bs-dismiss="modal">
                                                            Cancel
                                                        </button>

                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger">
                                                            Reject Request
                                                        </button>

                                                    </div>

                                                </form>

                                            </div>

                                        </div>

                                    </div>

                                <?php elseif (
                                    $request['status'] === 'Approved'
                                    && $verificationStatus === 'Pending'
                                ): ?>

                                    <span class="badge bg-warning text-dark">
                                        Awaiting Email Verification
                                    </span>

                                <?php elseif (
                                    $request['status'] === 'Approved'
                                    && $verificationStatus === 'Verified'
                                ): ?>

                                    <span class="badge bg-success">
                                        Ready / Workspace Created
                                    </span>

                                <?php else: ?>

                                    <span class="text-muted">
                                        No action
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
