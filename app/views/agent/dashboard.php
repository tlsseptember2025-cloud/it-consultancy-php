<?php

require_once HELPER_PATH . '/auth.php';

$isDemoAgent = isset($_SESSION['demo_agent']);

if ($isDemoAgent) {

    requireDemoAgent();

    require_once CONFIG_PATH . '/demo-database.php';

    $dashboardPdo = $demoPdo;

    $agentId = (int) ($_SESSION['demo_agent']['id'] ?? 0);
    $demoTenantId = (int) (
        $_SESSION['demo_agent']['demo_tenant_id'] ?? 0
    );

    if ($agentId <= 0 || $demoTenantId <= 0) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
        exit;
    }

    $agentValidationStmt = $dashboardPdo->prepare("
        SELECT id
        FROM agents
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
          AND status = 'Active'
        LIMIT 1
    ");

    $agentValidationStmt->execute([
        $agentId,
        $demoTenantId
    ]);

    if (!$agentValidationStmt->fetchColumn()) {
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

    $dashboardPdo = $pdo;

    $agentId = (int) ($_SESSION['agent']['id'] ?? 0);

    if ($agentId <= 0) {
        unset($_SESSION['agent']);
        header('Location: ?page=public-login');
        exit;
    }
}

$agentRatingScopeSql = '';
$consultationAgentScopeSql = '';
$serviceAgentScopeSql = '';

$agentScopeParams = [];

if ($isDemoAgent) {

    $agentRatingScopeSql = "
        AND EXISTS (
            SELECT 1
            FROM agents demo_agent_scope
            WHERE demo_agent_scope.id = agent_ratings.agent_id
              AND demo_agent_scope.demo_tenant_id = ?
              AND demo_agent_scope.is_demo_account = 1
        )
    ";

    $consultationAgentScopeSql = "
        AND EXISTS (
            SELECT 1
            FROM agents demo_agent_scope
            WHERE demo_agent_scope.id = cb.agent_id
              AND demo_agent_scope.demo_tenant_id = ?
              AND demo_agent_scope.is_demo_account = 1
        )
    ";

    $serviceAgentScopeSql = "
        AND EXISTS (
            SELECT 1
            FROM agents demo_agent_scope
            WHERE demo_agent_scope.id = sb.agent_id
              AND demo_agent_scope.demo_tenant_id = ?
              AND demo_agent_scope.is_demo_account = 1
        )
    ";

    $agentScopeParams = [$demoTenantId];
}

/*
|--------------------------------------------------------------------------
| AGENT PERFORMANCE
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT
        COUNT(*) AS total_ratings,
        AVG(rating) AS average_rating
    FROM agent_ratings
    WHERE agent_id = ?
        {$agentRatingScopeSql}
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$agentPerformance = $stmt->fetch(PDO::FETCH_ASSOC);

$totalRatings = (int) ($agentPerformance['total_ratings'] ?? 0);

$averageRating = (float) ($agentPerformance['average_rating'] ?? 0);

/*
|--------------------------------------------------------------------------
| SERVICE PERFORMANCE
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT
        COUNT(*) AS total_service_ratings,
        AVG(rating) AS average_service_rating
    FROM agent_ratings
    WHERE agent_id = ?
        {$agentRatingScopeSql}
      AND rating_type = 'service'
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$servicePerformance = $stmt->fetch(PDO::FETCH_ASSOC);

$totalServiceRatings = (int) (
    $servicePerformance['total_service_ratings'] ?? 0
);

$averageServiceRating = (float) (
    $servicePerformance['average_service_rating'] ?? 0
);

/*
|--------------------------------------------------------------------------
| CONSULTATIONS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Assigned Consultations
|--------------------------------------------------------------------------
|
| Includes consultations still assigned to the agent, including
| missed consultations.
|
*/

$stmt = $dashboardPdo->prepare("
    SELECT COUNT(*)
    FROM consultation_bookings cb

    INNER JOIN requests r
        ON r.id = cb.request_id

    WHERE
        cb.agent_id = ?
        {$consultationAgentScopeSql}

        AND r.workflow_stage IN (
            'Consultation Confirmed',
            'Customer Contact',
            'Missed Consultation'
        )
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$assignedConsultations = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Completed Consultations
|--------------------------------------------------------------------------
|
| Counts consultations that the assigned agent has completed.
| After administrator approval, the workflow moves forward to
| Proposal Draft, so we must not depend only on the
| 'Consultation Completed' workflow stage.
|
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT COUNT(*)
    FROM consultation_bookings cb

    INNER JOIN requests r
        ON r.id = cb.request_id

    WHERE
        cb.agent_id = ?
        {$consultationAgentScopeSql}
        AND r.job_status = 'Completed'
        AND r.completed_at IS NOT NULL
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$completedConsultations = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Missed Consultations
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT COUNT(*)
    FROM consultation_bookings cb

    INNER JOIN requests r
        ON r.id = cb.request_id

    WHERE
        cb.agent_id = ?
        {$consultationAgentScopeSql}
        AND r.workflow_stage = 'Missed Consultation'
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$missedConsultations = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Last 3 Completed Consultations
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT
        r.id AS request_id,
        c.name AS customer_name,
        s.title AS service_name,
        cs.slot_date,
        cs.slot_time

    FROM consultation_bookings cb

    INNER JOIN requests r
        ON r.id = cb.request_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE
        cb.agent_id = ?
        {$consultationAgentScopeSql}
        AND r.job_status = 'Completed'
        AND r.completed_at IS NOT NULL

    ORDER BY
        r.completed_at DESC,
        r.id DESC

    LIMIT 3
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$recentCompletedConsultations = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Last 3 Missed Consultations
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT
        r.id AS request_id,
        c.name AS customer_name,
        s.title AS service_name,
        cs.slot_date,
        cs.slot_time

    FROM consultation_bookings cb

    INNER JOIN requests r
        ON r.id = cb.request_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE
        cb.agent_id = ?
        {$consultationAgentScopeSql}
        AND r.workflow_stage = 'Missed Consultation'

    ORDER BY
        cs.slot_date DESC,
        cs.slot_time DESC,
        r.id DESC

    LIMIT 3
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$recentMissedConsultations = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| SERVICE JOBS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Assigned Service Jobs
|--------------------------------------------------------------------------
|
| Only active jobs are counted here.
| Completed and failed jobs are excluded.
|
*/

$stmt = $dashboardPdo->prepare("
    SELECT COUNT(*)
    FROM service_bookings sb

    INNER JOIN requests r
        ON r.id = sb.request_id

    WHERE
        sb.agent_id = ?
        {$serviceAgentScopeSql}
        AND r.job_status IN (
            'Pending',
            'In Progress'
        )
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$assignedServiceJobs = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Completed Service Jobs
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT COUNT(*)
    FROM service_bookings sb

    INNER JOIN requests r
        ON r.id = sb.request_id

    WHERE
        sb.agent_id = ?
        {$serviceAgentScopeSql}
        AND r.job_status = 'Completed'
        AND r.completed_at IS NOT NULL
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$completedServiceJobs = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Missed / Could Not Complete Service Jobs
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT COUNT(*)
    FROM service_bookings sb

    INNER JOIN requests r
        ON r.id = sb.request_id

    WHERE
        sb.agent_id = ?
        {$serviceAgentScopeSql}
        AND r.job_status = 'Could Not Complete'
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$missedServiceJobs = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Last 3 Completed Service Jobs
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT
        r.id AS request_id,
        c.name AS customer_name,
        s.title AS service_name,
        ss.service_date,
        ss.service_time

    FROM service_bookings sb

    INNER JOIN requests r
        ON r.id = sb.request_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE
        sb.agent_id = ?
        {$serviceAgentScopeSql}
        AND r.job_status = 'Completed'
        AND r.completed_at IS NOT NULL

    ORDER BY
        r.completed_at DESC,
        r.id DESC

    LIMIT 3
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$recentCompletedServiceJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Last 3 Missed Service Jobs
|--------------------------------------------------------------------------
*/

$stmt = $dashboardPdo->prepare("
    SELECT
        r.id AS request_id,
        c.name AS customer_name,
        s.title AS service_name,
        ss.service_date,
        ss.service_time

    FROM service_bookings sb

    INNER JOIN requests r
        ON r.id = sb.request_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE
        sb.agent_id = ?
        {$serviceAgentScopeSql}
        AND r.job_status = 'Could Not Complete'

    ORDER BY
        ss.service_date DESC,
        ss.service_time DESC,
        r.id DESC

    LIMIT 3
");

$stmt->execute(array_merge([$agentId], $agentScopeParams));

$recentMissedServiceJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <!--
    |--------------------------------------------------------------------------
    | Welcome
    |--------------------------------------------------------------------------
    -->

   <div class="mb-4">

    <h2 class="mb-2">

        Welcome Back,
        <?= htmlspecialchars(
            isset($_SESSION['demo_agent'])
                ? $_SESSION['demo_agent']['username']
                : $_SESSION['agent']['name']
        ) ?>!

    </h2>

    <p class="text-muted mb-1">

        Position:
        <?= htmlspecialchars(
            isset($_SESSION['demo_agent'])
                ? $_SESSION['demo_agent']['position']
                : $_SESSION['agent']['position']
        ) ?>

    </p>

    <p class="text-success mb-0">

        Status:
        <?= htmlspecialchars(
            isset($_SESSION['demo_agent'])
                ? $_SESSION['demo_agent']['status']
                : $_SESSION['agent']['status']
        ) ?>

    </p>

</div>

<!-- Performance -->

<h4 class="mb-3">
    Performance
</h4>

<div class="row g-4 mb-5">

    <!-- Consultation Performance -->

    <div class="col-md-6">

        <div class="card border-dark shadow-sm h-100">

            <div class="card-body text-center">

                <h5 class="text-dark mb-3">
                    Consultation Performance
                </h5>

                <?php if ($totalRatings > 0): ?>

                    <?php
                    $filledConsultationStars =
                        (int) round($averageRating);
                    ?>

                    <div class="fs-2 mb-2">

                        <?php for (
                            $i = 1;
                            $i <= 5;
                            $i++
                        ): ?>

                            <?= $i <= $filledConsultationStars
                                ? '⭐'
                                : '☆' ?>

                        <?php endfor; ?>

                    </div>

                    <div class="fw-bold fs-5">

                        <?= number_format(
                            $averageRating,
                            1
                        ) ?>

                        / 5

                    </div>

                    <p class="text-muted mb-0">

                        Based on <?= $totalRatings ?>

                        <?= $totalRatings === 1
                            ? 'rating'
                            : 'ratings' ?>

                    </p>

                <?php else: ?>

                    <div class="fs-2 mb-2">
                        ☆☆☆☆☆
                    </div>

                    <p class="text-muted mb-0">
                        No consultation ratings yet.
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- Service Performance -->

    <div class="col-md-6">

        <div class="card border-dark shadow-sm h-100">

            <div class="card-body text-center">

                <h5 class="text-dark mb-3">
                    Service Performance
                </h5>

                <?php if ($totalServiceRatings > 0): ?>

                    <?php
                    $filledServiceStars =
                        (int) round($averageServiceRating);
                    ?>

                    <div class="fs-2 mb-2">

                        <?php for (
                            $i = 1;
                            $i <= 5;
                            $i++
                        ): ?>

                            <?= $i <= $filledServiceStars
                                ? '⭐'
                                : '☆' ?>

                        <?php endfor; ?>

                    </div>

                    <div class="fw-bold fs-5">

                        <?= number_format(
                            $averageServiceRating,
                            1
                        ) ?>

                        / 5

                    </div>

                    <p class="text-muted mb-0">

                        Based on <?= $totalServiceRatings ?>

                        <?= $totalServiceRatings === 1
                            ? 'rating'
                            : 'ratings' ?>

                    </p>

                <?php else: ?>

                    <div class="fs-2 mb-2">
                        ☆☆☆☆☆
                    </div>

                    <p class="text-muted mb-0">
                        No service ratings yet.
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

    <!--
    |--------------------------------------------------------------------------
    | CONSULTATIONS
    |--------------------------------------------------------------------------
    -->

    <h4 class="mb-3">

        Consultations

    </h4>

    <div class="row g-4 mb-5">


        <!-- Assigned Consultations -->

        <div class="col-md-4">

            <div class="card border-primary shadow-sm h-100">

                <div class="card-body">

                    <h5 class="text-primary">

                        Assigned Consultations

                    </h5>

                    <div class="display-5 fw-bold mb-3">

                        <?= $assignedConsultations ?>

                    </div>

                    <p class="text-muted mb-0">

                        Current consultations assigned to you.

                    </p>

                </div>

            </div>

        </div>


        <!-- Completed Consultations -->

        <div class="col-md-4">

            <div class="card border-success shadow-sm h-100">

                <div class="card-body">

                    <h5 class="text-success">

                        Completed Consultations

                    </h5>

                    <div class="display-5 fw-bold mb-3">

                        <?= $completedConsultations ?>

                    </div>


                    <?php if (empty($recentCompletedConsultations)): ?>

                        <p class="text-muted mb-0">

                            No completed consultations yet.

                        </p>

                    <?php else: ?>

                        <div class="list-group list-group-flush">

                            <?php foreach ($recentCompletedConsultations as $consultation): ?>

                                <a
                                    href="?page=view-consultation&id=<?= (int) $consultation['request_id'] ?>"
                                    class="list-group-item list-group-item-action px-0">

                                    <div class="fw-semibold">

                                        #<?= (int) $consultation['request_id'] ?>

                                        —

                                        <?= htmlspecialchars($consultation['customer_name']) ?>

                                    </div>

                                    <small class="text-muted">

                                        <?= htmlspecialchars($consultation['service_name']) ?>

                                        ·

                                        <?= htmlspecialchars($consultation['slot_date']) ?>

                                    </small>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Missed Consultations -->

        <div class="col-md-4">

            <div class="card border-danger shadow-sm h-100">

                <div class="card-body">

                    <h5 class="text-danger">

                        Missed Consultations

                    </h5>

                    <div class="display-5 fw-bold mb-3">

                        <?= $missedConsultations ?>

                    </div>


                    <?php if (empty($recentMissedConsultations)): ?>

                        <p class="text-muted mb-0">

                            No missed consultations.

                        </p>

                    <?php else: ?>

                        <div class="list-group list-group-flush">

                            <?php foreach ($recentMissedConsultations as $consultation): ?>

                                <a
                                    href="?page=explain-missed-consultation&id=<?= (int) $consultation['request_id'] ?>"
                                    class="list-group-item list-group-item-action px-0">

                                    <div class="fw-semibold">

                                        #<?= (int) $consultation['request_id'] ?>

                                        —

                                        <?= htmlspecialchars($consultation['customer_name']) ?>

                                    </div>

                                    <small class="text-muted">

                                        <?= htmlspecialchars($consultation['service_name']) ?>

                                        ·

                                        <?= htmlspecialchars($consultation['slot_date']) ?>

                                    </small>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | SERVICE JOBS
    |--------------------------------------------------------------------------
    -->

    <h4 class="mb-3">

        Service Jobs

    </h4>

    <div class="row g-4">


        <!-- Assigned Service Jobs -->

        <div class="col-md-4">

            <div class="card border-primary shadow-sm h-100">

                <div class="card-body">

                    <h5 class="text-primary">

                        Assigned Service Jobs

                    </h5>

                    <div class="display-5 fw-bold mb-3">

                        <?= $assignedServiceJobs ?>

                    </div>

                    <p class="text-muted mb-0">

                        Service Jobs will appear here once the Agent Service Jobs workflow is implemented.

                    </p>

                </div>

            </div>

        </div>


        <!-- Completed Service Jobs -->

        <div class="col-md-4">

            <div class="card border-success shadow-sm h-100">

                <div class="card-body">

                    <h5 class="text-success">

                        Completed Assigned Jobs

                    </h5>

                    <div class="display-5 fw-bold mb-3">

                        <?= $completedServiceJobs ?>

                    </div>


                    <?php if (empty($recentCompletedServiceJobs)): ?>

                        <p class="text-muted mb-0">

                            No completed service jobs yet.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Missed Service Jobs -->

        <div class="col-md-4">

            <div class="card border-danger shadow-sm h-100">

                <div class="card-body">

                    <h5 class="text-danger">

                        Missed Service Jobs

                    </h5>

                    <div class="display-5 fw-bold mb-3">

                        <?= $missedServiceJobs ?>

                    </div>


                    <?php if (empty($recentMissedServiceJobs)): ?>

                        <p class="text-muted mb-0">

                            No missed service jobs.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>