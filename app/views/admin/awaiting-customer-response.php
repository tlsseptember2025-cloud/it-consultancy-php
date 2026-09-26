<?php 
require dirname(__DIR__) . '/layouts/header-admin.php'; 
require_once APP_PATH . '/helpers/WorkflowHelper.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

$hasVerificationRows = false;

foreach ($requests as $request) {

    if (
        ($request['workflow_stage'] ?? '')
        !== 'Closure Agreement Sent'
    ) {
        $hasVerificationRows = true;
        break;
    }
}

?>

<h2 class="mb-4">
    Awaiting Customer Response
</h2>

<div class="d-flex justify-content-end mb-3">

    <form
        method="get"
        class="d-flex gap-2"
        id="awaitingResponseSearchForm"
    >

        <input
            type="hidden"
            name="page"
            value="awaiting-customer-response"
        >

        <input
            type="text"
            name="search"
            id="awaitingResponseSearch"
            class="form-control"
            placeholder="Search requests..."
            value="<?= htmlspecialchars($search) ?>"
            autocomplete="off"
            style="width:280px;"
        >

        <?php if ($search !== ''): ?>

            <a
                href="?page=awaiting-customer-response"
                class="btn btn-outline-secondary"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>

</div>

<div class="card shadow-sm">

    <div class="card-body">

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead class="table-dark">

                    <tr>

                        <th
                            class="text-center"
                            style="width:100px;">

                            Request #

                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Service
                        </th>


                        <?php if ($hasVerificationRows): ?>

                            <th>
                                Email #
                            </th>

                            <th>
                                Response Deadline
                            </th>

                        <?php endif; ?>


                        <th>
                            Status
                        </th>

                        <th width="180">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (empty($requests)): ?>

                        <tr>

                            <td
                                colspan="<?= $hasVerificationRows ? 7 : 5 ?>"
                                class="text-center text-muted py-4">

                                No requests awaiting customer response.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($requests as $request): ?>

                            <?php

                            $isClosureAgreement =
                                (
                                    ($request['workflow_stage'] ?? '')
                                    === 'Closure Agreement Sent'
                                );

                            ?>


                            <tr>


                                <!-- Request -->

                                <td class="text-center">

                                    <strong>
                                        #<?= (int) $request['id'] ?>
                                    </strong>

                                </td>


                                <!-- Customer -->

                                <td>

                                    <?= htmlspecialchars(
                                        $request['customer_name']
                                    ) ?>

                                </td>


                                <!-- Service -->

                                <td>

                                    <?= htmlspecialchars(
                                        $request['service_name']
                                    ) ?>

                                </td>


                                <?php if ($hasVerificationRows): ?>


                                    <!-- Email Number -->

                                    <td>

                                        <?php if ($isClosureAgreement): ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-primary">

                                                <?= (int) (
                                                    $request[
                                                        'verification_email_count'
                                                    ] ?? 0
                                                ) ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Response Deadline -->

                                    <td>

                                        <?php if (
                                            $isClosureAgreement
                                        ): ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php elseif (
                                            !empty(
                                                $request[
                                                    'customer_response_deadline'
                                                ]
                                            )
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $request[
                                                    'customer_response_deadline'
                                                ]
                                            ) ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                <?php endif; ?>


                                <!-- Workflow Status -->

                                <td>

                                    <?= workflowBadge(
                                        $request['workflow_stage']
                                    ) ?>

                                </td>


                                <!-- Action -->

                                <td>

                                    <?php if ($isClosureAgreement): ?>

                                        <a
                                            href="?page=admin-close-request&id=<?= (int) $request['id'] ?>"
                                            class="btn btn-primary btn-sm">

                                            View / Resend

                                        </a>

                                    <?php else: ?>

                                        <a
                                            href="?page=view-awaiting-customer-response&id=<?= (int) $request['id'] ?>"
                                            class="btn btn-primary btn-sm">

                                            View

                                        </a>

                                    <?php endif; ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>

    <?php if ($totalPages > 1): ?>

    <nav class="mt-3" aria-label="Awaiting Customer Response pagination">

        <ul class="pagination justify-content-center">

            <?php if ($page > 1): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= buildPaginationUrl(
                            'awaiting-customer-response',
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
                            'awaiting-customer-response',
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
                            'awaiting-customer-response',
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

    const searchInput = document.getElementById('awaitingResponseSearch');

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
                'awaiting-customer-response'
            );

            // New search always starts from page 1.
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

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>