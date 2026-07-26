<?php

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM consultation_bookings cb
    INNER JOIN requests r
        ON r.id = cb.request_id
    WHERE
        cb.agent_id = ?
        AND r.workflow_stage = ?
");

$stmt->execute([
    $_SESSION['agent']['id'],
    'Customer Contact Approved'
]);

$customerContactApprovedCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold"
            href="?page=agent-dashboard"
            title="<?= COMPANY_TAGLINE ?>">

            <?= COMPANY_NAME ?>

        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div
            class="collapse navbar-collapse"
            id="navbarNav">

            <ul class="navbar-nav ms-auto align-items-lg-center">

                <!-- Consultations -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=agent-consultations">

                        My Consultations

                    </a>

                </li>

                <!-- Service Jobs -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=agent-jobs">

                        My Service Jobs

                    </a>

                </li>

                <!-- Profile -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=agent-profile">

                        Profile

                    </a>

                </li>

                <!-- Logout -->

                <li class="nav-item">

                    <a
                        class="nav-link text-danger"
                        href="?page=agent-logout">

                        Logout

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>