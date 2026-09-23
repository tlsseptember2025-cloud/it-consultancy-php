<?php

require_once CONFIG_PATH . '/demo-database.php';
require_once CONFIG_PATH . '/database.php';

$error = '';
$success = '';

$accountType = trim($_POST['account_type'] ?? '');
$login       = trim($_POST['login'] ?? '');
$reason      = trim($_POST['reason'] ?? '');

$allowedTypes = [
    'admin'    => 'Demo Admin',
    'customer' => 'Demo Customer',
    'agent'    => 'Demo Agent',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($allowedTypes[$accountType])) {
        $error = 'Please select your Demo account type.';
    } elseif ($login === '') {
        $error = 'Please enter your Demo username or email.';
    } elseif (mb_strlen($login) > 255) {
        $error = 'The username or email is too long.';
    } elseif (mb_strlen($reason) > 500) {
        $error = 'The reason must be 500 characters or fewer.';
    }

    if ($error === '') {

        try {
            $account = null;

            if ($accountType === 'admin') {
                $stmt = $demoPdo->prepare("
                    SELECT
                        u.id,
                        u.username,
                        u.email,
                        u.demo_tenant_id,
                        t.company_name,
                        t.status AS tenant_status,
                        t.expires_at
                    FROM users u
                    INNER JOIN demo_tenants t
                        ON t.id = u.demo_tenant_id
                    WHERE u.is_demo_account = 1
                      AND u.is_super_admin = 0
                      AND u.demo_tenant_id IS NOT NULL
                      AND (u.username = ? OR u.email = ?)
                    LIMIT 1
                ");
            } elseif ($accountType === 'customer') {
                $stmt = $demoPdo->prepare("
                    SELECT
                        c.id,
                        c.username,
                        c.email,
                        c.demo_tenant_id,
                        t.company_name,
                        t.status AS tenant_status,
                        t.expires_at
                    FROM customers c
                    INNER JOIN demo_tenants t
                        ON t.id = c.demo_tenant_id
                    WHERE c.is_demo_account = 1
                      AND c.demo_tenant_id IS NOT NULL
                      AND (c.username = ? OR c.email = ?)
                    LIMIT 1
                ");
            } else {
                $stmt = $demoPdo->prepare("
                    SELECT
                        a.id,
                        a.username,
                        a.email,
                        a.demo_tenant_id,
                        t.company_name,
                        t.status AS tenant_status,
                        t.expires_at
                    FROM agents a
                    INNER JOIN demo_tenants t
                        ON t.id = a.demo_tenant_id
                    WHERE a.is_demo_account = 1
                      AND a.demo_tenant_id IS NOT NULL
                      AND (a.username = ? OR a.email = ?)
                    LIMIT 1
                ");
            }

            $stmt->execute([$login, $login]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
             * Do not reveal whether a Demo account exists.
             * A generic success message is returned for valid-looking
             * recovery submissions.
             */
            if (!$account) {
                $success = 'If the Demo account details are valid, your password recovery request has been submitted for Admin review.';
            } elseif (
                $account['tenant_status'] !== 'Active' ||
                (!empty($account['expires_at']) && strtotime($account['expires_at']) <= time())
            ) {
                $success = 'If the Demo account details are valid, your password recovery request has been submitted for Admin review.';
            } else {

                $tenantId = (int) $account['demo_tenant_id'];
                $accountId = (int) $account['id'];

                /* Prevent duplicate pending requests for the same Demo account. */
                $pendingStmt = $demoPdo->prepare("
                    SELECT id
                    FROM demo_password_recovery_requests
                    WHERE demo_tenant_id = ?
                      AND account_type = ?
                      AND account_id = ?
                      AND status = 'Pending'
                    LIMIT 1
                ");
                $pendingStmt->execute([
                    $tenantId,
                    $accountType,
                    $accountId
                ]);

                $pendingRequest = $pendingStmt->fetchColumn();

                if ($pendingRequest) {
                    $success = 'A password recovery request for this Demo account is already waiting for Admin review.';
                } else {

                    $insertStmt = $demoPdo->prepare("
                        INSERT INTO demo_password_recovery_requests
                        (
                            demo_tenant_id,
                            account_type,
                            account_id,
                            username,
                            email,
                            reason,
                            status
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, 'Pending')
                    ");

                    $insertStmt->execute([
                        $tenantId,
                        $accountType,
                        $accountId,
                        $account['username'],
                        $account['email'],
                        $reason !== '' ? $reason : null
                    ]);

                    $requestId = (int) $demoPdo->lastInsertId();

                    /*
                     * Notify the Main Admin through the existing
                     * notifications system. Admin notifications are global,
                     * so recipient_id remains NULL, matching the current
                     * notification design.
                     */
                    $notificationStmt = $pdo->prepare("
                        INSERT INTO notifications
                        (
                            recipient_type,
                            recipient_id,
                            title,
                            message,
                            link
                        )
                        VALUES
                        ('admin', NULL, ?, ?, NULL)
                    ");

                    $notificationStmt->execute([
                        'Demo Password Recovery Request',
                        $allowedTypes[$accountType]
                            . ' (' . $account['username'] . ') has requested a Demo password recovery. Request #' . $requestId . '.'
                    ]);

                    $success = 'Your password recovery request has been submitted. The Main Admin will review it and, if approved, send a temporary password to your registered Demo email address.';

                    $accountType = '';
                    $login = '';
                    $reason = '';
                }
            }

        } catch (PDOException $e) {
            error_log('Demo password recovery request failed: ' . $e->getMessage());
            $error = 'We could not submit your recovery request right now. Please try again later.';
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
                    🔑 Demo Password Recovery
                </h2>

                <p class="text-muted text-center mb-4">
                    Submit a request to the Main Admin if you cannot access your Demo account.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success === ''): ?>
                    <div class="alert alert-info">
                        <strong>How it works</strong>
                        <ol class="mb-0 mt-2">
                            <li>Submit your Demo account details.</li>
                            <li>The Main Admin reviews the request.</li>
                            <li>If approved, a temporary password is generated.</li>
                            <li>The temporary password is sent to your registered Demo email.</li>
                            <li>You must change the temporary password after logging in.</li>
                        </ol>
                    </div>

                    <form method="POST" action="?page=demo-password-recovery" autocomplete="off">

                        <div class="mb-3">
                            <label for="account_type" class="form-label">
                                Demo Account Type
                            </label>

                            <select
                                class="form-select"
                                id="account_type"
                                name="account_type"
                                required>

                                <option value="">Select Account Type</option>
                                <?php foreach ($allowedTypes as $value => $label): ?>
                                    <option
                                        value="<?= htmlspecialchars($value) ?>"
                                        <?= $accountType === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="login" class="form-label">
                                Demo Username or Email
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="login"
                                name="login"
                                value="<?= htmlspecialchars($login) ?>"
                                maxlength="255"
                                autocomplete="username"
                                required>
                        </div>

                        <div class="mb-4">
                            <label for="reason" class="form-label">
                                Reason <span class="text-muted">(optional)</span>
                            </label>

                            <textarea
                                class="form-control"
                                id="reason"
                                name="reason"
                                rows="3"
                                maxlength="500"
                                placeholder="For example: I cannot remember my Demo password."><?= htmlspecialchars($reason) ?></textarea>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100">
                            Submit Recovery Request
                        </button>

                    </form>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="?page=demo-login">
                        Return to Demo Login
                    </a>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
