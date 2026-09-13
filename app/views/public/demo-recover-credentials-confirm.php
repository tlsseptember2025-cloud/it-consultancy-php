<?php

/*
|--------------------------------------------------------------------------
| DEMO CREDENTIAL RECOVERY CONFIRMATION
|--------------------------------------------------------------------------
|
| This page is reached through the one-time recovery link sent to the
| verified business email address.
|
| Recovery process:
|   1. Validate one-time token
|   2. Find the Demo workspace
|   3. Find Admin + Customer + Agent accounts
|   4. Generate three new temporary passwords
|   5. Reset all three accounts
|   6. Set must_change_password = 1 for all three
|   7. Consume the recovery token
|   8. Send ONE email containing all three credentials
|
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/demo-domain.php';
require_once HELPER_PATH . '/email.php';

$token = trim($_GET['token'] ?? '');

$success = false;
$message = '';

if ($token === '') {

    $message =
        'This Demo credential recovery link is invalid or incomplete.';

} else {

    try {

        /*
        |--------------------------------------------------------------------------
        | Hash the supplied recovery token
        |--------------------------------------------------------------------------
        */

        $tokenHash = hash('sha256', $token);

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Find the Demo workspace associated with this token
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT
                dw.id AS workspace_id,
                dw.status AS workspace_status,
                dw.credential_recovery_expires_at,

                dr.id AS request_id,
                dr.email,
                dr.full_name,
                dr.company_name,
                dr.status AS request_status,
                dr.verification_status,
                dr.email_verified_at

            FROM demo_workspaces dw

            INNER JOIN demo_requests dr
                ON dr.id = dw.demo_request_id

            WHERE dw.credential_recovery_token_hash = ?
              AND dw.credential_recovery_expires_at > NOW()
              AND dr.status = 'Approved'
              AND dr.verification_status = 'Verified'
              AND dr.email_verified_at IS NOT NULL
        ";

        $params = [$tokenHash];

        /*
        |--------------------------------------------------------------------------
        | Respect Demo environment isolation
        |--------------------------------------------------------------------------
        */

        $demoEnvironment = getDemoEnvironment();

        if ($demoEnvironment !== null) {

            $sql .= " AND dr.environment = ?";
            $params[] = $demoEnvironment;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$workspace) {

            throw new RuntimeException(
                'This Demo credential recovery link is invalid or has expired.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify workspace status
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $workspace['workspace_status'],
                ['Active', 'Pending'],
                true
            )
        ) {

            throw new RuntimeException(
                'This Demo workspace is no longer active.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find all three Demo users
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                role,
                status
            FROM demo_users
            WHERE workspace_id = ?
              AND role IN ('admin', 'customer', 'agent')
              AND status = 'Active'
            ORDER BY FIELD(
                role,
                'admin',
                'customer',
                'agent'
            )
        ");

        $stmt->execute([
            (int) $workspace['workspace_id']
        ]);

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | Organize users by role
        |--------------------------------------------------------------------------
        */

        $usersByRole = [];

        foreach ($users as $user) {

            $usersByRole[$user['role']] = $user;
        }

        /*
        |--------------------------------------------------------------------------
        | Require all three accounts
        |--------------------------------------------------------------------------
        */

        foreach (
            ['admin', 'customer', 'agent']
            as $requiredRole
        ) {

            if (!isset($usersByRole[$requiredRole])) {

                throw new RuntimeException(
                    'The Demo workspace credentials are incomplete.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Generate NEW temporary passwords
        |--------------------------------------------------------------------------
        |
        | We never recover or attempt to retrieve the old plaintext
        | passwords. New temporary passwords are generated instead.
        |
        */

        $passwords = [

            'admin' =>
                bin2hex(random_bytes(8)),

            'customer' =>
                bin2hex(random_bytes(8)),

            'agent' =>
                bin2hex(random_bytes(8))
        ];

        /*
        |--------------------------------------------------------------------------
        | Reset all three Demo accounts
        |--------------------------------------------------------------------------
        */

        $updateUser = $pdo->prepare("
            UPDATE demo_users
            SET
                password_hash = ?,
                must_change_password = 1
            WHERE id = ?
              AND workspace_id = ?
              AND status = 'Active'
        ");

        foreach (
            ['admin', 'customer', 'agent']
            as $role
        ) {

            $updateUser->execute([

                password_hash(
                    $passwords[$role],
                    PASSWORD_DEFAULT
                ),

                (int) $usersByRole[$role]['id'],

                (int) $workspace['workspace_id']
            ]);

            if ($updateUser->rowCount() !== 1) {

                throw new RuntimeException(
                    'One of the Demo credentials could not be reset.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Consume the recovery token
        |--------------------------------------------------------------------------
        |
        | This prevents the same recovery link from being used again.
        |
        */

        $clearToken = $pdo->prepare("
            UPDATE demo_workspaces
            SET
                credential_recovery_token_hash = NULL,
                credential_recovery_expires_at = NULL,
                credential_recovery_requested_at = NULL
            WHERE id = ?
        ");

        $clearToken->execute([
            (int) $workspace['workspace_id']
        ]);

        if ($clearToken->rowCount() !== 1) {

            throw new RuntimeException(
                'The Demo recovery token could not be consumed.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Commit database changes
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        /*
        |--------------------------------------------------------------------------
        | Build Demo login link
        |--------------------------------------------------------------------------
        */

        $loginLink =
            rtrim(APP_URL, '/')
            . '/?page=demo-login';

        /*
        |--------------------------------------------------------------------------
        | Send ONE email containing all three credentials
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | This matches the actual 10-argument helper signature in
        | app/helpers/email.php.
        |
        */

        $emailSent = sendDemoCredentialsEmail(

            $workspace['email'],

            $workspace['full_name'] ?? '',

            $workspace['company_name'] ?? '',

            $usersByRole['admin']['username'],
            $passwords['admin'],

            $usersByRole['customer']['username'],
            $passwords['customer'],

            $usersByRole['agent']['username'],
            $passwords['agent'],

            $loginLink
        );

        /*
        |--------------------------------------------------------------------------
        | Email result
        |--------------------------------------------------------------------------
        */

        if ($emailSent) {

            $success = true;

        } else {

            error_log(
                'Demo credential recovery credentials email failed'
                . ' | WORKSPACE='
                . (int) $workspace['workspace_id']
                . ' | EMAIL='
                . $workspace['email']
            );

            $message =
                'Your Demo credentials were reset, but the credentials email '
                . 'could not be sent. Please request another recovery link.';
        }

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Demo credential recovery database error: '
            . $e->getMessage()
        );

        $message =
            'We could not complete the Demo credential recovery right now. '
            . 'Please try again later.';

    } catch (RuntimeException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Demo credential recovery error: '
            . $e->getMessage()
        );

        $message = $e->getMessage();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Demo credential recovery unexpected error: '
            . $e->getMessage()
        );

        $message =
            'We could not complete the Demo credential recovery right now. '
            . 'Please try again later.';
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">

    <div class="col-lg-7 col-md-9">

        <div class="card shadow-sm">

            <div class="card-body text-center">

                <?php if ($success): ?>

                    <h2 class="mb-3 text-success">
                        ✓ Credentials Reset Successfully
                    </h2>

                    <div class="alert alert-success text-start">

                        <strong>Demo credentials have been reset.</strong>

                        <br><br>

                        A single email containing the new
                        <strong>Admin</strong>,
                        <strong>Customer</strong>,
                        and <strong>Agent</strong>
                        credentials has been sent to the verified
                        business email address.

                    </div>

                    <p class="text-muted">

                        All three temporary passwords must be changed
                        after the corresponding account signs in.

                    </p>

                    <a
                        href="?page=demo-login"
                        class="btn btn-primary">

                        Open Demo Login

                    </a>

                <?php else: ?>

                    <h2 class="mb-3 text-danger">
                        Credential Recovery Failed
                    </h2>

                    <div class="alert alert-danger text-start">

                        <?= htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                    <a
                        href="?page=demo-recover-credentials"
                        class="btn btn-secondary">

                        Request a New Recovery Link

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>