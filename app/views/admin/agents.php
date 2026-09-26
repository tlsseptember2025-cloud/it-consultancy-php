<?php

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/SearchPaginationHelper.php';

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

$search = getSearchTerm();

$page = getPageNumber();

$limit = getPageLimit(10);

$params = [];


if (isset($_SESSION['demo_user'])) {

    /*
     * Demo Admin sees only Agents belonging
     * to the current Demo tenant.
     */

    $where = "
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
    ";

    $params[] = $demoTenantId;

} else {

    /*
     * Main System Admin sees all Agents.
     */

    $where = "WHERE 1 = 1";
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$where .= buildSearchCondition(
    [
        'name',
        'email',
        'phone',
        'position'
    ],
    $search,
    $params
);


/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/

$countStmt = $agentsPdo->prepare("
    SELECT COUNT(*)
    FROM agents
    $where
");

$countStmt->execute($params);

$totalAgents = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages(
    $totalAgents,
    $limit
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = getPageOffset(
    $page,
    $limit
);


/*
|--------------------------------------------------------------------------
| Load Agents
|--------------------------------------------------------------------------
*/

$stmt = $agentsPdo->prepare("
    SELECT *
    FROM agents
    $where
    ORDER BY name ASC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);

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

            Total Agents: <strong><?= $totalAgents ?></strong>

        </p>

        <div class="card shadow-sm mb-3">

    <div class="card-body">

        <form method="GET" class="row g-3 align-items-end">

            <input
                type="hidden"
                name="page"
                value="agents">

            <div class="col-md-8">

                <label class="form-label">
                    Search Agents
                </label>

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Name, email, phone, or position"
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">

            </div>

            <div class="col-md-4 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary">

                    Search

                </button>

                <?php if ($search !== ''): ?>

                    <a
                        href="?page=agents"
                        class="btn btn-outline-secondary">

                        Clear

                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>

</div>


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

            <?php if ($totalPages > 1): ?>

    <nav aria-label="Agent pagination">

        <ul class="pagination justify-content-center mt-4">

            <?php if ($page > 1): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'agents',
                                $page - 1,
                                ['search' => $search]
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">

                        Previous

                    </a>

                </li>

            <?php endif; ?>


            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li class="page-item <?= $i === $page ? 'active' : '' ?>">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'agents',
                                $i,
                                ['search' => $search]
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">

                        <?= $i ?>

                    </a>

                </li>

            <?php endfor; ?>


            <?php if ($page < $totalPages): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'agents',
                                $page + 1,
                                ['search' => $search]
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">

                        Next

                    </a>

                </li>

            <?php endif; ?>

        </ul>

    </nav>

<?php endif; ?>

        <?php endif; ?>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>