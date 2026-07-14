<?php

require_once HELPER_PATH . '/auth.php';
require dirname(__DIR__) . '/layouts/header-admin.php';

requireAdminLogin();

require_once CONFIG_PATH . '/database.php';

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$newLeads = $pdo->query("
    SELECT COUNT(*)
    FROM contract_leads
    WHERE status = 'New'
")->fetchColumn();

$contactedLeads = $pdo->query("
    SELECT COUNT(*)
    FROM contract_leads
    WHERE status = 'Contacted'
")->fetchColumn();

$convertedLeads = $pdo->query("
    SELECT COUNT(*)
    FROM contract_leads
    WHERE status = 'Converted'
")->fetchColumn();

$closedLeads = $pdo->query("
    SELECT COUNT(*)
    FROM contract_leads
    WHERE status = 'Closed'
")->fetchColumn();

$totalCustomers = $pdo->query("
    SELECT COUNT(*)
    FROM customers
")->fetchColumn();

$totalRequests = $pdo->query("
    SELECT COUNT(*)
    FROM requests
")->fetchColumn();

$totalServices = $pdo->query("
    SELECT COUNT(*)
    FROM services
")->fetchColumn();

$unreadMessages = $pdo->query("
    SELECT COUNT(*)
    FROM messages
    WHERE status = 'unread'
")->fetchColumn();

$totalPayments = $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
")->fetchColumn();

$totalRevenue = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM payments
")->fetchColumn();

$totalQuoted = $pdo->query("
    SELECT COALESCE(SUM(quoted_price),0)
    FROM requests
")->fetchColumn();

$totalRefunded = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM refunds
")->fetchColumn();

$consultations = $pdo->query("
    SELECT
        c.name,
        cs.slot_date,
        cs.slot_time

    FROM consultation_bookings cb

    JOIN consultation_slots cs
        ON cb.slot_id = cs.id

    JOIN requests r
        ON cb.request_id = r.id

    JOIN customers c
        ON r.customer_id = c.id

    ORDER BY
        cs.slot_date,
        cs.slot_time

    LIMIT 5
")->fetchAll();

$netRevenue = $totalRevenue - $totalRefunded;
$totalRevenue = $totalPayments - $totalRefunded;
$outstandingBalance = $totalQuoted - $totalRevenue;



/*
|--------------------------------------------------------------------------
| Recent Activity
|--------------------------------------------------------------------------
*/

$latestRefund = $pdo->query("
    SELECT
        refunds.*,
        customers.name,
        services.title
    FROM refunds
    JOIN requests
        ON requests.id = refunds.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    ORDER BY refunds.id DESC
    LIMIT 1
")->fetch();

$latestRequest = $pdo->query("
    SELECT
         customers.name,
    services.title,
    requests.status,
    requests.quoted_price
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    ORDER BY requests.id DESC
    LIMIT 1
")->fetch();

$latestPayment = $pdo->query("
    SELECT
         customers.name,
    payments.amount,
    payments.status,
    payments.payment_date
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    ORDER BY payments.id DESC
    LIMIT 1
")->fetch();

$latestMessage = $pdo->query("
    SELECT *
    FROM messages
    ORDER BY created_at DESC
    LIMIT 1
")->fetch();

$servicesScheduled = [];

$awaitingPayment = $pdo->query("
    SELECT COUNT(*)
    FROM requests
    WHERE workflow_stage = 'Proposal Accepted'
")->fetchColumn();

?>

<div class="row mb-4">

    <div class="col-md-3">
        <div class="card border-primary text-center">
            <div class="card-body">
                <h6>Total Customers</h6>
                <h2 class="text-primary"><?= $totalCustomers ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-success text-center">
            <div class="card-body">
                <h6>Total Requests</h6>
                <h2 class="text-success"><?= $totalRequests ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center" style="border-color: var(--bs-orange);">
            <div class="card-body">
                <h6>Total Services</h6>
                <h2 class="text-warning"><?= $totalServices ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-danger text-center">
            <div class="card-body">
                <h6>Unread Messages</h6>
                <h2 class="text-danger"><?= $unreadMessages ?></h2>
            </div>
        </div>
    </div>

</div>

 <div class="row g-4">

    <div class="col-md-3">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body">
                <h4>Total Payments</h4>
                <h1>AED <?= number_format($totalPayments, 2) ?></h1>
            </div>
        </div>
    </div>

    <div class="col-md-3">

        <div class="card bg-danger text-white shadow-sm">

            <div class="card-body">

                <h4>Total Refunded</h4>

                <h1>
                    AED <?= number_format($totalRefunded, 2) ?>
                </h1>

            </div>

        </div>

    </div>

    <div class="col-md-3">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <h4>Net Revenue</h4>
                <h1>AED <?= number_format($netRevenue, 2) ?></h1>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-white shadow-sm" style="background-color: var(--bs-orange);">
            <div class="card-body">
                <h4>Outstanding Balance</h4>
                <h1>AED <?= number_format($outstandingBalance, 2) ?></h1>
            </div>
        </div>
    </div>

 </div>

</div>


    <div class="row mt-4">

      <div class="row justify-content-center mt-4">

    <!-- Upcoming Consultations -->

    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card shadow-sm h-100">

            <div class="card-header">
                Upcoming Consultations
            </div>

            <div class="card-body">

                <?php if (empty($consultations)): ?>

                    <p class="text-muted">
                        No consultations scheduled.
                    </p>

                <?php else: ?>

                    <?php foreach ($consultations as $c): ?>

                        <div class="border-bottom mb-2 pb-2">

                            <strong>
                                <?= htmlspecialchars($c['name']) ?>
                            </strong>

                            <br>

                            <?= date('M d, Y', strtotime($c['slot_date'])) ?>

                            <br>

                            <?= date('h:i A', strtotime($c['slot_time'])) ?>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- Upcoming Services -->

    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card shadow-sm h-100">

            <div class="card-header">
                Upcoming Services
            </div>

            <div class="card-body">

                <?php if (empty($servicesScheduled)): ?>

                    <p class="text-muted">
                        No services scheduled.
                    </p>

                <?php else: ?>

                    <?php foreach ($servicesScheduled as $service): ?>

                        <div class="border-bottom mb-2 pb-2">

                            <strong>
                                <?= htmlspecialchars($service['name']) ?>
                            </strong>

                            <br>

                            <?= date('M d, Y', strtotime($service['service_date'])) ?>

                            <br>

                            <?= date('h:i A', strtotime($service['service_time'])) ?>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- Awaiting Payment -->

    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card shadow-sm h-100">

            <div class="card-header">
                Awaiting Payment
            </div>

            <div class="card-body text-center">

                <h1 class="text-warning">
                    <?= $awaitingPayment ?>
                </h1>

                <p class="mb-0">
                    Requests awaiting payment
                </p>

            </div>

        </div>

    </div>

</div>

<!-- Recent Activity -->

<div class="card mt-4 shadow-sm">

    <div class="card-body">

        <h3 class="text-center mb-4">
            Recent Activity
        </h3>

        <div class="row justify-content-center g-4">

            <!-- Latest Request -->

            <div class="col-lg-3 col-md-6 mb-3">

                <div class="border-start border-4 border-primary rounded p-3 h-100">

                    <h5 class="text-primary">
                        Latest Request
                    </h5>

                    <?php if ($latestRequest): ?>

                        <strong>
                            <?= htmlspecialchars($latestRequest['name']) ?>
                        </strong>

                        <br>

                        <?= htmlspecialchars($latestRequest['title']) ?>

                        <br>

                        <strong class="text-success">
                            AED <?= number_format($latestRequest['quoted_price'], 2) ?>
                        </strong>

                    <?php else: ?>

                        No requests found.

                    <?php endif; ?>

                </div>

            </div>

            <!-- Latest Payment -->

            <div class="col-lg-2 col-md-4">
                

                <div class="border-start border-4 border-success rounded p-3 h-100">

                    <h5 class="text-success">
                        Latest Payment
                    </h5>

                    <?php if ($latestPayment): ?>

                        <strong>
                            <?= htmlspecialchars($latestPayment['name']) ?>
                        </strong>

                        <br>

                        AED <?= number_format($latestPayment['amount'], 2) ?>

                        <br>

                        <?= htmlspecialchars($latestPayment['status']) ?>

                    <?php else: ?>

                        No payments found.

                    <?php endif; ?>

                </div>

            </div>

            <!-- Latest Message -->

            <div class="col-lg-2 col-md-4">

                <div class="border-start border-4 border-danger rounded p-3 h-100">

                    <h5 class="text-danger">
                        Latest Message
                    </h5>

                    <?php if ($latestMessage): ?>

                        <strong>
                            <?= htmlspecialchars($latestMessage['name']) ?>
                        </strong>

                        <br>

                        <?= htmlspecialchars(substr($latestMessage['message'], 0, 60)) ?>...

                    <?php else: ?>

                        No messages found.

                    <?php endif; ?>

                </div>

            </div>

            <!-- Latest Refund -->

            <div class="col-lg-2 col-md-4">

                <div class="border-start border-4 border-warning rounded p-3 h-100">

                    <h5 style="color: orange;">
                        Latest Refund
                    </h5>

                    <?php if ($latestRefund): ?>

                        <strong>
                            <?= htmlspecialchars($latestRefund['name']) ?>
                        </strong>

                        <br>

                        AED <?= number_format($latestRefund['amount'], 2) ?>

                    <?php else: ?>

                        No refunds found.

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>
</div>

<div class="row mt-4">

    <div class="col-md-6 mx-auto">

        <div class="card shadow-sm border-success">

            <div class="card-header bg-success text-white">

                <h5 class="mb-0">
                    🏢 Company Support Leads
                </h5>

            </div>

            <div class="card-body text-center">

                <p class="mb-2">
                    🆕 New:
                    <strong><?= $newLeads ?></strong>
                </p>

                <p class="mb-2">
                    📞 Contacted:
                    <strong><?= $contactedLeads ?></strong>
                </p>

                <p class="mb-2">
                    🤝 Converted:
                    <strong><?= $convertedLeads ?></strong>
                </p>

                <p class="mb-3">
                    📁 Closed:
                    <strong><?= $closedLeads ?></strong>
                </p>

                <a
                    href="?page=contract-leads"
                    class="btn btn-success">

                    View Leads

                </a>

            </div>

        </div>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
