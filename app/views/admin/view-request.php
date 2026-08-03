<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/request-events.php';
require_once CONFIG_PATH . '/request-event-display.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT
        requests.*,
        customers.name AS customer_name,
        customers.email,
        customers.phone,
        customers.company,
        services.title AS service_title
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    WHERE requests.id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {
    die('Request not found');
}

/*
|--------------------------------------------------------------------------
| Request Timeline
|--------------------------------------------------------------------------
*/

$events = RequestEventHelper::get(
    $pdo,
    (int)$request['id']
);

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Request Details
        </h2>

        <p><strong>Customer:</strong> <?= htmlspecialchars($request['customer_name']) ?></p>

        <p><strong>Email:</strong> <?= htmlspecialchars($request['email']) ?></p>

        <p><strong>Phone:</strong> <?= htmlspecialchars($request['phone']) ?></p>

        <p><strong>Company:</strong> <?= htmlspecialchars($request['company']) ?></p>

        <hr>

        <p><strong>Service:</strong> <?= htmlspecialchars($request['service_title']) ?></p>

        <p><strong>Quoted Price:</strong> $<?= number_format($request['quoted_price'], 2) ?></p>

        <p><strong>Status:</strong> <?= htmlspecialchars($request['status']) ?></p>

        <p><strong>Description:</strong></p>

        <div class="border rounded p-3 mb-3">

            <?= nl2br(htmlspecialchars($request['description'])) ?>

        </div>

        <p><strong>Date:</strong> <?= formatDateTime($request['created_at']) ?>

        <hr>

<div class="card shadow-sm mt-4">

    <div class="card-header bg-dark text-white">

        Request Timeline

    </div>

    <div class="card-body">

        <?php if (empty($events)): ?>

            <p class="text-muted mb-0">

                No events have been recorded for this request.

            </p>

        <?php else: ?>

            <?php foreach ($events as $event): ?>

                <div class="border-bottom pb-3 mb-3">

                    <div class="d-flex justify-content-between">

                        <strong>

                            <?php

$display = $requestEventDisplay[$event['event_code']] ?? [
    'title' => 'Unknown Event',
    'icon'  => '📝',
    'badge' => 'secondary'
];

?>

<span class="badge bg-<?= $display['badge'] ?> me-2">

    <?= $display['icon'] ?>

</span>

<strong>

    <?= htmlspecialchars($display['title']) ?>

</strong>

                        </strong>

                        <small class="text-muted">

                            <?= formatDateTime($event['created_at']) ?>

                        </small>

                    </div>

                    <div class="text-muted small mt-2">

                        <?= htmlspecialchars($event['event_source']) ?>

                    </div>
                    
                    <?php if (!empty($event['event_description'])): ?>

                        <div class="mt-2">

                            <?= nl2br(htmlspecialchars($event['event_description'])) ?>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

        <a
            href="?page=requests"
            class="btn btn-secondary">

            Back

        </a>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>