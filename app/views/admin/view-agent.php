<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();


/*
|--------------------------------------------------------------------------
| Select Database
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['demo_user'])) {

    require_once CONFIG_PATH . '/demo-database.php';

    $agentPdo = $demoPdo;

    $demoTenantId = (int) $_SESSION['demo_user']['demo_tenant_id'];

} else {

    require CONFIG_PATH . '/database.php';

    $agentPdo = $pdo;

    $demoTenantId = null;
}


/*
|--------------------------------------------------------------------------
| Agent ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {

    die('Invalid agent ID');

}


/*
|--------------------------------------------------------------------------
| Load Agent
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['demo_user'])) {

    /*
     * Demo Admin can only view an Agent
     * belonging to the current Demo tenant.
     */

    $stmt = $agentPdo->prepare("
        SELECT *
        FROM agents
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $demoTenantId
    ]);

} else {

    /*
     * Main System Admin can view any Agent.
     */

    $stmt = $agentPdo->prepare("
        SELECT *
        FROM agents
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

}


$agent = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$agent) {

    die('Agent not found.');

}

?>


<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>


<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">

                    Agent Details

                </h2>


                <div class="row mb-3">

                    <div class="col-md-4">

                        <strong>Name</strong>

                    </div>

                    <div class="col-md-8">

                        <?= htmlspecialchars(
                            $agent['name'] ?? ''
                        ) ?>

                    </div>

                </div>


                <?php if (isset($agent['username'])): ?>

                    <div class="row mb-3">

                        <div class="col-md-4">

                            <strong>Username</strong>

                        </div>

                        <div class="col-md-8">

                            <?= htmlspecialchars(
                                $agent['username'] ?? ''
                            ) ?>

                        </div>

                    </div>

                <?php endif; ?>


                <div class="row mb-3">

                    <div class="col-md-4">

                        <strong>Email</strong>

                    </div>

                    <div class="col-md-8">

                        <?= htmlspecialchars(
                            $agent['email'] ?? ''
                        ) ?>

                    </div>

                </div>


                <div class="row mb-3">

                    <div class="col-md-4">

                        <strong>Phone</strong>

                    </div>

                    <div class="col-md-8">

                        <?php if (!empty($agent['phone'])): ?>

                            <?= htmlspecialchars(
                                $agent['phone']
                            ) ?>

                        <?php else: ?>

                            <span class="text-muted">

                                Not Set

                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="row mb-3">

                    <div class="col-md-4">

                        <strong>Position</strong>

                    </div>

                    <div class="col-md-8">

                        <?php if (!empty($agent['position'])): ?>

                            <?= htmlspecialchars(
                                $agent['position']
                            ) ?>

                        <?php else: ?>

                            <span class="text-muted">

                                Not Set

                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="row mb-3">

                    <div class="col-md-4">

                        <strong>Status</strong>

                    </div>

                    <div class="col-md-8">

                        <?php if (
                            ($agent['status'] ?? '') === 'Active'
                        ): ?>

                            <span class="badge bg-success">

                                Active

                            </span>

                        <?php else: ?>

                            <span class="badge bg-secondary">

                                <?= htmlspecialchars(
                                    $agent['status'] ?? 'Unknown'
                                ) ?>

                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <?php if (isset($agent['created_at'])): ?>

                    <div class="row mb-3">

                        <div class="col-md-4">

                            <strong>Created</strong>

                        </div>

                        <div class="col-md-8">

                            <?= htmlspecialchars(
                                $agent['created_at'] ?? ''
                            ) ?>

                        </div>

                    </div>

                <?php endif; ?>


                <hr>


                <a
                    href="?page=agents"
                    class="btn btn-secondary">

                    Back to Agents

                </a>


            </div>

        </div>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>