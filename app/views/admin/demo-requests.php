<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once CONFIG_PATH . '/database.php';

$stmt = $pdo->query("
    SELECT
        id,
        full_name,
        email,
        phone,
        company_name,
        company_domain,
        explore_options,
        status,
        email_confirmed_at,
        created_at
    FROM demo_requests
    ORDER BY created_at DESC
");

$demoRequests = $stmt->fetchAll();

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th class="text-center" style="width:100px;">
                    Demo #
                </th>

                <th>
                    Requester
                </th>

                <th>
                    Company
                </th>

                <th>
                    Email
                </th>

                <th>
                    Domain
                </th>

                <th>
                    Status
                </th>

                <th style="width:150px; white-space:nowrap;">
                    Request Date
                </th>

                <th style="width:120px;">
                    Action
                </th>

            </tr>

        </thead>

        <tbody>

            <?php if (empty($demoRequests)): ?>

                <tr>

                    <td colspan="8" class="text-center text-muted">
                        No Demo requests found.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($demoRequests as $request): ?>

                    <tr>

                        <td class="text-center">
                            #<strong><?= (int)$request['id']; ?></strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['full_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['company_name'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['company_domain']) ?>
                        </td>

                        <td>

                            <?php if ($request['status'] === 'Confirmed'): ?>

                                <span class="badge bg-success">
                                    Confirmed
                                </span>

                            <?php elseif ($request['status'] === 'Approved'): ?>

                                <span class="badge bg-primary">
                                    Approved
                                </span>

                            <?php elseif ($request['status'] === 'Rejected'): ?>

                                <span class="badge bg-danger">
                                    Rejected
                                </span>

                            <?php elseif ($request['status'] === 'Customer Confirmed'): ?>

                                <span class="badge bg-info">
                                    Customer Confirmed
                                </span>

                            <?php elseif ($request['status'] === 'Demo Created'): ?>

                                <span class="badge bg-success">
                                    Demo Created
                                </span>

                            <?php elseif ($request['status'] === 'Expired'): ?>

                                <span class="badge bg-secondary">
                                    Expired
                                </span>

                            <?php else: ?>

                                <span class="badge bg-warning text-dark">
                                    <?= htmlspecialchars($request['status']) ?>
                                </span>

                            <?php endif; ?>

                        </td>

                        <td style="white-space:nowrap;">
                            <?= htmlspecialchars($request['created_at']) ?>
                        </td>

                        <td>

                            <a
                                href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                class="btn btn-info btn-sm">
                                View
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>