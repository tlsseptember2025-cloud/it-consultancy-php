<?php
// CSRF protection for all state-changing POST requests.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}


require_once HELPER_PATH . '/auth.php';
requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $agentPdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);
    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
} else {
    require_once CONFIG_PATH . '/database.php';
    $agentPdo = $pdo;
    $demoTenantId = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['password'] !== $_POST['confirm_password']) {

        $error = "Passwords do not match.";

    } else {

        if ($isDemoAdmin) {
            $stmt = $agentPdo->prepare("
                SELECT id
                FROM agents
                WHERE email = ?
                  AND demo_tenant_id = ?
                  AND is_demo_account = 1
                LIMIT 1
            ");
            $stmt->execute([
                trim($_POST['email'] ?? ''),
                $demoTenantId
            ]);
        } else {
            $stmt = $agentPdo->prepare("
                SELECT id
                FROM agents
                WHERE email = ?
                  AND (is_demo_account = 0 OR is_demo_account IS NULL)
                LIMIT 1
            ");
            $stmt->execute([trim($_POST['email'] ?? '')]);
        }
        if ($stmt->fetch()) {

            $error = "An agent with this email already exists.";

        } else {

            $stmt = $agentPdo->prepare("
                INSERT INTO agents
                (
                    name,
                    email,
                    password,
                    phone,
                    position,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                trim($_POST['name']),
                trim($_POST['email']),
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                trim($_POST['phone']),
                trim($_POST['position']),
                $_POST['status']
            ]);

            header("Location: ?page=agents");
            exit;

        }
    }
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Add Agent
                </h2>

                <?php if (!empty($error)): ?>

<div class="alert alert-danger">

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

                <form method="POST" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">

                        <label class="form-label">
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            autocomplete="off">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Password

                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            autocomplete="new-password"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Confirm Password

                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            class="form-control"
                            autocomplete="new-password"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Position

                        </label>

                        <input
                            type="text"
                            name="position"
                            class="form-control">

                    </div>

                    <div class="mb-4">

                        <label class="form-label">

                            Status

                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <option value="Active">

                                Active

                            </option>

                            <option value="Inactive">

                                Inactive

                            </option>

                        </select>

                    </div>

                    <button
                        class="btn btn-primary">

                        Save Agent

                    </button>

                    <a
                        href="?page=agents"
                        class="btn btn-secondary ms-2">

                        Cancel

                    </a>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>