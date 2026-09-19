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

    $agentsPdo = $demoPdo;

    $demoTenantId = (int) $_SESSION['demo_user']['demo_tenant_id'];

} else {

    require CONFIG_PATH . '/database.php';

    $agentsPdo = $pdo;

    $demoTenantId = null;
}


/*
|--------------------------------------------------------------------------
| Load Agents
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['demo_user'])) {

    /*
     * Demo Admin sees only Agents belonging
     * to the current Demo tenant.
     */

    $stmt = $agentsPdo->prepare("
        SELECT *
        FROM agents
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
        ORDER BY name ASC
    ");

    $stmt->execute([
        $demoTenantId
    ]);

} else {

    /*
     * Main System Admin sees all Agents.
     */

    $stmt = $agentsPdo->query("
        SELECT *
        FROM agents
        ORDER BY name ASC
    ");
}

$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);


require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <div class="d-flex justify-content-between align-items-center mb-2">

            <h2 class="mb-0">
                Agents
            </h2>

            <?php if (!isset($_SESSION['demo_user'])): ?>

                <a
                    href="?page=add-agent"
                    class="btn btn-success">

                    Add Agent

                </a>

            <?php endif; ?>

        </div>


        <p class="text-muted">

            Total Agents:
            <strong><?= count($agents) ?></strong>

        </p>


        <?php if (empty($agents)): ?>

            <div class="alert alert-info">

                No agents found.

            </div>

        <?php else: ?>

            <table class="table table-bordered table-hover">

                <thead>

                    <tr>

                        <th width="50">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Position</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>

                    </tr>

                </thead>


                <tbody>

                    <?php $i = 1; ?>

                    <?php foreach ($agents as $agent): ?>

                        <tr>

                            <td>
                                <?= $i++ ?>
                            </td>


                            <td class="text-nowrap">

                                <strong>
                                    <?= htmlspecialchars(
                                        $agent['name']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $agent['email']
                                ) ?>

                            </td>


                            <td>

                                <?= !empty($agent['phone'])

                                    ? htmlspecialchars(
                                        $agent['phone']
                                    )

                                    : '<span class="text-muted fst-italic">
                                        Not Set
                                       </span>' ?>

                            </td>


                            <td>

                                <?= !empty($agent['position'])

                                    ? htmlspecialchars(
                                        $agent['position']
                                    )

                                    : '<span class="text-muted fst-italic">
                                        Not Set
                                       </span>' ?>

                            </td>


                            <td class="text-center">

                                <?php if (
                                    $agent['status'] === 'Active'
                                ): ?>

                                    <span
                                        class="badge rounded-pill bg-success">

                                        Active

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge rounded-pill bg-secondary">

                                        Inactive

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td class="text-center">

                                <td class="text-center">

    <?php if (isset($_SESSION['demo_user'])): ?>

        <a
            href="?page=view-agent&id=<?= (int) $agent['id'] ?>"
            class="btn btn-info btn-sm px-3">

            View

        </a>

    <?php else: ?>

        <a
            href="?page=edit-agent&id=<?= (int) $agent['id'] ?>"
            class="btn btn-warning btn-sm px-3">

            Edit

        </a>

    <?php endif; ?>

</td>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>