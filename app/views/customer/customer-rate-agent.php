<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';


$bookingId = (int) ($_GET['booking_id'] ?? 0);

$requestId = (int) ($_GET['request_id'] ?? 0);

$type = $_GET['type'] ?? '';

if (!in_array($type, ['consultation', 'service'], true)) {

    die('Invalid rating request.');

}

if ($type === 'consultation' && $bookingId <= 0) {

    die('Invalid consultation rating request.');

}

if ($type === 'service' && $requestId <= 0) {

    die('Invalid service rating request.');

}

$customerId = (int) $_SESSION['customer']['id'];


/*
 * Get the request and verify that it belongs
 * to the logged-in customer.
 */
$stmt = $pdo->prepare("
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

    WHERE
        cb.id = ?
        AND r.customer_id = ?

    LIMIT 1
");

$stmt->execute([
    $bookingId,
    $customerId
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {

    die('Request not found.');

}


if (empty($request['booking_agent_id'])) {

    die('No agent is assigned to this consultation.');

}


/*
 * Make sure the relevant work has actually been completed.
 */

/*
 * Make sure the relevant work has actually been completed.
 */
if ($type === 'consultation') {

    if (
        $request['job_status'] !== 'Completed'
        || empty($request['completed_at'])
    ) {

        die('This consultation is not available for rating yet.');

    }

} elseif ($type === 'service') {

    if ($request['job_status'] !== 'Completed') {

        die('This service is not available for rating yet.');

    }

}



/*
 * Check whether the customer has already rated
 * this request and rating type.
 */

if ($type === 'consultation') {

    $stmt = $pdo->prepare("
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

    $stmt = $pdo->prepare("
        SELECT rating
        FROM agent_ratings
        WHERE request_id = ?
          AND rating_type = ?
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $type
    ]);
}

$existingRating = $stmt->fetchColumn();


if ($existingRating !== false) {

    die('You have already rated this ' . $type . '.');

}


$error = null;
$success = null;


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_rating'])
) {

    $submittedRating = (int) ($_POST['rating'] ?? 0);


    if ($submittedRating < 1 || $submittedRating > 5) {

        $error = 'Please select a rating from 1 to 5.';

    } else {

        /*
         * Re-check that the customer has not already
         * submitted this rating.
         */
        $stmt = $pdo->prepare("
            SELECT id
            FROM agent_ratings
            WHERE request_id = ?
              AND rating_type = ?
            LIMIT 1
        ");

        $stmt->execute([
            $requestId,
            $type
        ]);


        if ($stmt->fetch()) {

            $error = 'You have already rated this ' . $type . '.';

        } else {

            /*
             * Save the rating.
             */
            
            $stmt = $pdo->prepare("
    INSERT INTO agent_ratings
    (
        request_id,
        consultation_booking_id,
        customer_id,
        agent_id,
        rating_type,
        rating
    )
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $request['id'],
    $request['consultation_booking_id'],
    $customerId,
    $request['booking_agent_id'],
    $type,
    $submittedRating
]);

            $success = 'Thank you for your rating.';

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
                            <?= htmlspecialchars($request['service_name']) ?>
                        </strong>

                    </p>


                    <p class="text-muted">

                        Consultant:
                        <?= htmlspecialchars($request['agent_name']) ?>

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
                            value="<?= $requestId ?>">

                        <input
                            type="hidden"
                            name="type"
                            value="<?= htmlspecialchars($type) ?>">


                        <div class="mb-4">

                            <div
                                class="rating-stars"
                                style="font-size: 2.5rem;">

                                <?php for ($i = 1; $i <= 5; $i++): ?>

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

        document.getElementById('rating').value = selectedRating;

        document.querySelectorAll('.rating-star').forEach(function (item) {

            const itemRating = parseInt(
                item.dataset.rating,
                10
            );

            item.textContent =
                itemRating <= selectedRating
                    ? '★'
                    : '☆';

        });

    });

});
</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>