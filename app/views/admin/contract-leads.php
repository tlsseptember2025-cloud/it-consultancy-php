<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$stmt = $pdo->query("
    SELECT *
    FROM contract_leads
    ORDER BY created_at DESC
");

$leads = $stmt->fetchAll();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<h2 class="mb-4">
    Company Support Leads
</h2>

<table class="table table-bordered table-striped">

    <thead>

        <tr>

            <th>Company</th>
            <th>Contact</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Date</th>
            <th>Comments</th>
            <th>Actions</th>

        </tr>

    </thead>

    <tbody>

        <?php foreach ($leads as $lead): ?>

            <tr>

                <td><?= htmlspecialchars($lead['company_name']) ?></td>

                <td><?= htmlspecialchars($lead['contact_person']) ?></td>

                <td>
                    <a href="mailto:<?= htmlspecialchars($lead['email']) ?>">
                        <?= htmlspecialchars($lead['email']) ?>
                    </a>
                </td>

                <td>
                    <a href="tel:<?= htmlspecialchars($lead['phone']) ?>">
                        <?= htmlspecialchars($lead['phone']) ?>
                    </a>
                </td>

                <td>
                    <?php
                        $badge = match ($lead['status']) {
                            'New' => 'bg-primary',
                            'Contacted' => 'bg-info',
                            'Converted' => 'bg-success',
                            'Closed' => 'bg-secondary',
                            default => 'bg-light text-dark'
                        };
                    ?>

<span class="badge <?= $badge ?>">
    <?= htmlspecialchars($lead['status']) ?>
</span>
                </td>

                <td><?= formatDateTime($lead['created_at']) ?></td>

                <td><?= nl2br(htmlspecialchars($lead['comments'])) ?></td>

                <td>

                    <a
                        href="?page=edit-contract-lead&id=<?= $lead['id'] ?>"
                        class="btn btn-warning btn-sm">

                        Edit

                    </a>

                    <a
                        href="?page=delete-contract-lead&id=<?= $lead['id'] ?>"
                        class="btn btn-danger btn-sm"
                        onclick="return confirm('Delete this lead?');">

                        Delete

                    </a>

                </td>

            </tr>

        <?php endforeach; ?>

    </tbody>

</table>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>