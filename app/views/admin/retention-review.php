<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/retention_review_helper.php';

$requests = getRetentionReviewRequests($pdo);

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="mb-1">
                Retention Review
            </h2>

            <p class="text-muted mb-0">
                Archived requests requiring administrator retention review.
            </p>
        </div>

        <span class="badge bg-warning text-dark">
            <?= count($requests) ?> Due
        </span>

    </div>


    <?php if (empty($requests)): ?>

        <div class="alert alert-info">
            No requests are currently due for retention review.
        </div>

    <?php else: ?>

        <div class="card shadow-sm">

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead class="table-light">

                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Archived</th>
                                <th>Review Due</th>
                                <th>Retention Expires</th>
                                <th>Extension</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <td>
                                    #<?= (int) $request['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $request['customer_name']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $request['service_title']
                                    ) ?>
                                </td>

                                <td>
                                    <?= !empty($request['archived_at'])
                                        ? date(
                                            'd M Y',
                                            strtotime($request['archived_at'])
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($request['retention_review_at'])
                                        ? date(
                                            'd M Y',
                                            strtotime(
                                                $request['retention_review_at']
                                            )
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($request['retention_expires_at'])
                                        ? date(
                                            'd M Y',
                                            strtotime(
                                                $request['retention_expires_at']
                                            )
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= (int) $request['retention_extension_years'] ?>
                                    year(s)
                                </td>

                                <td>

                                    <a
                                        href="?page=review-retention&id=<?= (int) $request['id'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Review
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>