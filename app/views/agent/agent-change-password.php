<?php

if (!isset($_SESSION['agent'])) {
    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/email.php';

$agentId = (int) $_SESSION['agent']['id'];

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email
    FROM agents
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$agentId]);

$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {
    session_destroy();

    header('Location: ?page=public-login');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['request_password_change'])) {

    /*
     * Generate a cryptographically secure token.
     */
    $token = bin2hex(random_bytes(32));

    /*
     * Store only the hash of the token in the database.
     */
    $tokenHash = hash('sha256', $token);

    /*
     * Token expires after 1 hour.
     */
    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + (60 * 60)
    );

    $stmt = $pdo->prepare("
        UPDATE agents
        SET
            password_reset_token = ?,
            password_reset_expires_at = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $tokenHash,
        $expiresAt,
        $agentId
    ]);

    /*
     * Send the raw token only by email.
     */
    $emailSent = sendAgentPasswordResetEmail(
        $agent['email'],
        $agent['name'],
        $token
    );

    if ($emailSent) {

        header('Location: ?page=agent-change-password&sent=1');
        exit;

    } else {

        $error = 'Unable to send the password-change email. Please try again.';
    }
}

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-6">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">
                        Change Password
                    </h4>

                </div>

                <div class="card-body">

                    <?php if (isset($_GET['sent']) && $_GET['sent'] === '1'): ?>

                        <div class="alert alert-success">
                            A secure password-change link has been sent to your work email.
                            Please check your inbox.
                        </div>

                    <?php endif; ?>


                    <?php if (!empty($error)): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>

                    <?php endif; ?>

                    <p class="mb-3">
                        To protect your account, your password can only be
                        changed using a secure link sent to your work email.
                    </p>

                    <div class="alert alert-info">

                        A secure password-change link will be sent to your
                        registered work email address.

                    </div>

                    <form method="POST">

                        <button
                            type="submit"
                            name="request_password_change"
                            class="btn btn-primary">

                            🔐 Send Secure Password Link

                        </button>

                        <a
                            href="?page=agent-profile"
                            class="btn btn-secondary">

                            ← Back

                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>