<?php require __DIR__ . '/layouts/header.php'; ?>

<?php

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT
        r.*,
        s.title AS service_title
    FROM requests r
    JOIN services s
        ON r.service_id = s.id
    WHERE r.customer_id = ?
    ORDER BY r.id DESC
");

$stmt->execute([$customerId]);

$requests = $stmt->fetchAll();

?>

<h1 class="mb-4">My Requests</h1>

<div class="card">

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Service</th>
                    <th>Quoted Price</th>
                    <th>Status</th>
                    <th>Date</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($requests as $request): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($request['service_title']) ?>
                        </td>

                        <td>
                            $<?= number_format($request['quoted_price'], 2) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['status']) ?>
                        </td>

                        <td>
                            <?= date('M d, Y', strtotime($request['created_at'])) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>