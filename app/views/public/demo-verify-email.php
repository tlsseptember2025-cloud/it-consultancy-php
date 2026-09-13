<?php

/*
|--------------------------------------------------------------------------
| Demo Email Verification + Workspace Provisioning
|--------------------------------------------------------------------------
|
| Approved request
|   -> verify business email
|   -> create isolated workspace
|   -> create Admin / Customer / Agent demo accounts
|   -> create temporary Customer / Agent records used by the portals
|   -> send credentials
|
*/

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/demo-domain.php';
require_once HELPER_PATH . '/email.php';

$success = '';
$error = '';

$token = trim($_GET['token'] ?? '');

if ($token === '') {

    $error = 'This Demo verification link is invalid or incomplete.';

} else {

    try {

        $tokenHash = hash('sha256', $token);
        $demoEnvironment = getDemoEnvironment();

        /*
        |--------------------------------------------------------------------------
        | Load request
        |--------------------------------------------------------------------------
        */

        if ($demoEnvironment !== null) {

            $stmt = $pdo->prepare("
                SELECT *
                FROM demo_requests
                WHERE email_verification_token_hash = ?
                  AND environment = ?
                LIMIT 1
            ");

            $stmt->execute([
                $tokenHash,
                $demoEnvironment
            ]);

        } else {

            $stmt = $pdo->prepare("
                SELECT *
                FROM demo_requests
                WHERE email_verification_token_hash = ?
                LIMIT 1
            ");

            $stmt->execute([
                $tokenHash
            ]);
        }

        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {

            $error =
                'This Demo verification link is invalid or has already been used.';

        } elseif ($request['status'] !== 'Approved') {

            $error =
                'This Demo request is not currently approved for verification.';

        } elseif (
            ($request['verification_status'] ?? '') === 'Verified'
            || !empty($request['email_verified_at'])
        ) {

            /*
            | A previous successful verification may already have provisioned
            | the workspace. Do not create another one.
            */
            if (!empty($request['workspace_id'])) {

                $success =
                    'Your Demo email has already been verified and your Demo workspace has been created. '
                    . 'Please use the Demo credentials that were sent to you.';

            } else {

                $error =
                    'Your Demo email has already been verified, but the workspace has not yet been provisioned. '
                    . 'Please contact us so we can complete your Demo setup.';
            }

        } elseif (
            empty($request['email_verification_expires_at'])
            || strtotime($request['email_verification_expires_at']) <= time()
        ) {

            $stmt = $pdo->prepare("
                UPDATE demo_requests
                SET verification_status = 'Expired'
                WHERE id = ?
                  AND verification_status = 'Pending'
            ");

            $stmt->execute([
                (int) $request['id']
            ]);

            $error =
                'This Demo verification link has expired. Please contact us for a new verification link.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Provision workspace and accounts atomically
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Lock the company record
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM demo_companies
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                (int) $request['demo_company_id']
            ]);

            $company = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                throw new RuntimeException(
                    'The Demo company record could not be found.'
                );
            }

            if ((int) $company['demo_used'] === 1) {
                throw new RuntimeException(
                    'This company has already used its Demo access.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate workspace provisioning
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM demo_workspaces
                WHERE demo_request_id = ?
                  AND status IN ('Pending', 'Active')
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                (int) $request['id']
            ]);

            $existingWorkspaceId = $stmt->fetchColumn();

            if ($existingWorkspaceId) {
                throw new RuntimeException(
                    'This Demo workspace has already been provisioned.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create workspace
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO demo_workspaces (
                    demo_company_id,
                    demo_request_id,
                    status,
                    verified_at
                )
                VALUES (?, ?, 'Pending', NOW())
            ");

            $stmt->execute([
                (int) $company['id'],
                (int) $request['id']
            ]);

            $workspaceId = (int) $pdo->lastInsertId();

            /*
|--------------------------------------------------------------------------
| Generate Demo Credentials
|--------------------------------------------------------------------------
|
| Usernames are based on the verified business email domain.
|
| Example:
|   saif@loopsautomation.com
|
| becomes:
|   loopsautomation-admin
|   loopsautomation-customer
|   loopsautomation-agent
|
| Company name is intentionally NOT used because it is optional.
|--------------------------------------------------------------------------
*/

$passwords = [
    'admin'    => bin2hex(random_bytes(8)),
    'customer' => bin2hex(random_bytes(8)),
    'agent'    => bin2hex(random_bytes(8))
];

/*
|--------------------------------------------------------------------------
| Build Username Prefix From Verified Email Domain
|--------------------------------------------------------------------------
*/

$verifiedDomain = strtolower(
    trim($request['company_domain'] ?? '')
);

if ($verifiedDomain === '') {

    $verifiedDomain =
        strtolower(
            trim(
                getDemoEmailDomain(
                    $request['email']
                ) ?? ''
            )
        );
}

$usernamePrefix = preg_replace(
    '/[^a-z0-9]+/',
    '',
    explode('.', $verifiedDomain)[0] ?? ''
);

if ($usernamePrefix === '') {

    throw new RuntimeException(
        'Unable to create Demo usernames from the verified business email domain.'
    );
}

/*
|--------------------------------------------------------------------------
| Make Usernames Unique
|--------------------------------------------------------------------------
*/

$baseUsernames = [
    'admin'    => $usernamePrefix . '-admin',
    'customer' => $usernamePrefix . '-customer',
    'agent'    => $usernamePrefix . '-agent'
];

$usernames = $baseUsernames;

$suffix = 1;

while (true) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM demo_users
        WHERE username IN (?, ?, ?)
    ");

    $stmt->execute([
        $usernames['admin'],
        $usernames['customer'],
        $usernames['agent']
    ]);

    $existingCount = (int) $stmt->fetchColumn();

    if ($existingCount === 0) {
        break;
    }

    $suffix++;

    $usernames = [
        'admin'    => $usernamePrefix . '-' . $suffix . '-admin',
        'customer' => $usernamePrefix . '-' . $suffix . '-customer',
        'agent'    => $usernamePrefix . '-' . $suffix . '-agent'
    ];

    /*
    | Safety limit against an unexpected database condition.
    */
    if ($suffix > 9999) {

        throw new RuntimeException(
            'Unable to generate unique Demo usernames.'
        );
    }
}

            

            /*
            |--------------------------------------------------------------------------
            | Create temporary Customer record
            |--------------------------------------------------------------------------
            */

            $customerPasswordHash =
                password_hash(
                    $passwords['customer'],
                    PASSWORD_DEFAULT
                );

            $stmt = $pdo->prepare("
                INSERT INTO customers (
                    name,
                    email,
                    phone,
                    company,
                    notes,
                    password
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $request['full_name'],
                $request['email'],
                $request['phone'] ?? null,
                $request['company_name'] ?? null,
                'Temporary Demo Customer - Workspace #' . $workspaceId,
                $customerPasswordHash
            ]);

            $customerId = (int) $pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create temporary Agent record
            |--------------------------------------------------------------------------
            */

            $agentPasswordHash =
                password_hash(
                    $passwords['agent'],
                    PASSWORD_DEFAULT
                );

            $agentName =
                trim($request['full_name']) . ' - Demo Agent';

            $stmt = $pdo->prepare("
                INSERT INTO agents (
                    name,
                    email,
                    password,
                    phone,
                    position,
                    status,
                    active
                )
                VALUES (?, ?, ?, ?, ?, 'Active', 1)
            ");

            $stmt->execute([
                $agentName,
                $request['email'],
                $agentPasswordHash,
                $request['phone'] ?? null,
                'Demo Consultant'
            ]);

            $agentId = (int) $pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create the three Demo authentication accounts
            |--------------------------------------------------------------------------
            */

            $accounts = [
                [
                    'username' => $usernames['admin'],
                    'password' => $passwords['admin'],
                    'role'     => 'admin'
                ],
                [
                    'username' => $usernames['customer'],
                    'password' => $passwords['customer'],
                    'role'     => 'customer'
                ],
                [
                    'username' => $usernames['agent'],
                    'password' => $passwords['agent'],
                    'role'     => 'agent'
                ]
            ];

            $demoUserIds = [];

            foreach ($accounts as $account) {

                $passwordHash =
                    password_hash(
                        $account['password'],
                        PASSWORD_DEFAULT
                    );

                $stmt = $pdo->prepare("
                    INSERT INTO demo_users (
    username,
    role,
    password_hash,
    status,
    approved_at,
    workspace_id
)
VALUES (?, ?, ?, 'Active', NOW(), ?)
                ");

                $stmt->execute([
                    $account['username'],
                    $account['role'],
                    $passwordHash,
                    $workspaceId
                ]);

                $demoUserIds[$account['role']] =
                    (int) $pdo->lastInsertId();
            }

            /*
            |--------------------------------------------------------------------------
            | Store workspace ownership
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE demo_workspaces
                SET
                    customer_id = ?,
                    agent_id = ?,
                    status = 'Active',
                    verified_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $customerId,
                $agentId,
                $workspaceId
            ]);

            /*
            |--------------------------------------------------------------------------
            | Mark company as having used its Demo
            |--------------------------------------------------------------------------
            |
            | The permanent company/domain history remains even after the
            | temporary workspace is later deleted.
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE demo_companies
                SET demo_used = 1
                WHERE id = ?
            ");

            $stmt->execute([
                (int) $company['id']
            ]);

            /*
            |--------------------------------------------------------------------------
            | Complete request
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE demo_requests
                SET
                    verification_status = 'Verified',
                    email_verified_at = NOW(),
                    email_verification_token_hash = NULL,
                    email_verification_expires_at = NULL,
                    workspace_id = ?,
                    demo_user_id = ?
                WHERE id = ?
                  AND status = 'Approved'
                  AND verification_status = 'Pending'
            ");

            $stmt->execute([
                $workspaceId,
                $demoUserIds['admin'],
                (int) $request['id']
            ]);

            if ($stmt->rowCount() !== 1) {

                throw new RuntimeException(
                    'The Demo request could not be finalized.'
                );
            }

            $pdo->commit();


            /*
|--------------------------------------------------------------------------
| Send Demo Credentials Email
|--------------------------------------------------------------------------
*/

$loginLink =
    rtrim(APP_URL, '/')
    . '/?page=demo-login';

$emailSent = sendDemoCredentialsEmail(
    $request['email'],
    $request['full_name'],
    $request['company_name'] ?? '',
    $usernames['admin'],
    $passwords['admin'],
    $usernames['customer'],
    $passwords['customer'],
    $usernames['agent'],
    $passwords['agent'],
    $loginLink
);

if ($emailSent) {

    $success =
        'Email verified successfully. '
        . 'Your private Demo workspace and all three Demo accounts '
        . 'have been created. Your credentials have been sent by email.';

} else {

    $success =
        'Email verified successfully. '
        . 'Your private Demo workspace and all three Demo accounts '
        . 'have been created, but the credentials email could not be sent. '
        . 'Please contact us so the credentials can be reissued.';
}
        }

    } catch (RuntimeException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Demo email verification / provisioning failed: '
            . $e->getMessage()
        );

        $error =
            'We could not complete your Demo setup right now. '
            . 'Please try again later.';
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">

    <div class="col-lg-7 col-md-9">

        <div class="card shadow-sm">

            <div class="card-body text-center">

                <h2 class="mb-3 text-primary">
                    🖥 Demo Email Verification
                </h2>

                <?php if ($success !== ''): ?>

                    <div class="alert alert-success text-start">

                        <strong>Demo Setup Complete</strong>

                        <br><br>

                        <?= htmlspecialchars($success) ?>

                    </div>

                    <p class="text-muted">
                        Check your email for the three Demo credentials.
                    </p>

                    <a
                        href="?page=demo-login"
                        class="btn btn-primary">
                        Open Demo Login
                    </a>

                <?php elseif ($error !== ''): ?>

                    <div class="alert alert-danger text-start">

                        <strong>Verification Failed</strong>

                        <br><br>

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>

                <div class="mt-4">

                    <a
                        href="?page=home"
                        class="btn btn-secondary">
                        Back to Main Website
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
