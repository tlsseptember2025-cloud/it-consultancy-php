<?php

require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Customer database / authentication context
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $ratingPdo = $demoPdo;

    $customerId = (int) (
        $_SESSION['demo_customer']['id'] ?? 0
    );

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($customerId <= 0 || $demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Demo Customer
    |--------------------------------------------------------------------------
    */

    $demoCustomerCheck = $ratingPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $demoCustomerCheck->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$demoCustomerCheck->fetchColumn()) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=public-login');
        exit;
    }

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $ratingPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}

/*
|--------------------------------------------------------------------------
| Request Parameters
|--------------------------------------------------------------------------
*/

$bookingId = (int) (
    $_GET['booking_id'] ?? 0
);

$serviceBookingId = (int) (
    $_GET['service_booking_id'] ?? 0
);

$requestId = (int) (
    $_GET['request_id'] ?? 0
);

$type = $_GET['type'] ?? '';

/*
|--------------------------------------------------------------------------
| Validate Rating Type
|--------------------------------------------------------------------------
*/

if (!in_array($type, ['consultation', 'service'], true)) {

    die('Invalid rating request.');
}

if (
    $type === 'consultation'
    && $bookingId <= 0
) {

    die('Invalid consultation rating request.');
}

if (
    $type === 'service'
    && $serviceBookingId <= 0
) {

    die('Invalid service rating request.');
}

/*
|--------------------------------------------------------------------------
| Load Consultation / Service
|--------------------------------------------------------------------------
*/

if ($type === 'consultation') {

    /*
    |--------------------------------------------------------------------------
    | Consultation Rating
    |--------------------------------------------------------------------------
    */

    if ($isDemoCustomer) {

        $stmt = $ratingPdo->prepare("
            SELECT
                r.id,
                r.customer_id,
                r.agent_id,
                r.workflow_stage,
                r.job_status,
                r.completed_at,

                cb.id AS consultation_booking_id,
                cb.agent_id AS booking_agent_id,

                c.name AS customer_name,
                c.email,

                s.title AS service_name,

                a.name AS agent_name

            FROM consultation_bookings cb

            INNER JOIN requests r
                ON r.id = cb.request_id

            INNER JOIN customers c
                ON c.id = r.customer_id

            INNER JOIN services s
                ON s.id = r.service_id

            LEFT JOIN agents a
                ON a.id = cb.agent_id

            WHERE cb.id = ?
              AND r.customer_id = ?

              AND c.demo_tenant_id = ?
              AND c.is_demo_account = 1

              AND s.demo_tenant_id = c.demo_tenant_id
              AND s.is_demo_account = 1

              AND a.demo_tenant_id = c.demo_tenant_id
              AND a.is_demo_account = 1

            LIMIT 1
        ");

        $stmt->execute([
            $bookingId,
            $customerId,
            $demoTenantId
        ]);

    } else {

        $stmt = $ratingPdo->prepare("
            SELECT
                r.id,
                r.customer_id,
                r.agent_id,
                r.workflow_stage,
                r.job_status,
                r.completed_at,

                cb.id AS consultation_booking_id,
                cb.agent_id AS booking_agent_id,

                c.name AS customer_name,
                c.email,

                s.title AS service_name,

                a.name AS agent_name

            FROM consultation_bookings cb

            INNER JOIN requests r
                ON r.id = cb.request_id

            INNER JOIN customers c
                ON c.id = r.customer_id

            INNER JOIN services s
                ON s.id = r.service_id

            LEFT JOIN agents a
                ON a.id = cb.agent_id

            WHERE cb.id = ?
              AND r.customer_id = ?

            LIMIT 1
        ");

        $stmt->execute([
            $bookingId,
            $customerId
        ]);
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | Service Rating
    |--------------------------------------------------------------------------
    */

    if ($isDemoCustomer) {

        $stmt = $ratingPdo->prepare("
            SELECT
                r.id,
                r.customer_id,
                r.agent_id,
                r.workflow_stage,
                r.job_status,
                r.completed_at,

                sb.id AS service_booking_id,
                sb.agent_id AS booking_agent_id,

                c.name AS customer_name,
                c.email,

                s.title AS service_name,

                a.name AS agent_name

            FROM service_bookings sb

            INNER JOIN requests r
                ON r.id = sb.request_id

            INNER JOIN customers c
                ON c.id = r.customer_id

            INNER JOIN services s
                ON s.id = r.service_id

            LEFT JOIN agents a
                ON a.id = sb.agent_id

            WHERE sb.id = ?
              AND r.customer_id = ?

              AND c.demo_tenant_id = ?
              AND c.is_demo_account = 1

              AND s.demo_tenant_id = c.demo_tenant_id
              AND s.is_demo_account = 1

              AND a.demo_tenant_id = c.demo_tenant_id
              AND a.is_demo_account = 1

            LIMIT 1
        ");

        $stmt->execute([
            $serviceBookingId,
            $customerId,
            $demoTenantId
        ]);

    } else {

        $stmt = $ratingPdo->prepare("
            SELECT
                r.id,
                r.customer_id,
                r.agent_id,
                r.workflow_stage,
                r.job_status,
                r.completed_at,

                sb.id AS service_booking_id,
                sb.agent_id AS booking_agent_id,

                c.name AS customer_name,
                c.email,

                s.title AS service_name,

                a.name AS agent_name

            FROM service_bookings sb

            INNER JOIN requests r
                ON r.id = sb.request_id

            INNER JOIN customers c
                ON c.id = r.customer_id

            INNER JOIN services s
                ON s.id = r.service_id

            LEFT JOIN agents a
                ON a.id = sb.agent_id

            WHERE sb.id = ?
              AND r.customer_id = ?

            LIMIT 1
        ");

        $stmt->execute([
            $serviceBookingId,
            $customerId
        ]);
    }
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {

    die('Request not found.');
}

/*
|--------------------------------------------------------------------------
| Confirm Agent Assignment
|--------------------------------------------------------------------------
*/

if (empty($request['booking_agent_id'])) {

    die(
        $type === 'consultation'
            ? 'No agent is assigned to this consultation.'
            : 'No agent is assigned to this service.'
    );
}

/*
|--------------------------------------------------------------------------
| Make Sure Work Has Actually Been Completed
|--------------------------------------------------------------------------
*/

if ($type === 'consultation') {

    if (
        empty($request['consultation_booking_id'])
        || $request['job_status'] !== 'Completed'
        || empty($request['completed_at'])
    ) {

        die(
            'This consultation is not available for rating yet.'
        );
    }

} else {

    if (
        empty($request['service_booking_id'])
        || $request['job_status'] !== 'Completed'
        || empty($request['completed_at'])
    ) {

        die(
            'This service is not available for rating yet.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Check Existing Rating
|--------------------------------------------------------------------------
*/

if ($type === 'consultation') {

    $stmt = $ratingPdo->prepare("
        SELECT rating
        FROM agent_ratings
        WHERE consultation_booking_id = ?
          AND rating_type = ?
        LIMIT 1
    ");

    $stmt->execute([
        $bookingId,
        $type
    ]);

} else {

    $stmt = $ratingPdo->prepare("
        SELECT rating
        FROM agent_ratings
        WHERE service_booking_id = ?
          AND rating_type = ?
        LIMIT 1
    ");

    $stmt->execute([
        $serviceBookingId,
        $type
    ]);
}

$existingRating = $stmt->fetchColumn();

if ($existingRating !== false) {

    die(
        'You have already rated this ' . $type . '.'
    );
}

$error = null;
$success = null;

/*
|--------------------------------------------------------------------------
| Submit Rating
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_rating'])
) {

    $submittedRating = (int) (
        $_POST['rating'] ?? 0
    );

    if (
        $submittedRating < 1
        || $submittedRating > 5
    ) {

        $error =
            'Please select a rating from 1 to 5.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Re-check Existing Rating
        |--------------------------------------------------------------------------
        */

        $stmt = $ratingPdo->prepare("
            SELECT id
            FROM agent_ratings
            WHERE request_id = ?
              AND rating_type = ?
            LIMIT 1
        ");

        $stmt->execute([
            $request['id'],
            $type
        ]);

        if ($stmt->fetch()) {

            $error =
                'You have already rated this ' . $type . '.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Save Rating
            |--------------------------------------------------------------------------
            */

            if ($type === 'consultation') {

                $stmt = $ratingPdo->prepare("
                    INSERT INTO agent_ratings
                    (
                        request_id,
                        consultation_booking_id,
                        service_booking_id,
                        customer_id,
                        agent_id,
                        rating_type,
                        rating
                    )
                    VALUES (?, ?, NULL, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $request['id'],
                    $request['consultation_booking_id'],
                    $customerId,
                    $request['booking_agent_id'],
                    $type,
                    $submittedRating
                ]);

            } else {

                $stmt = $ratingPdo->prepare("
                    INSERT INTO agent_ratings
                    (
                        request_id,
                        consultation_booking_id,
                        service_booking_id,
                        customer_id,
                        agent_id,
                        rating_type,
                        rating
                    )
                    VALUES (?, NULL, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $request['id'],
                    $request['service_booking_id'],
                    $customerId,
                    $request['booking_agent_id'],
                    $type,
                    $submittedRating
                ]);
            }

            $success =
                'Thank you for your rating.';
        }
    }
}

require VIEW_PATH . '/layouts/header-customer.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">

                        <?= $type === 'consultation'
                            ? 'Rate Your Consultation'
                            : 'Rate Your Service'
                        ?>

                    </h4>

                </div>

                <div class="card-body text-center">

                    <?php if (!empty($success)): ?>

                        <div class="alert alert-success">

                            <?= htmlspecialchars($success) ?>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($error)): ?>

                        <div class="alert alert-danger">

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>

                    <p class="mb-1">

                        <strong>

                            <?= htmlspecialchars(
                                $request['service_name']
                            ) ?>

                        </strong>

                    </p>

                    <p class="text-muted">

                        Consultant:

                        <?= htmlspecialchars(
                            $request['agent_name']
                        ) ?>

                    </p>

                    <p class="mt-4 mb-3">

                        How would you rate your

                        <?= $type === 'consultation'
                            ? 'consultation'
                            : 'service'
                        ?>?

                    </p>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?= (int) $request['id'] ?>">

                        <input
                            type="hidden"
                            name="type"
                            value="<?= htmlspecialchars($type) ?>">

                        <div class="mb-4">

                            <div
                                class="rating-stars"
                                style="font-size: 2.5rem;">

                                <?php for (
                                    $i = 1;
                                    $i <= 5;
                                    $i++
                                ): ?>

                                    <button
                                        type="button"
                                        class="btn btn-link text-warning p-1 rating-star"
                                        data-rating="<?= $i ?>"
                                        aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">

                                        ☆

                                    </button>

                                <?php endfor; ?>

                            </div>

                            <input
                                type="hidden"
                                name="rating"
                                id="rating"
                                value="">

                        </div>

                        <button
                            type="submit"
                            name="submit_rating"
                            class="btn btn-primary">

                            Submit Rating

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<script>

document.querySelectorAll('.rating-star').forEach(function (star) {

    star.addEventListener('click', function () {

        const selectedRating = parseInt(
            this.dataset.rating,
            10
        );

        document.getElementById('rating').value =
            selectedRating;

        document.querySelectorAll('.rating-star').forEach(
            function (item) {

                const itemRating = parseInt(
                    item.dataset.rating,
                    10
                );

                item.textContent =
                    itemRating <= selectedRating
                        ? '★'
                        : '☆';

            }
        );

    });

});

</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>