<?php

require_once CONFIG_PATH . '/demo-database.php';
require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/settings.php';
require_once APP_PATH . '/helpers/email.php';

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

$requestId = isset($_POST['request_id'])
    ? (int) $_POST['request_id']
    : 0;

$action = trim($_POST['action'] ?? '');

if (
    $requestId <= 0 ||
    !in_array($action, ['approve', 'reject'], true)
) {
    header('Location: ?page=demo-password-recovery-requests');
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Recovery Request
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT *
    FROM demo_password_recovery_requests
    WHERE id = ?
      AND status = 'Pending'
    LIMIT 1
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('Location: ?page=demo-password-recovery-requests');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Main Admin ID
|--------------------------------------------------------------------------
*/

$adminStmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
");

$adminStmt->execute([
    $_SESSION['user']
]);

$adminId = (int) $adminStmt->fetchColumn();

if ($adminId <= 0) {
    header('Location: ?page=demo-password-recovery-requests');
    exit;
}

/*
|--------------------------------------------------------------------------
| Reject Request
|--------------------------------------------------------------------------
*/

if ($action === 'reject') {

    $rejectionReason = trim(
        $_POST['rejection_reason'] ?? ''
    );

    if ($rejectionReason === '') {

        $_SESSION['demo_password_recovery_error'] =
            'A rejection reason is required.';

        header(
            'Location: ?page=demo-password-recovery-requests'
        );

        exit;
    }

    try {

        $demoPdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Send Rejection Email
        |--------------------------------------------------------------------------
        */

        $username = htmlspecialchars(
            $request['username'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $safeReason = nl2br(
            htmlspecialchars(
                $rejectionReason,
                ENT_QUOTES,
                'UTF-8'
            )
        );

        $loginLink = APP_URL . '/?page=demo-password-recovery';

        $safeLoginLink = htmlspecialchars(
            $loginLink,
            ENT_QUOTES,
            'UTF-8'
        );

        $subject = 'Demo Password Recovery Request Rejected';

        $body = "
            <h2>Demo Password Recovery Request</h2>

            <p>Hello,</p>

            <p>
                Your Demo password recovery request has been
                <strong>rejected</strong> by the administrator.
            </p>

            <p>
                <strong>Username:</strong> {$username}
            </p>

            <p>
                <strong>Reason:</strong>
            </p>

            <div
                style='
                    border-left:4px solid #dc3545;
                    padding:12px 15px;
                    background:#f8f9fa;
                    margin:10px 0;
                '
            >
                {$safeReason}
            </div>

            <p>
                If you still need access to your Demo account,
                you may submit a new password recovery request.
            </p>

            <p>
                <a
                    href='{$safeLoginLink}'
                    style='
                        display:inline-block;
                        padding:10px 18px;
                        background:#0d6efd;
                        color:#ffffff;
                        text-decoration:none;
                        border-radius:5px;
                    '
                >
                    Submit Another Recovery Request
                </a>
            </p>

            <p>
                Regards,<br>
                <strong>IT Consultancy Team</strong>
            </p>
        ";

        $emailSent = sendEmail(
            $request['email'],
            $subject,
            $body
        );

        if (!$emailSent) {

            throw new RuntimeException(
                'The rejection email could not be sent.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Mark Request Rejected
        |--------------------------------------------------------------------------
        */

        $stmt = $demoPdo->prepare("
            UPDATE demo_password_recovery_requests
            SET
                status = 'Rejected',
                reviewed_at = NOW(),
                reviewed_by_admin_id = ?,
                rejection_reason = ?
            WHERE id = ?
              AND status = 'Pending'
        ");

        $stmt->execute([
            $adminId,
            $rejectionReason,
            $requestId
        ]);

        if ($stmt->rowCount() !== 1) {

            throw new RuntimeException(
                'The recovery request could not be marked as rejected.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $demoPdo->commit();

        $_SESSION['demo_password_recovery_message'] =
            'Request #' . $requestId .
            ' was rejected and the Demo user was notified by email.';

    } catch (Throwable $e) {

        if ($demoPdo->inTransaction()) {
            $demoPdo->rollBack();
        }

        error_log(
            'Demo password recovery rejection failed: ' .
            $e->getMessage()
        );

        $_SESSION['demo_password_recovery_error'] =
            'The recovery request could not be rejected. ' .
            'The Demo user was not notified.';
    }

    header(
        'Location: ?page=demo-password-recovery-requests'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Approve Request
|--------------------------------------------------------------------------
*/

if ($action === 'approve') {

    /*
    |--------------------------------------------------------------------------
    | Generate Temporary Password
    |--------------------------------------------------------------------------
    */

    $characters =
        'ABCDEFGHJKLMNPQRSTUVWXYZ' .
        'abcdefghijkmnopqrstuvwxyz' .
        '23456789';

    $temporaryPassword = '';

    $characterCount = strlen($characters);

    for ($i = 0; $i < 12; $i++) {
        $temporaryPassword .= $characters[
            random_int(0, $characterCount - 1)
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Hash Temporary Password
    |--------------------------------------------------------------------------
    */

    $passwordHash = password_hash(
        $temporaryPassword,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {

        $_SESSION['demo_password_recovery_error'] =
            'Unable to generate the temporary password.';

        header(
            'Location: ?page=demo-password-recovery-requests'
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Update Demo Account
    |--------------------------------------------------------------------------
    */

    try {

        $demoPdo->beginTransaction();

        $accountType = $request['account_type'];
        $accountId = (int) $request['account_id'];
        $tenantId = (int) $request['demo_tenant_id'];

        $accountUpdated = false;

        switch ($accountType) {

            /*
            |--------------------------------------------------------------------------
            | Demo Admin
            |--------------------------------------------------------------------------
            */

            case 'admin':

                $stmt = $demoPdo->prepare("
                    UPDATE users
                    SET
                        password = ?,
                        force_password_change = 1
                    WHERE id = ?
                      AND demo_tenant_id = ?
                      AND is_demo_account = 1
                      AND is_super_admin = 0
                    LIMIT 1
                ");

                $stmt->execute([
                    $passwordHash,
                    $accountId,
                    $tenantId
                ]);

                $accountUpdated = ($stmt->rowCount() === 1);

                break;

            /*
            |--------------------------------------------------------------------------
            | Demo Customer
            |--------------------------------------------------------------------------
            */

            case 'customer':

                $stmt = $demoPdo->prepare("
                    UPDATE customers
                    SET
                        password = ?,
                        force_password_change = 1
                    WHERE id = ?
                      AND demo_tenant_id = ?
                      AND is_demo_account = 1
                    LIMIT 1
                ");

                $stmt->execute([
                    $passwordHash,
                    $accountId,
                    $tenantId
                ]);

                $accountUpdated = ($stmt->rowCount() === 1);

                break;

            /*
            |--------------------------------------------------------------------------
            | Demo Agent
            |--------------------------------------------------------------------------
            */

            case 'agent':

                $stmt = $demoPdo->prepare("
                    UPDATE agents
                    SET
                        password = ?,
                        force_password_change = 1
                    WHERE id = ?
                      AND demo_tenant_id = ?
                      AND is_demo_account = 1
                      AND status = 'Active'
                    LIMIT 1
                ");

                $stmt->execute([
                    $passwordHash,
                    $accountId,
                    $tenantId
                ]);

                $accountUpdated = ($stmt->rowCount() === 1);

                break;

            default:

                throw new RuntimeException(
                    'Invalid Demo account type.'
                );
        }

        if (!$accountUpdated) {
            throw new RuntimeException(
                'Demo account could not be updated.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Send Temporary Password
        |--------------------------------------------------------------------------
        */

        $loginLink = APP_URL . '/?page=demo-login';

        $accountLabel = ucfirst($accountType);

        $username = htmlspecialchars(
            $request['username'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $email = htmlspecialchars(
            $request['email'],
            ENT_QUOTES,
            'UTF-8'
        );

        $safeTemporaryPassword = htmlspecialchars(
            $temporaryPassword,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeLoginLink = htmlspecialchars(
            $loginLink,
            ENT_QUOTES,
            'UTF-8'
        );

        $subject = 'Your Demo Temporary Password';

        $body = "
            <h2>Demo Password Recovery</h2>

            <p>Hello,</p>

            <p>
                Your Demo <strong>{$accountLabel}</strong> password
                recovery request has been approved.
            </p>

            <p>
                <strong>Username:</strong> {$username}<br>
                <strong>Email:</strong> {$email}
            </p>

            <p>
                Your temporary password is:
            </p>

            <p>
                <strong
                    style='
                        display:inline-block;
                        padding:10px 15px;
                        background:#f1f1f1;
                        border:1px solid #ddd;
                        border-radius:5px;
                        font-size:18px;
                    '
                >
                    {$safeTemporaryPassword}
                </strong>
            </p>

            <p>
                <strong>Important:</strong>
                You will be required to change this password
                immediately after logging in.
            </p>

            <p>
                <a
                    href='{$safeLoginLink}'
                    style='
                        display:inline-block;
                        padding:10px 18px;
                        background:#0d6efd;
                        color:#ffffff;
                        text-decoration:none;
                        border-radius:5px;
                    '
                >
                    Open Demo Login
                </a>
            </p>

            <p>
                Regards,<br>
                <strong>IT Consultancy Team</strong>
            </p>
        ";

        $emailSent = sendEmail(
            $request['email'],
            $subject,
            $body
        );

        if (!$emailSent) {

            throw new RuntimeException(
                'The temporary password email could not be sent.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Mark Recovery Request Approved
        |--------------------------------------------------------------------------
        */

        $stmt = $demoPdo->prepare("
            UPDATE demo_password_recovery_requests
            SET
                status = 'Approved',
                reviewed_at = NOW(),
                reviewed_by_admin_id = ?
            WHERE id = ?
              AND status = 'Pending'
        ");

        $stmt->execute([
            $adminId,
            $requestId
        ]);

        if ($stmt->rowCount() !== 1) {

            throw new RuntimeException(
                'The recovery request could not be marked as approved.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $demoPdo->commit();

        $_SESSION['demo_password_recovery_message'] =
            'Request #' . $requestId .
            ' was approved and the temporary password was emailed to the Demo user.';

    } catch (Throwable $e) {

        if ($demoPdo->inTransaction()) {
            $demoPdo->rollBack();
        }

        error_log(
            'Demo password recovery approval failed: ' .
            $e->getMessage()
        );

        $_SESSION['demo_password_recovery_error'] =
            'The recovery request could not be approved. ' .
            'The Demo account was not changed.';
    }

    header(
        'Location: ?page=demo-password-recovery-requests'
    );

    exit;
}