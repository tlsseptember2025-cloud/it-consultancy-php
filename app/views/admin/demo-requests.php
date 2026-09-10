<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;

}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/email.php';


/*
|--------------------------------------------------------------------------
| Demo Environment
|--------------------------------------------------------------------------
|
| DEV and DEMO use the same database.
| Only show requests belonging to the current site.
|
*/

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

if (strpos($host, 'demo.wahbibconsultancy.com') !== false) {

    $demoEnvironment = 'demo';

} elseif (strpos($host, 'dev.wahbibconsultancy.com') !== false) {

    $demoEnvironment = 'dev';

} else {

    /*
    |--------------------------------------------------------------------------
    | LOCAL DEVELOPMENT
    |--------------------------------------------------------------------------
    |
    | Local development uses its own database and does not have the
    | environment column. Therefore no environment filtering is used.
    |
    */

    $demoEnvironment = null;

}


// ============================================================================
// FLASH MESSAGE
// ============================================================================

$successMessage = $_SESSION['demo_success'] ?? null;
unset($_SESSION['demo_success']);

$generatedCredentials = $_SESSION['demo_credentials'] ?? null;
unset($_SESSION['demo_credentials']);

$errorMessage = null;


// ============================================================================
// APPROVE / REJECT REQUEST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if ($requestId <= 0) {

        $errorMessage = 'Invalid demo request.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Load Demo Request
            |--------------------------------------------------------------------------
            */

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

                throw new Exception('Demo request not found.');

            }


            /*
            |--------------------------------------------------------------------------
            | APPROVE
            |--------------------------------------------------------------------------
            */

            if ($action === 'approve') {

                if ($request['status'] !== 'Pending') {

                    throw new Exception(
                        'Only pending demo requests can be approved.'
                    );

                }


/*
                |--------------------------------------------------------------------------
                | Fixed Demo Accounts
                |--------------------------------------------------------------------------
                |
                | These are the three permanent demo accounts.
                |
                | Username: user      | Role: admin
                | Username: customer | Role: customer
                | Username: agent    | Role: agent
                |
                | The password "pass" is hashed before being stored.
                | Plain-text passwords are never stored in the database.
                |--------------------------------------------------------------------------
                */

                $demoAccounts = [
                    [
                        'username' => 'user',
                        'password' => 'pass',
                        'role'     => 'admin'
                    ],
                    [
                        'username' => 'customer',
                        'password' => 'pass',
                        'role'     => 'customer'
                    ],
                    [
                        'username' => 'agent',
                        'password' => 'pass',
                        'role'     => 'agent'
                    ]
                ];

                /*
                |--------------------------------------------------------------------------
                | Create / Validate Fixed Demo Accounts
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();
                $demoUserId = null;

                foreach ($demoAccounts as $account) {

                    $check = $pdo->prepare("
                        SELECT id, role, password_hash, status
                        FROM demo_users
                        WHERE username = ?
                        LIMIT 1
                    ");

                    $check->execute([
                        $account['username']
                    ]);

                    $existingUser = $check->fetch(PDO::FETCH_ASSOC);

                    if (!$existingUser) {

                        $passwordHash = password_hash(
                            $account['password'],
                            PASSWORD_DEFAULT
                        );

                        $stmt = $pdo->prepare("
                            INSERT INTO demo_users (
                                username,
                                role,
                                password_hash,
                                status,
                                approved_at
                            )
                            VALUES (
                                ?,
                                ?,
                                ?,
                                'Active',
                                NOW()
                            )
                        ");

                        $stmt->execute([
                            $account['username'],
                            $account['role'],
                            $passwordHash
                        ]);

                        $accountId = (int) $pdo->lastInsertId();

                    } else {

                        if ($existingUser['role'] !== $account['role']) {
                            throw new Exception(
                                'Fixed demo account configuration is invalid for username "'
                                . $account['username'] . '".'
                            );
                        }

                        if (!password_verify(
                            $account['password'],
                            $existingUser['password_hash']
                        )) {
                            throw new Exception(
                                'Fixed demo account password configuration is invalid for username "'
                                . $account['username'] . '".'
                            );
                        }

                        $accountId = (int) $existingUser['id'];
                    }

                    if ($account['role'] === 'admin') {
                        $demoUserId = $accountId;
                    }
                }

                if (!$demoUserId) {
                    throw new Exception(
                        'Unable to create or locate the fixed demo admin account.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Link Request To Demo User
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE demo_requests
                    SET
                        demo_user_id = ?,
                        status = 'Approved',
                        approved_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $demoUserId,
                    $requestId
                ]);

               $pdo->commit();


/*
                |--------------------------------------------------------------------------
                | Send Approval Email With All Three Fixed Accounts
                |--------------------------------------------------------------------------
                */

                $emailSubject = 'Your Demo Portal Access Has Been Approved';

                $emailBody = "
                    <h2>Hello " . htmlspecialchars($request['full_name']) . ",</h2>

                    <p>
                        Your request for access to our IT Consultancy Demo Portal
                        has been approved.
                    </p>

                    <p>
                        You may use any of the following fixed demo accounts:
                    </p>

                    <table cellpadding='8' cellspacing='0' border='1'
                           style='border-collapse:collapse; width:100%; max-width:600px;'>
                        <thead>
                            <tr>
                                <th align='left'>User Type</th>
                                <th align='left'>Username</th>
                                <th align='left'>Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Admin</td>
                                <td><strong>user</strong></td>
                                <td><strong>pass</strong></td>
                            </tr>
                            <tr>
                                <td>Customer</td>
                                <td><strong>customer</strong></td>
                                <td><strong>pass</strong></td>
                            </tr>
                            <tr>
                                <td>Agent</td>
                                <td><strong>agent</strong></td>
                                <td><strong>pass</strong></td>
                            </tr>
                        </tbody>
                    </table>

                    <br>

                    <p>
                        <strong>Important:</strong> These are fixed demo accounts
                        provided for demonstration purposes.
                    </p>

                    <p>
                        Please use the appropriate user type when signing in.
                    </p>

                    <p>
                        Thank you,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>
                ";

                $emailSent = sendEmail(
                    $request['email'],
                    $emailSubject,
                    $emailBody
                );

                $_SESSION['demo_credentials'] = [
                    'full_name' => $request['full_name'],
                    'accounts' => [
                        [
                            'type' => 'Admin',
                            'username' => 'user',
                            'password' => 'pass'
                        ],
                        [
                            'type' => 'Customer',
                            'username' => 'customer',
                            'password' => 'pass'
                        ],
                        [
                            'type' => 'Agent',
                            'username' => 'agent',
                            'password' => 'pass'
                        ]
                    ]
                ];

                if ($emailSent) {

    $_SESSION['demo_success'] =
        'Demo request approved successfully. '
        . 'The demo credentials were also sent to the applicant by email.';

} else {

    $_SESSION['demo_success'] =
        'Demo request approved successfully, '
        . 'but the email could not be sent. '
        . 'Please provide the displayed credentials to the applicant.';

}


header('Location: ?page=demo-requests');
exit;

            }


            /*
            |--------------------------------------------------------------------------
            | REJECT
            |--------------------------------------------------------------------------
            */

            if ($action === 'reject') {

                if ($request['status'] !== 'Pending') {

                    throw new Exception(
                        'Only pending demo requests can be rejected.'
                    );

                }

                $reason = trim(
                    $_POST['rejection_reason'] ?? ''
                );

                if ($reason === '') {

                    throw new Exception(
                        'Please provide a rejection reason.'
                    );

                }


                $stmt = $pdo->prepare("
    UPDATE demo_requests
    SET
        status = 'Rejected',
        rejection_reason = ?,
        rejected_at = NOW()
    WHERE id = ?
");

$stmt->execute([
    $reason,
    $requestId
]);


/*
|--------------------------------------------------------------------------
| Send Rejection Email
|--------------------------------------------------------------------------
*/

$emailSent = sendDemoRejectedEmail(
    $request['email'],
    $request['full_name'],
    $reason
);


if ($emailSent) {

    $_SESSION['demo_success'] =
        'Demo request rejected successfully. '
        . 'A rejection notification was also sent to the applicant.';

} else {

    $_SESSION['demo_success'] =
        'Demo request rejected successfully, '
        . 'but the rejection email could not be sent.';

}


header('Location: ?page=demo-requests');
exit;

            }


            throw new Exception('Invalid action.');

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            $errorMessage = $e->getMessage();

        }

    }

}


// ============================================================================
// LOAD DEMO REQUESTS
// ============================================================================

if ($demoEnvironment !== null) {

    /*
    |--------------------------------------------------------------------------
    | DEV / DEMO — Shared Database
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            dr.*,
            du.username AS demo_username,
            du.status AS demo_user_status,
            du.first_login_at,
            du.expires_at
        FROM demo_requests dr

        LEFT JOIN demo_users du
            ON du.id = dr.demo_user_id

        WHERE dr.environment = ?

        ORDER BY
            CASE
                WHEN dr.status = 'Pending' THEN 1
                WHEN dr.status = 'Approved' THEN 2
                ELSE 3
            END,
            dr.created_at DESC
    ");

    $stmt->execute([
        $demoEnvironment
    ]);

} else {

    /*
    |--------------------------------------------------------------------------
    | LOCAL — Separate Database
    |--------------------------------------------------------------------------
    |
    | Local does not use environment filtering because its database does
    | not contain the environment column.
    |
    */

    $stmt = $pdo->query("
        SELECT
            dr.*,
            du.username AS demo_username,
            du.status AS demo_user_status,
            du.first_login_at,
            du.expires_at
        FROM demo_requests dr

        LEFT JOIN demo_users du
            ON du.id = dr.demo_user_id

        ORDER BY
            CASE
                WHEN dr.status = 'Pending' THEN 1
                WHEN dr.status = 'Approved' THEN 2
                ELSE 3
            END,
            dr.created_at DESC
    ");

}

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ============================================================================
// ADMIN HEADER
// ============================================================================

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Demo Requests
            </h2>

            <p class="text-muted mb-0">
                Review and manage temporary demo access requests.
            </p>

        </div>

    </div>


    <!-- ================================================================
         SUCCESS MESSAGE
         ================================================================ -->

    <?php if ($successMessage): ?>

        <div class="alert alert-success">

            <strong>Success!</strong>

            <?= htmlspecialchars($successMessage) ?>

        </div>

    <?php endif; ?>


    <!-- ================================================================
         ERROR MESSAGE
         ================================================================ -->

    <?php if ($errorMessage): ?>

        <div class="alert alert-danger">

            <strong>Error:</strong>

            <?= htmlspecialchars($errorMessage) ?>

        </div>

    <?php endif; ?>


    <!-- ================================================================
         FIXED DEMO CREDENTIALS
         ================================================================ -->

    <?php if ($generatedCredentials): ?>

        <div class="alert alert-warning">

            <h5 class="mb-3">
                🔐 Fixed Demo Accounts
            </h5>

            <p>
                Demo access has been approved for
                <strong>
                    <?= htmlspecialchars($generatedCredentials['full_name']) ?>
                </strong>.
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-sm bg-white mb-3">
                    <thead>
                        <tr>
                            <th>User Type</th>
                            <th>Username</th>
                            <th>Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generatedCredentials['accounts'] as $account): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($account['type']) ?></strong>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($account['username']) ?></code>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($account['password']) ?></code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <small>
                These three accounts are fixed demo accounts.
                Passwords are stored in the database only as secure hashes.
            </small>

        </div>

    <?php endif; ?>


    <!-- ================================================================
         REQUEST TABLE
         ================================================================ -->

    <div class="card shadow-sm">

        <div class="card-header">

            <strong>
                Demo Access Requests
            </strong>

        </div>

        <div class="card-body p-0">

            <?php if (!$requests): ?>

                <div class="p-4 text-muted">

                    No demo requests found.

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped mb-0">

                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Applicant
                                </th>

                                <th>
                                    Contact
                                </th>

                                <th>
                                    Company
                                </th>

                                <th>
                                    Areas of Interest
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Demo Account
                                </th>

                                <th>
                                    Created
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <!-- ID -->

                                <td>

                                    <?= (int) $request['id'] ?>

                                </td>


                                <!-- NAME -->

                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $request['full_name']
                                        ) ?>
                                    </strong>

                                </td>


                                <!-- CONTACT -->

                                <td>

                                    <div>

                                        <?= htmlspecialchars(
                                            $request['email']
                                        ) ?>

                                    </div>

                                    <?php if (!empty($request['phone'])): ?>

                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                $request['phone']
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <!-- COMPANY -->

                                <td>

                                    <?= !empty($request['company_name'])
                                        ? htmlspecialchars(
                                            $request['company_name']
                                        )
                                        : '<span class="text-muted">—</span>'
                                    ?>

                                </td>


                                <!-- INTEREST -->

                                <td>

                                    <?php

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

                                    <?php if ($options): ?>

                                        <?php foreach ($options as $option): ?>

                                            <span
                                                class="badge bg-info text-dark mb-1">

                                                <?= htmlspecialchars($option) ?>

                                            </span>

                                        <?php endforeach; ?>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if (
                                        $request['status'] === 'Pending'
                                    ): ?>

                                        <span class="badge bg-warning text-dark">
                                            Pending
                                        </span>

                                    <?php elseif (
                                        $request['status'] === 'Approved'
                                    ): ?>

                                        <span class="badge bg-success">
                                            Approved
                                        </span>

                                    <?php elseif (
                                        $request['status'] === 'Rejected'
                                    ): ?>

                                        <span class="badge bg-danger">
                                            Rejected
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- DEMO USER -->

                                <td>

                                    <?php if ($request['demo_username']): ?>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $request['demo_username']
                                            ) ?>
                                        </strong>

                                        <br>

                                        <?php if (
                                            $request['demo_user_status']
                                            === 'Active'
                                        ): ?>

                                            <span class="badge bg-success">
                                                Active
                                            </span>

                                        <?php elseif (
                                            $request['demo_user_status']
                                            === 'Expired'
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


                                <!-- CREATED -->

                                <td>

                                    <?= htmlspecialchars(
                                        $request['created_at']
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <?php if (
                                        $request['status'] === 'Pending'
                                    ): ?>

                                        <div class="d-flex gap-2">

                                            <!-- APPROVE -->

                                            <form
                                                method="POST"
                                                onsubmit="return confirm(
                                                    'Approve this demo request and enable the three fixed demo accounts?'
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


                                            <!-- REJECT -->

                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rejectModal<?= (int) $request['id'] ?>">

                                                Reject

                                            </button>

                                        </div>


                                        <!-- REJECT MODAL -->

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

                                                                Reject demo request
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

                                                                <label
                                                                    class="form-label">

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

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>