<?php

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=agent-login');
    exit;
}


require_once CONFIG_PATH . '/database.php';

$agentId = $_SESSION['agent']['id'];

$totalConsultations = $pdo->prepare("
    SELECT COUNT(*)
    FROM consultation_bookings
    WHERE agent_id = ?
");

$totalConsultations->execute([$agentId]);

$totalConsultations = $totalConsultations->fetchColumn();

$totalServices = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests
    WHERE agent_id = ?
");

$totalServices->execute([$agentId]);

$totalServices = $totalServices->fetchColumn();

$jobsInProgress = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests
    WHERE agent_id = ?
    AND job_status = 'In Progress'
");

$jobsInProgress->execute([$agentId]);

$jobsInProgress = $jobsInProgress->fetchColumn();

$completedServices = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests
    WHERE agent_id = ?
    AND job_status = 'Completed'
");

$completedServices->execute([$agentId]);

$completedServices = $completedServices->fetchColumn();


$totalNotifications = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE recipient_type = 'agent'
    AND recipient_id = ?
    AND is_read = 0
");

$totalNotifications->execute([$agentId]);

$totalNotifications = $totalNotifications->fetchColumn();

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <h2 class="mb-2">
        Welcome,
        <?= htmlspecialchars($_SESSION['agent']['name']) ?>
    </h2>

    <p class="text-muted mb-1">
        Position:
        <?= htmlspecialchars($_SESSION['agent']['position']) ?>
    </p>

    <p class="text-success mb-4">
        Status:
        <?= htmlspecialchars($_SESSION['agent']['status']) ?>
    </p>

    <div class="row g-4">

        <div class="col-md-3">

            <div class="card border-primary shadow-sm">

                <div class="card-body text-center">

                    <h5>Assigned Consultations</h5>

                    <h2><?= $totalConsultations ?></h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-info shadow-sm">

                <div class="card-body text-center">

                    <h5>Assigned Service Jobs</h5>

                    <h2><?= $totalServices ?></h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-success shadow-sm">

                <div class="card-body text-center">

                    <h5>Completed Jobs</h5>

                    <h2><?= $completedServices ?></h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-warning shadow-sm">

                <div class="card-body text-center">

                    <h5>Notifications</h5>

                    <h2><?= $totalNotifications ?></h2>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>