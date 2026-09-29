<?php

require_once HELPER_PATH . '/auth.php';

$isDemoAgent = isset($_SESSION['demo_agent']);

if ($isDemoAgent) {

    requireDemoAgent();
    require_once CONFIG_PATH . '/demo-database.php';

    $agentId = (int) ($_SESSION['demo_agent']['id'] ?? 0);
    $demoTenantId = (int) ($_SESSION['demo_agent']['demo_tenant_id'] ?? 0);

    if ($agentId <= 0 || $demoTenantId <= 0) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
        exit;
    }

    $profilePdo = $demoPdo;

    $agentCheck = $profilePdo->prepare("
        SELECT id
        FROM agents
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
          AND status = 'Active'
        LIMIT 1
    ");

    $agentCheck->execute([
        $agentId,
        $demoTenantId
    ]);

    if (!$agentCheck->fetchColumn()) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
        exit;
    }

} else {

    if (!isset($_SESSION['agent'])) {
        header('Location: ?page=public-login');
        exit;
    }

    require_once CONFIG_PATH . '/database.php';

    $agentId = (int) $_SESSION['agent']['id'];
    $profilePdo = $pdo;
}

if ($isDemoAgent) {

    $stmt = $profilePdo->prepare("
        SELECT
            id,
            name,
            email,
            position,
            status
        FROM agents
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $agentId,
        $demoTenantId
    ]);

} else {

    $stmt = $profilePdo->prepare("
        SELECT
            id,
            name,
            email,
            position,
            status
        FROM agents
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $agentId
    ]);
}

$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {

    if ($isDemoAgent) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
    } else {
        unset($_SESSION['agent']);
        header('Location: ?page=public-login');
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {

        $error = 'Name cannot be empty.';

    } else {

        if ($isDemoAgent) {

            $stmt = $profilePdo->prepare("
                UPDATE agents
                SET name = ?
                WHERE id = ?
                  AND demo_tenant_id = ?
                  AND is_demo_account = 1
            ");

            $stmt->execute([
                $name,
                $agentId,
                $demoTenantId
            ]);

            $_SESSION['demo_agent']['name'] = $name;

        } else {

            $stmt = $profilePdo->prepare("
                UPDATE agents
                SET name = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $agentId
            ]);

            $_SESSION['agent']['name'] = $name;
        }

        header('Location: ?page=agent-dashboard&success=profile-updated');
        exit;
    }
}

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">
                        My Profile
                    </h4>

                </div>

                <div class="card-body">

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label fw-bold">
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="<?= htmlspecialchars($agent['name']) ?>"
                            required>

                    </div>


                    <div class="mb-3">

                        <label class="form-label fw-bold">
                            Work Email
                        </label>

                        <input
                            type="email"
                            class="form-control"
                            value="<?= htmlspecialchars($agent['email']) ?>"
                            disabled>

                        <div class="form-text">
                            Your work email is used for your agent login and cannot be changed.
                        </div>

                    </div>


                    <div class="mb-3">

                        <label class="form-label fw-bold">
                            Position
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= htmlspecialchars($agent['position']) ?>"
                            disabled>

                    </div>


                    <div class="mb-4">

                        <label class="form-label fw-bold">
                            Status
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= htmlspecialchars($agent['status']) ?>"
                            disabled>

                    </div>

                    <button
                        type="submit"
                        name="save_profile"
                        class="btn btn-success">

                        💾 Save Changes

                    </button>

                </form>

                    <hr>


                    <div class="d-flex justify-content-between align-items-center">

    <div>

        <h5 class="mb-1">
            Password
        </h5>

        <p class="text-muted mb-0">
            Change your password using a secure link sent to your work email.
        </p>

    </div>

    <a
        href="?page=agent-change-password"
        class="btn btn-primary">

        🔐 Change Password

    </a>

</div>


<div class="mt-4">

    <a
        href="?page=agent-consultations"
        class="btn btn-secondary">

        ← Back

    </a>

</div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>