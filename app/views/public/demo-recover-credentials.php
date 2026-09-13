<?php

/*
|--------------------------------------------------------------------------
| DEMO CREDENTIAL RECOVERY
|--------------------------------------------------------------------------
|
| Public recovery request for a verified Demo workspace.
|
| Recovery is workspace-based:
|   - requester enters business email only
|   - system finds the verified Demo workspace
|   - a one-time recovery link is emailed to the verified address
|   - the recovery link will reset Admin + Customer + Agent together
|
| IMPORTANT:
| This page does NOT reset passwords directly from the public POST.
| The reset will only happen after the emailed one-time recovery link
| is successfully opened.
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/demo-domain.php';
require_once HELPER_PATH . '/email.php';

$success = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));

    /*
    |--------------------------------------------------------------------------
    | Always use a generic response
    |--------------------------------------------------------------------------
    |
    | Do not reveal whether an email address belongs to a verified Demo
    | workspace. This prevents account/workspace enumeration.
    |--------------------------------------------------------------------------
    */

    $genericSuccess =
        'If a verified Demo account is associated with this email, '
        . 'a credential recovery link has been sent to the verified business email address.';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid business email address.';

    } else {

        try {

            $demoEnvironment = getDemoEnvironment();

            /*
            |--------------------------------------------------------------------------
            | Find the verified Demo workspace for this business email
            |--------------------------------------------------------------------------
            */

            if ($demoEnvironment !== null) {

                $stmt = $pdo->prepare("
                    SELECT
                        dw.*,
                        dr.email,
                        dr.full_name,
                        dr.company_name
                    FROM demo_workspaces dw
                    INNER JOIN demo_requests dr
                        ON dr.id = dw.demo_request_id
                    WHERE LOWER(dr.email) = ?
                      AND dw.status IN ('Active', 'Pending')
                      AND dr.status = 'Approved'
                      AND dr.verification_status = 'Verified'
                      AND dr.email_verified_at IS NOT NULL
                      AND dr.environment = ?
                    ORDER BY dw.id DESC
                    LIMIT 1
                ");

                $stmt->execute([
                    $email,
                    $demoEnvironment
                ]);

            } else {

                $stmt = $pdo->prepare("
                    SELECT
                        dw.*,
                        dr.email,
                        dr.full_name,
                        dr.company_name
                    FROM demo_workspaces dw
                    INNER JOIN demo_requests dr
                        ON dr.id = dw.demo_request_id
                    WHERE LOWER(dr.email) = ?
                      AND dw.status IN ('Active', 'Pending')
                      AND dr.status = 'Approved'
                      AND dr.verification_status = 'Verified'
                      AND dr.email_verified_at IS NOT NULL
                    ORDER BY dw.id DESC
                    LIMIT 1
                ");

                $stmt->execute([
                    $email
                ]);
            }

            $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workspace) {

                /*
                |--------------------------------------------------------------------------
                | Generic response for unknown/unverified addresses
                |--------------------------------------------------------------------------
                */

                $success = $genericSuccess;

            } else {

                /*
                |--------------------------------------------------------------------------
                | Do not issue repeated recovery links too quickly
                |--------------------------------------------------------------------------
                */

                if (
                    !empty($workspace['credential_recovery_requested_at'])
                    && strtotime($workspace['credential_recovery_requested_at'])
                        > (time() - 15 * 60)
                ) {

                    $success = $genericSuccess;

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Generate one-time recovery token
                    |--------------------------------------------------------------------------
                    */

                    $token = bin2hex(random_bytes(32));

                    $tokenHash = hash(
                        'sha256',
                        $token
                    );

                    $expiresAt = date(
                        'Y-m-d H:i:s',
                        time() + (60 * 60)
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Store only the token hash
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        UPDATE demo_workspaces
                        SET
                            credential_recovery_token_hash = ?,
                            credential_recovery_expires_at = ?,
                            credential_recovery_requested_at = NOW()
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $tokenHash,
                        $expiresAt,
                        (int) $workspace['id']
                    ]);

                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException(
                            'The Demo recovery request could not be created.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Build recovery link
                    |--------------------------------------------------------------------------
                    */

                    $recoveryLink = APP_URL . '/?page=demo-recover-credentials-confirm&token=' . urlencode($token);

                    $safeName = htmlspecialchars(
                        $workspace['full_name'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $safeCompany = htmlspecialchars(
                        $workspace['company_name'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $emailSubject =
                        'Demo Credential Recovery';

                    $emailBody = "
                        <h2>Hello {$safeName},</h2>

                        <p>
                            We received a request to recover the credentials
                            for your private Demo workspace.
                        </p>

                        " . (
                            $safeCompany !== ''
                                ? "<p><strong>Company:</strong> {$safeCompany}</p>"
                                : ''
                        ) . "

                        <p>
                            To continue, use the secure one-time recovery link below:
                        </p>

                        <p>
                            <a
                                href='{$recoveryLink}'
                                style='
                                    display:inline-block;
                                    padding:10px 16px;
                                    background:#0d6efd;
                                    color:#ffffff;
                                    text-decoration:none;
                                    border-radius:5px;
                                '>
                                Recover Demo Credentials
                            </a>
                        </p>

                        <p>
                            This link expires in <strong>1 hour</strong>
                            and can be used only once.
                        </p>

                        <p>
                            If you did not request Demo credential recovery,
                            you can safely ignore this email.
                        </p>

                        <p>
                            Kind Regards,<br>
                            <strong>IT Consultancy Team</strong>
                        </p>
                    ";

                    $emailSent = sendEmail(
                        $workspace['email'],
                        $emailSubject,
                        $emailBody
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Do not expose email delivery details to the requester
                    |--------------------------------------------------------------------------
                    */

                    if (!$emailSent) {
                        error_log(
                            'Demo credential recovery email failed for workspace #'
                            . (int) $workspace['id']
                        );
                    }

                    $success = $genericSuccess;
                }
            }

        } catch (PDOException $e) {

            error_log(
                'Demo credential recovery failed: '
                . $e->getMessage()
            );

            $error =
                'We could not process the recovery request right now. '
                . 'Please try again later.';

        } catch (RuntimeException $e) {

            error_log(
                'Demo credential recovery failed: '
                . $e->getMessage()
            );

            $error =
                'We could not process the recovery request right now. '
                . 'Please try again later.';
        }
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">
    <div class="col-lg-6 col-md-8">

        <div class="card shadow-sm">

            <div class="card-body">

                <h2 class="mb-2 text-center">
                    🔐 Recover Demo Credentials
                </h2>

                <p class="text-muted text-center mb-4">
                    Enter the business email address used when your Demo
                    workspace was verified.
                </p>

                <?php if ($success !== ''): ?>

                    <div class="alert alert-success">
                        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                <?php endif; ?>

                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                <?php endif; ?>

                <?php if ($success === ''): ?>

                    <form
                        method="POST"
                        action="?page=demo-recover-credentials"
                        autocomplete="off">

                        <div class="mb-3">

                            <label
                                for="email"
                                class="form-label">
                                Business Email
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                maxlength="255"
                                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                                autocomplete="email"
                                required>

                            <div class="form-text">
                                Use the same business email address that was
                                verified for your Demo.
                            </div>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100">
                            Send Recovery Link
                        </button>

                    </form>

                <?php endif; ?>

                <div class="text-center mt-4">

                    <a
                        href="?page=demo-login"
                        class="btn btn-secondary">
                        Back to Demo Login
                    </a>

                </div>

            </div>

        </div>

    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
