<?php

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

$adminEmail = $_SESSION['user'] ?? '';

if ($adminEmail === '') {
    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Main Admin
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        email
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$adminEmail]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    unset($_SESSION['user']);

    header('Location: ?page=login');
    exit;
}

$adminId = (int) $admin['id'];

$error = '';
$recoveryCredential = '';

/*
|--------------------------------------------------------------------------
| Check Existing Recovery Credential
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        recovery_credential_created_at
    FROM admin_security
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->execute([$adminId]);

$security = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Generate Initial Recovery Credential
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($security) {

        $error = 'A recovery credential already exists for this Admin account.';

    } else {

        /*
         * Generate a strong random 32-character credential.
         */
        $characters =
            'ABCDEFGHJKLMNPQRSTUVWXYZ' .
            'abcdefghijkmnopqrstuvwxyz' .
            '23456789' .
            '!@#$%^&*_-+=';

        $recoveryCredential = '';

        $characterCount = strlen($characters);

        for ($i = 0; $i < 32; $i++) {
            $recoveryCredential .= $characters[random_int(0, $characterCount - 1)];
        }


        /*
         * Store ONLY the hash.
         *
         * The plaintext credential is never stored
         * in the database.
         */
        $recoveryCredentialHash = password_hash(
            $recoveryCredential,
            PASSWORD_DEFAULT
        );


        try {

            $stmt = $pdo->prepare("
                INSERT INTO admin_security (
                    admin_id,
                    recovery_credential_hash,
                    recovery_credential_created_at
                )
                VALUES (?, ?, NOW())
            ");

            $stmt->execute([
                $adminId,
                $recoveryCredentialHash
            ]);

        } catch (PDOException $e) {

            /*
             * Never display database details to the Admin.
             */
            error_log(
                'Main Admin recovery credential creation failed: '
                . $e->getMessage()
            );

            $recoveryCredential = '';

            $error = 'The recovery credential could not be created. Please try again.';
        }
    }
}


require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="row justify-content-center">

    <div class="col-lg-7 col-md-9">

        <div class="card shadow-sm">

            <div class="card-header bg-dark text-white">

                <h4 class="mb-0">
                    Main Admin Recovery Credential
                </h4>

            </div>

            <div class="card-body">

                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>


                <?php if ($recoveryCredential !== ''): ?>

                    <div class="alert alert-warning">

                        <strong>Important:</strong>

                        This recovery credential will only be shown once.
                        Save it somewhere secure before closing this page.

                    </div>


                    <div class="mb-3">

                        <label class="form-label fw-bold">
                            Recovery Credential
                        </label>

                        <div
                            id="recoveryCredential"
                            class="form-control bg-light"
                            style="
                                font-family: monospace;
                                font-size: 1rem;
                                word-break: break-all;
                            "
                        >
                            <?= htmlspecialchars($recoveryCredential) ?>
                        </div>

                    </div>


                    <div class="d-flex gap-2 justify-content-end">

                        <button
                            type="button"
                            class="btn btn-primary"
                            id="saveRecoveryCredential"
                        >
                            Save Credential
                        </button>

                        <a
                            href="?page=dashboard"
                            class="btn btn-secondary"
                            id="closeRecoveryCredential"
                        >
                            Close
                        </a>

                    </div>


                    <script>
                    document
                        .getElementById('saveRecoveryCredential')
                        .addEventListener('click', function () {

                            const credential =
                                document
                                    .getElementById('recoveryCredential')
                                    .textContent
                                    .trim();

                            const blob = new Blob(
                                [credential + "\n"],
                                {
                                    type: 'text/plain;charset=utf-8'
                                }
                            );

                            const url = URL.createObjectURL(blob);

                            const link = document.createElement('a');

                            link.href = url;
                            link.download = 'admin-recovery-credential.txt';

                            document.body.appendChild(link);

                            link.click();

                            document.body.removeChild(link);

                            URL.revokeObjectURL(url);

                            this.textContent = 'Saved';
                            this.classList.remove('btn-primary');
                            this.classList.add('btn-success');
                        });
                    </script>


                <?php elseif ($security): ?>

                    <div class="alert alert-info">

                        A recovery credential has already been created
                        for this Main Admin account.

                        <br><br>

                        If you have lost it, do not create another one
                        from this page. Use the recovery-credential
                        regeneration procedure.

                    </div>


                    <div class="text-end">

                        <a
                            href="?page=dashboard"
                            class="btn btn-secondary"
                        >
                            Close
                        </a>

                    </div>


                <?php else: ?>

                    <p class="text-muted">

                        This credential is an emergency recovery key for
                        the Main Admin account.

                    </p>

                    <div class="alert alert-warning">

                        <strong>Important:</strong>

                        The credential will be generated once and shown
                        only once. The system stores only a secure hash
                        of the credential.

                        <br><br>

                        If you close the page without saving it, it cannot
                        be displayed again.

                    </div>


                    <form method="POST">

                        <div class="d-flex justify-content-between">

                            <a
                                href="?page=dashboard"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Generate Recovery Credential
                            </button>

                        </div>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>