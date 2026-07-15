<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';
require_once CONFIG_PATH . '/database.php';

$stmt = $pdo->query("
    SELECT * FROM customers
    ORDER BY created_at DESC
");

$customers = $stmt->fetchAll();

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2 class="mb-0">
        Customers
    </h2>

    <a href="?page=add-customer"
       class="btn btn-primary">

        Add Customer

    </a>

</div>

<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Company</th>
                <th>Action</th>

            </tr>

        </thead>

        <tbody>

            <?php foreach ($customers as $customer): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($customer['name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($customer['email']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($customer['phone']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($customer['company']) ?>
                    </td>

                    <td>

                        <a
                            href="?page=view-customer&id=<?= $customer['id'] ?>"
                            class="btn btn-sm btn-info">

                            View

                        </a>

                        <a
                            href="?page=edit-customer&id=<?= $customer['id'] ?>"
                            class="btn btn-sm btn-warning">

                            Edit

                        </a>

                        <a
                            href="?page=delete-customer&id=<?= $customer['id'] ?>"
                            class="btn btn-sm btn-danger"
                            onclick="return confirm('Delete customer?')">

                            Delete

                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>