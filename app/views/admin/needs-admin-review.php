<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';


/*
|--------------------------------------------------------------------------
| Load Requests Requiring Administrator Review
|--------------------------------------------------------------------------
|
| Includes:
|
| - Normal Needs Admin Review requests
| - Consultation / Service reviews
| - Service reschedule requests awaiting approval
|
*/

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$params = [];

$where = "
    WHERE r.workflow_stage IN (
        'Needs Admin Review',
        'Awaiting Reschedule Approval',
        'Needs Admin Final Approval'
    )
";

$where .= buildSearchCondition(
    [
        'r.id',
        'c.name',
        'a.name',
        's.title',
        'r.review_type',
        'r.incomplete_reason',
        'r.description',
        'r.contact_result',
        'r.missed_consultation_reason',
        'r.workflow_stage',
        'r.job_status'
    ],
    $search,
    $params
);


/*
|--------------------------------------------------------------------------
| Count matching review requests
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN agents a
        ON a.id = r.agent_id

    INNER JOIN services s
        ON s.id = r.service_id

    $where
");

$countStmt->execute($params);

$totalRequests = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages(
    $totalRequests,
    $limit
);

$page = min(
    $page,
    $totalPages
);

$offset = getPageOffset(
    $page,
    $limit
);


/*
|--------------------------------------------------------------------------
| Load paginated review requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        r.id,
        r.job_status,
        r.workflow_stage,
        r.review_type,
        r.incomplete_reason,
        r.contact_result,
        r.missed_consultation_reason,
        r.pending_reschedule_slot_id,

        c.name AS customer_name,

        a.name AS agent_name,

        s.title AS service_name,


        /* Consultation schedule */

        cs.slot_date AS consultation_date,
        cs.slot_time AS consultation_time,


        /* Current service schedule */

        ss.service_date,
        ss.service_time,


        /* Pending consultation reschedule */

        pending_cs.slot_date AS pending_consultation_date,
        pending_cs.slot_time AS pending_consultation_time,


        /* Pending service reschedule */

        pending_ss.service_date AS pending_service_date,
        pending_ss.service_time AS pending_service_time


    FROM requests r


    INNER JOIN customers c
        ON c.id = r.customer_id


    INNER JOIN agents a
        ON a.id = r.agent_id


    INNER JOIN services s
        ON s.id = r.service_id


    /* Consultation booking */

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id


    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id


    /* Service booking */

    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id


    LEFT JOIN service_slots ss
        ON ss.id = sb.slot_id


    /* Pending consultation reschedule */

    LEFT JOIN consultation_slots pending_cs
        ON pending_cs.id = r.pending_reschedule_slot_id


    /* Pending service reschedule */

    LEFT JOIN service_slots pending_ss
        ON pending_ss.id = r.pending_reschedule_slot_id


    $where


    ORDER BY

        CASE

            WHEN r.workflow_stage = 'Awaiting Reschedule Approval'
                THEN pending_ss.service_date

            WHEN r.review_type IN (
                'service_missed',
                'service_overdue'
            )
                THEN ss.service_date

            ELSE cs.slot_date

        END DESC,


        CASE

            WHEN r.workflow_stage = 'Awaiting Reschedule Approval'
                THEN pending_ss.service_time

            WHEN r.review_type IN (
                'service_missed',
                'service_overdue'
            )
                THEN ss.service_time

            ELSE cs.slot_time

        END DESC

    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt->execute();

$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);


require VIEW_PATH . '/layouts/header-admin.php';

?>


<div class="container py-4">


    <?php if (
        isset($_GET['success'])
        && $_GET['success'] === 'rescheduled'
    ): ?>

        <div
            id="successAlert"
            class="alert alert-success alert-dismissible fade show"
            role="alert">

            <strong>Success!</strong>

            The consultation has been successfully rescheduled.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>

        </div>


        <script>

            if (window.history.replaceState) {

                const url = new URL(window.location);

                url.searchParams.delete('success');

                window.history.replaceState(
                    {},
                    document.title,
                    url.pathname + url.search
                );

            }


            setTimeout(function () {

                const alert = document.getElementById(
                    'successAlert'
                );

                if (alert) {

                    alert.classList.remove('show');

                    setTimeout(function () {

                        alert.remove();

                    }, 300);

                }

            }, 5000);

        </script>

    <?php endif; ?>


    <h2 class="mb-1">

        Needs Admin Review

    </h2>

    <div class="d-flex justify-content-end mb-3">

    <form
        method="get"
        class="d-flex gap-2"
        id="reviewSearchForm"
    >

        <input
            type="hidden"
            name="page"
            value="needs-admin-review"
        >

        <input
            type="text"
            name="search"
            id="reviewSearch"
            class="form-control"
            placeholder="Search reviews..."
            value="<?= htmlspecialchars($search) ?>"
            autocomplete="off"
            style="width:280px;"
        >

        <?php if ($search !== ''): ?>

            <a
                href="?page=needs-admin-review"
                class="btn btn-outline-secondary"
                id="clearReviewSearch"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>

</div>


    <p class="text-muted mb-4">

        Requests that require an administrator's decision.

    </p>


    <?php if (empty($consultations)): ?>


        <div class="alert alert-success">

            There are currently no requests waiting for administrator review.

        </div>


    <?php else: ?>


        <div class="card shadow-sm">

            <div class="card-body p-0">

                <table class="table table-hover mb-0">

                    <thead class="table-dark">

                        <tr>

                            <th
                                class="text-center"
                                style="width:100px;">

                                Request #

                            </th>

                            <th>Customer</th>

                            <th>Agent</th>

                            <th>Service</th>

                            <th>Review Purpose</th>

                            <th>Reason</th>

                            <th
                                style="
                                    width:150px;
                                    white-space:nowrap;
                                ">

                                Date

                            </th>

                            <th
                                style="
                                    width:150px;
                                    white-space:nowrap;
                                ">

                                Action

                            </th>

                        </tr>

                    </thead>


                    <tbody>



                        <?php foreach ($consultations as $consultation): ?>

                            <?php

                                $isConsultationReschedule = (
                                    $consultation['workflow_stage']
                                        === 'Awaiting Reschedule Approval'
                                    &&
                                    !empty($consultation['pending_consultation_date'])
                                );

                                $isServiceReschedule = (
                                    $consultation['workflow_stage']
                                        === 'Awaiting Reschedule Approval'
                                    &&
                                    !empty($consultation['pending_service_date'])
                                );

                            ?>

                            <tr>


                                <td class="text-center">

                                    <strong>

                                        #<?= (int) $consultation['id'] ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $consultation['customer_name']
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $consultation['agent_name']
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $consultation['service_name']
                                    ) ?>

                                </td>


                                <!-- Review Purpose -->

                                <td>



                                    <?php if ($isConsultationReschedule): ?>

    <span
        class="
            badge
            bg-warning
            text-dark
        ">

        Consultation Reschedule Approval

    </span>

    <div
        class="
            small
            text-muted
            mt-1
        ">

        Customer selected a new consultation
        appointment awaiting approval.

    </div>


<?php elseif ($isServiceReschedule): ?>

    <span
        class="
            badge
            bg-warning
            text-dark
        ">

        Service Reschedule Approval

    </span>

    <div
        class="
            small
            text-muted
            mt-1
        ">

        Customer selected a new service
        appointment awaiting approval.

    </div>


                                    <?php elseif (

                                        $consultation['review_type']
                                            === 'consultation_overdue'

                                    ): ?>


                                        <span class="badge bg-danger">

                                            Consultation Overdue

                                        </span>


                                        <div
                                            class="
                                                small
                                                text-muted
                                                mt-1
                                            ">

                                            Consultation exceeded the
                                            scheduled one-hour session.

                                        </div>


                                    <?php elseif (

                                        $consultation['review_type']
                                            === 'service_missed'

                                    ): ?>


                                        <span class="badge bg-danger">

                                            Missed Service

                                        </span>


                                        <div
                                            class="
                                                small
                                                text-muted
                                                mt-1
                                            ">

                                            Service was not started within
                                            the one-hour start window.

                                        </div>


                                    <?php elseif (

    $consultation['review_type']
        === 'service_overdue'

): ?>

    <span class="badge bg-danger">

        Service Overdue

    </span>

    <div
        class="
            small
            text-muted
            mt-1
        ">

        Service remained In Progress
        after the scheduled one-hour
        session.

    </div>


<!-- ADD THE NEW BLOCK HERE -->

<?php elseif (

    $consultation['review_type']
        === 'service_not_completed'

): ?>

    <span class="badge bg-danger">

        Service Not Completed

    </span>

    <div
        class="
            small
            text-muted
            mt-1
        ">

        The customer confirmed that the service was not
        completed after the agent's explanation was accepted.

    </div>


<!-- THEN CONTINUE WITH THE EXISTING BLOCK -->

<?php elseif (

    $consultation['review_type']
        === 'customer_contact'

): ?>


                                        <span
                                            class="
                                                badge
                                                bg-warning
                                                text-dark
                                            ">

                                            Customer Contact Review

                                        </span>


                                        <div
                                            class="
                                                small
                                                text-muted
                                                mt-1
                                            ">

                                            Customer contact requires
                                            administrator action.

                                        </div>


                                    <?php else: ?>


                                        <span class="badge bg-secondary">

                                            Consultation Review

                                        </span>


                                        <div
                                            class="
                                                small
                                                text-muted
                                                mt-1
                                            ">

                                            Consultation requires
                                            administrator review.

                                        </div>


                                    <?php endif; ?>


                                </td>


                                <!-- Reason -->

<td>

    <?php if ($isConsultationReschedule): ?>

        Customer selected a new consultation
        appointment.

    <?php elseif ($isServiceReschedule): ?>

        Customer selected a new service
        appointment.

    <?php else: ?>

        <?= htmlspecialchars(
            $consultation[
                'incomplete_reason'
            ] ?? ''
        ) ?>

    <?php endif; ?>

</td>
                                <!-- Date -->

                                <td
                                    style="
                                        width:150px;
                                        white-space:nowrap;
                                    ">


                                    <?php

                                    if ($isConsultationReschedule) {

                                        $reviewDate =
                                            $consultation[
                                                'pending_consultation_date'
                                            ];

                                        $reviewTime =
                                            $consultation[
                                                'pending_consultation_time'
                                            ];

                                    } elseif ($isServiceReschedule) {

                                        $reviewDate =
                                            $consultation[
                                                'pending_service_date'
                                            ];

                                        $reviewTime =
                                            $consultation[
                                                'pending_service_time'
                                            ];

                                    }


                                     elseif (

                                        in_array(
                                            $consultation['review_type'],
                                            [
                                                'service_missed',
                                                'service_overdue',
                                                'service_not_completed'
                                            ],
                                            true
                                        )

                                    ) {

                                        $reviewDate =
                                            $consultation['service_date'];

                                        $reviewTime =
                                            $consultation['service_time'];

                                    } else {

                                        $reviewDate =
                                            $consultation[
                                                'consultation_date'
                                            ];

                                        $reviewTime =
                                            $consultation[
                                                'consultation_time'
                                            ];

                                    }

                                    ?>


                                    <?= $reviewDate

                                        ? formatDate($reviewDate)

                                        : '<span class="text-muted">
                                            N/A
                                           </span>'

                                    ?>


                                    <br>


                                    <small class="text-muted">


                                        <?= $reviewTime

                                            ? formatTime($reviewTime)

                                            : ''

                                        ?>


                                    </small>


                                </td>


                                <!-- Action -->

                                <td
                                    style="
                                        width:150px;
                                        white-space:nowrap;
                                    ">


                                    <?php if ($isConsultationReschedule): ?>

    <a
        href="?page=review-reschedule-consultation&id=<?= (int) $consultation['id'] ?>"
        class="
            btn
            btn-warning
            btn-sm
        ">

        Review Consultation Reschedule →

    </a>


<?php elseif ($isServiceReschedule): ?>

    <a
        href="?page=review-reschedule-service&id=<?= (int) $consultation['id'] ?>"
        class="
            btn
            btn-warning
            btn-sm
        ">

        Review Service Reschedule →

    </a>


                                    <?php elseif (

    $consultation['review_type'] === 'service_missed'

): ?>


    <a
        href="?page=admin-review-service-job&id=<?= (int) $consultation['id'] ?>"
        class="
            btn
            btn-danger
            btn-sm
        ">

        Review Missed Service →

    </a>


<?php elseif (

    $consultation['review_type'] === 'service_overdue'

): ?>


    <a
        href="?page=admin-review-service-job&id=<?= (int) $consultation['id'] ?>"
        class="
            btn
            btn-danger
            btn-sm
        ">

        Review Overdue Service →

    </a>

    <?php elseif (

    $consultation['review_type'] === 'service_not_completed'

): ?>

    <a
        href="?page=admin-review-service-job&id=<?= (int) $consultation['id'] ?>"
        class="
            btn
            btn-danger
            btn-sm
        ">

        Review Service Reassignment →

    </a>

    <?php elseif (

    $consultation['workflow_stage']
    === 'Needs Admin Final Approval'

): ?>

    <a
        href="?page=admin-final-approve-consultation&id=<?= (int) $consultation['id'] ?>"
        class="
            btn
            btn-success
            btn-sm
        ">

        Final Approve Consultation →

    </a>


    <?php elseif (

    $consultation['workflow_stage'] === 'Needs Admin Review'

    && !empty($consultation['missed_consultation_reason'])

): ?>

    <a
        href="?page=review-missed-consultation&id=<?= (int) $consultation['id'] ?>"
        class="
            btn
            btn-warning
            btn-sm
        ">

        Review Missed Consultation →

    </a>


                                    <?php else: ?>


                                        <a
                                            href="?page=admin-review-consultation&id=<?= (int) $consultation['id'] ?>"
                                            class="
                                                btn
                                                btn-primary
                                                btn-sm
                                            ">

                                            Review Consultation →

                                        </a>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

        </div>


    <?php endif; ?>

    <?php if ($totalPages > 1): ?>

    <nav class="mt-3" aria-label="Needs Admin Review pagination">

        <ul class="pagination justify-content-center">

            <?php if ($page > 1): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= buildPaginationUrl(
                            'needs-admin-review',
                            $page - 1,
                            ['search' => $search]
                        ) ?>"
                    >
                        Previous
                    </a>

                </li>

            <?php endif; ?>


            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li
                    class="page-item <?= $i === $page ? 'active' : '' ?>"
                >

                    <a
                        class="page-link"
                        href="<?= buildPaginationUrl(
                            'needs-admin-review',
                            $i,
                            ['search' => $search]
                        ) ?>"
                    >
                        <?= $i ?>
                    </a>

                </li>

            <?php endfor; ?>


            <?php if ($page < $totalPages): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= buildPaginationUrl(
                            'needs-admin-review',
                            $page + 1,
                            ['search' => $search]
                        ) ?>"
                    >
                        Next
                    </a>

                </li>

            <?php endif; ?>

        </ul>

    </nav>

<?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('reviewSearch');

    if (!searchInput) {
        return;
    }

    let searchTimer;

    searchInput.addEventListener('input', function () {

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {

            const search = searchInput.value.trim();

            const url = new URL(window.location.href);

            url.searchParams.set(
                'page',
                'needs-admin-review'
            );

            // Always return to page 1 after a new search.
            url.searchParams.set('p', '1');

            if (search !== '') {

                url.searchParams.set(
                    'search',
                    search
                );

            } else {

                url.searchParams.delete('search');

            }

            window.location.href = url.toString();

        }, 300);

    });

});
</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>