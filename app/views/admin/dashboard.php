<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require dirname(__DIR__) . '/layouts/header-admin.php';
require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/retention_review_helper.php';

/*
|--------------------------------------------------------------------------
| Pending Payments
|--------------------------------------------------------------------------
*/

$pendingPayments = $pdo->query("
    SELECT
        p.id,
        p.request_id,
        p.amount,
        p.status,
        p.payment_date,
        p.created_at,
        c.name AS customer_name
    FROM payments p
    JOIN requests r
        ON r.id = p.request_id
    JOIN customers c
        ON c.id = r.customer_id
    WHERE p.status = 'Pending'
    ORDER BY p.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

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

$netRevenue = $totalRevenue - $totalRefunded;
//$totalRevenue = $totalPayments - $totalRefunded;
//$outstandingBalance = $totalQuoted - $totalRevenue;


$totalRevenue = $totalPayments - $totalRefunded;
$outstandingBalance = max(
    0,
    $totalQuoted - $totalRevenue
);

/*
|--------------------------------------------------------------------------
| Retention Review
|--------------------------------------------------------------------------
*/

$retentionReviewRequests = getRetentionReviewRequests($pdo);
$retentionReviewCount = count($retentionReviewRequests);
$retentionReviewLatest = array_slice(
    $retentionReviewRequests,
    0,
    3
);

/*
|--------------------------------------------------------------------------
| Needs Admin Review
|--------------------------------------------------------------------------
*/

$needsAdminReview = $pdo->query("
    SELECT
        r.id,
        r.created_at,
        c.name AS customer_name,
        s.title AS service_title
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.workflow_stage = 'Needs Admin Review'
    ORDER BY r.id DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Awaiting Reschedule Approval
|--------------------------------------------------------------------------
*/

$awaitingRescheduleApproval = $pdo->query("
    SELECT
        r.id,
        r.pending_reschedule_requested_at,
        c.name AS customer_name,
        s.title AS service_title
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.workflow_stage = 'Awaiting Reschedule Approval'
      AND r.pending_reschedule_slot_id IS NOT NULL
    ORDER BY r.pending_reschedule_requested_at ASC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Agent Assignment Needed
|--------------------------------------------------------------------------
*/

$agentAssignmentNeeded = $pdo->query("
    SELECT
        r.id,
        r.created_at,
        c.name AS customer_name,
        s.title AS service_title
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.workflow_stage = 'Submitted'
      AND r.agent_id IS NULL
    ORDER BY r.id DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Awaiting Customer Response
|--------------------------------------------------------------------------
*/

$awaitingCustomerResponse = $pdo->query("
    SELECT
        r.id,
        r.created_at,
        c.name AS customer_name,
        s.title AS service_title,
        r.workflow_stage
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.workflow_stage IN (
        'Waiting Customer Response',
        'Closure Agreement Sent'
    )
    ORDER BY r.id DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Pending Closure Agreements
|--------------------------------------------------------------------------
*/

$pendingClosureAgreements = $pdo->query("
    SELECT
        ca.id AS agreement_id,
        ca.request_id,
        ca.customer_id,
        ca.typed_name,
        ca.created_at,
        c.name AS customer_name,
        s.title AS service_title
    FROM consultation_closure_agreements ca
    JOIN customers c
        ON c.id = ca.customer_id
    JOIN requests r
        ON r.id = ca.request_id
    JOIN services s
        ON s.id = r.service_id
    WHERE ca.status = 'Pending'
    ORDER BY ca.id DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Refund Requests
|--------------------------------------------------------------------------
*/

$refundRequests = $pdo->query("
    SELECT
        rr.id,
        rr.request_id,
        rr.created_at,
        c.name AS customer_name,
        s.title AS service_title,
        rr.refund_amount
    FROM refund_requests rr
    JOIN requests r
        ON r.id = rr.request_id
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE rr.status = 'Pending'
    ORDER BY rr.id DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Messages Needing Attention
|--------------------------------------------------------------------------
*/

$messagesNeedingAttention = $pdo->query("
    SELECT
        id,
        name,
        email,
        message,
        created_at
    FROM messages
    WHERE status = 'unread'
    ORDER BY created_at DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
.dashboard-layout {
    width: 100vw;
    max-width: none;
    margin-left: calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
    padding-left: 15px;
    padding-right: 15px;
}

.dashboard-action-item {
    transition: background-color 0.15s ease;
}

.dashboard-action-item:hover {
    background-color: #f8f9fa;
}

.container-fluid {
    background: transparent !important;
}

.container {
    background: transparent !important;
}

</style>

<div class="container-fluid mt-4 dashboard-layout">

    <div class="row g-4">

        <!-- LEFT SIDEBAR -->
        <div class="col-lg-2">

            <!-- Financial Summary -->
            <div class="card shadow-sm mb-4">

                <div class="card-header bg-dark text-white">
                    <strong>💰 Financial Summary</strong>
                </div>

                <div class="card-body p-3">

                    <div class="card bg-info text-white shadow-sm mb-3">
                        <div class="card-body">
                            <h5>Total Payments</h5>
                            <h3>
                                AED <?= number_format($totalPayments, 2) ?>
                            </h3>
                        </div>
                    </div>

                    <div class="card bg-danger text-white shadow-sm mb-3">
                        <div class="card-body">
                            <h5>Total Refunded</h5>
                            <h3>
                                AED <?= number_format($totalRefunded, 2) ?>
                            </h3>
                        </div>
                    </div>

                    <div class="card bg-success text-white shadow-sm mb-3">
                        <div class="card-body">
                            <h5>Net Revenue</h5>
                            <h3>
                                AED <?= number_format($netRevenue, 2) ?>
                            </h3>
                        </div>
                    </div>

                    <div class="card shadow-sm"
                         style="background-color: var(--bs-orange); color: white;">
                        <div class="card-body">
                            <h5>Outstanding Balance</h5>
                            <h3>
                                AED <?= number_format($outstandingBalance, 2) ?>
                            </h3>
                        </div>
                    </div>

                </div>

            </div>


            <!-- Company Support Leads -->
            <div class="card shadow-sm border-success">

                <div class="card-header bg-success text-white">
                    <strong>🏢 Company Support Leads</strong>
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


        <!-- RIGHT: ACTIVE DASHBOARD -->
        <div class="col-lg-10">

            <!-- Needs Admin Review -->

                <div class="row g-4">

                    <!-- Needs Admin Review -->
                    <div class="col-lg-6">

                        <div class="card shadow-sm border-danger h-100">

                            <div class="card-header bg-danger text-white">
                                <strong>🔴 Needs Admin Review</strong>
                            </div>

                            <div class="card-body p-0">

                                <?php if (empty($needsAdminReview)): ?>

                                    <div class="p-4 text-muted text-center">
                                        No requests currently need admin review.
                                    </div>

                                <?php else: ?>

                                    <?php foreach ($needsAdminReview as $item): ?>

                                        <a
                                            href="?page=needs-admin-review"
                                            class="text-decoration-none text-dark d-block"
                                        >

                                            <div class="p-3 border-bottom dashboard-action-item">

                                                <div class="fw-bold">
                                                    Request #<?= (int) $item['id'] ?>
                                                </div>

                                                <div>
                                                    <?= htmlspecialchars($item['customer_name']) ?>
                                                </div>

                                                <div class="small text-muted">
                                                    <?= htmlspecialchars($item['service_title']) ?>
                                                </div>

                                            </div>

                                        </a>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>


                    <!-- Awaiting Reschedule Approval -->
<div class="col-lg-6">

    <div class="card shadow-sm border-warning h-100">

        <div class="card-header bg-warning text-dark">
            <strong>🔄 Awaiting Reschedule Approval</strong>
        </div>

        <div class="card-body p-0">

            <?php if (empty($awaitingRescheduleApproval)): ?>

                <div class="p-4 text-muted text-center">
                    No consultation reschedules are awaiting approval.
                </div>

            <?php else: ?>

                <?php foreach ($awaitingRescheduleApproval as $item): ?>

                    <a
                        href="?page=review-reschedule-consultation&id=<?= (int)$item['id'] ?>"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom dashboard-action-item">

                            <div class="fw-bold">
                                Request #<?= (int)$item['id'] ?>
                            </div>

                            <div>
                                <?= htmlspecialchars($item['customer_name']) ?>
                            </div>

                            <div class="small text-muted">
                                <?= htmlspecialchars($item['service_title']) ?>
                            </div>

                            <div class="mt-2">

                                <span class="badge bg-warning text-dark">
                                    Awaiting Approval
                                </span>

                            </div>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>


                        <!-- Agent Assignment Needed -->
<div class="col-lg-6">

    <div class="card shadow-sm border-success h-100">

        <div class="card-header bg-success text-white">
            <strong>👤 Agent Assignment Needed</strong>
        </div>

        <div class="card-body p-0">

            <?php if (empty($agentAssignmentNeeded)): ?>

                <div class="p-4 text-muted text-center">
                    No requests are currently waiting for agent assignment.
                </div>

            <?php else: ?>

                <?php foreach ($agentAssignmentNeeded as $item): ?>

                    <a
                        href="?page=assign-agent&request_id=<?= (int)$item['id'] ?>"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom dashboard-action-item">

                            <div class="fw-bold">
                                Request #<?= (int)$item['id'] ?>
                            </div>

                            <div>
                                <?= htmlspecialchars($item['customer_name']) ?>
                            </div>

                            <div class="small text-muted">
                                <?= htmlspecialchars($item['service_title']) ?>
                            </div>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Awaiting Customer Response -->
<div class="col-lg-6">

    <div class="card shadow-sm border-warning h-100">

        <div class="card-header bg-warning text-dark">
            <strong>🟡 Awaiting Customer Response</strong>
        </div>

        <div class="card-body p-0">

            <?php if (empty($awaitingCustomerResponse)): ?>

                <div class="p-4 text-muted text-center">
                    No requests are currently awaiting customer response.
                </div>

            <?php else: ?>

                <?php foreach ($awaitingCustomerResponse as $item): ?>

                    <a
                        href="?page=view-request&id=<?= (int)$item['id'] ?>"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom dashboard-action-item">

                            <div class="fw-bold">
                                Request #<?= (int)$item['id'] ?>
                            </div>

                            <div>
                                <?= htmlspecialchars($item['customer_name']) ?>
                            </div>

                            <div class="small text-muted">
                                <?= htmlspecialchars($item['service_title']) ?>
                            </div>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Pending Closure Agreements -->
<div class="col-lg-6">

    <div class="card shadow-sm border-danger">

        <div class="card-header bg-danger text-white">
            <strong>📄 Pending Closure Agreements</strong>
        </div>

        <div class="card-body p-0">

            <?php if (empty($pendingClosureAgreements)): ?>

                <div class="p-4 text-muted text-center">
                    No closure agreements are currently pending review.
                </div>

            <?php else: ?>

                <?php foreach ($pendingClosureAgreements as $agreement): ?>

                    <a
                        href="?page=review-closure-agreement&agreement_id=<?= (int)$agreement['agreement_id'] ?>"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom">

                            <div class="fw-bold">
                                Agreement #<?= (int)$agreement['agreement_id'] ?>
                            </div>

                            <div>
                                Request #<?= (int)$agreement['request_id'] ?>
                                —
                                <?= htmlspecialchars($agreement['customer_name']) ?>
                            </div>

                            <div class="small text-muted">
                                <?= htmlspecialchars($agreement['service_title']) ?>
                            </div>

                            <div class="mt-2">
                                <span class="badge bg-danger">
                                    Pending Review
                                </span>
                            </div>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Pending Payments -->
<div class="col-lg-6">

    <div class="card shadow-sm border-info">

        <div class="card-header bg-info text-white">
            <strong>💳 Payment Review</strong>
        </div>

        <div class="card-body p-0">

            <?php if (empty($pendingPayments)): ?>

                <div class="p-4 text-muted text-center">
                    No payments are currently pending review.
                </div>

            <?php else: ?>

                <?php foreach ($pendingPayments as $payment): ?>

                    <a
                        href="?page=payments&payment_id=<?= (int)$payment['id'] ?>"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom">

                            <div class="fw-bold">
                                Payment #<?= (int)$payment['id'] ?>
                            </div>

                            <div>
                                <?= htmlspecialchars($payment['customer_name']) ?>
                            </div>

                            <div class="small text-muted">
                                Request #<?= (int)$payment['request_id'] ?>
                            </div>

                            <div class="mt-2">

                                <span class="badge bg-info text-dark">
                                    AED <?= number_format(
                                        (float)$payment['amount'],
                                        2
                                    ) ?>
                                </span>

                                <span class="badge bg-warning text-dark">
                                    Pending
                                </span>

                            </div>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Refund Requests -->
<div class="col-lg-6">

    <div class="card shadow-sm border-warning">

        <div class="card-header bg-warning text-dark">
            <strong>💰 Refund Requests</strong>
        </div>

        <div class="card-body p-0">

            <?php if (empty($refundRequests)): ?>

                <div class="p-4 text-muted text-center">
                    No refund requests are currently pending review.
                </div>

            <?php else: ?>

                <?php foreach ($refundRequests as $refund): ?>

                    <a
                        href="?page=review-refund&id=<?= (int)$refund['id'] ?>"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom">

                            <div class="fw-bold">
                                Refund #<?= (int)$refund['id'] ?>
                            </div>

                            <div>
                                <?= htmlspecialchars($refund['customer_name']) ?>
                            </div>

                            <div class="small text-muted">
                                <?= htmlspecialchars($refund['service_title']) ?>
                            </div>

                            <div class="mt-2">

                                <span class="badge bg-warning text-dark">
                                    AED <?= number_format(
                                        (float)$refund['refund_amount'],
                                        2
                                    ) ?>
                                </span>

                                <span class="badge bg-danger">
                                    Pending Review
                                </span>

                            </div>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Messages Needing Attention -->
<div class="col-lg-6">

    <div class="card shadow-sm border-danger">

        <div class="card-header bg-danger text-white">
            <strong>✉️ Messages Needing Attention</strong>
        </div>

        <div class="card-body p-0">

            <?php if (empty($messagesNeedingAttention)): ?>

                <div class="p-4 text-muted text-center">
                    No messages currently need attention.
                </div>

            <?php else: ?>

                <?php foreach ($messagesNeedingAttention as $message): ?>

                    <a
                        href="?page=messages"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom dashboard-action-item">

                            <div class="fw-bold">
                                <?= htmlspecialchars($message['name']) ?>
                            </div>

                            <div class="small text-muted">
                                <?= htmlspecialchars($message['email']) ?>
                            </div>

                            <div class="mt-2">
                                <?= htmlspecialchars(
                                    mb_substr($message['message'], 0, 80)
                                ) ?>

                                <?php if (mb_strlen($message['message']) > 80): ?>
                                    ...
                                <?php endif; ?>
                            </div>

                            <div class="mt-2">

                                <span class="badge bg-danger">
                                    Unread
                                </span>

                            </div>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Retention Review -->
<div class="col-lg-6">

    <div class="card shadow-sm border-warning h-100">

        <div class="card-header bg-warning text-dark">
            <strong>📁 Retention Review</strong>

            <?php if ($retentionReviewCount > 0): ?>
                <span class="badge bg-dark float-end">
                    <?= $retentionReviewCount ?> Due
                </span>
            <?php endif; ?>
        </div>

        <div class="card-body p-0">

            <?php if (empty($retentionReviewLatest)): ?>

                <div class="p-4 text-muted text-center">
                    No requests are currently due for retention review.
                </div>

            <?php else: ?>

                <?php foreach ($retentionReviewLatest as $request): ?>

                    <a
                        href="?page=review-retention&id=<?= (int)$request['id'] ?>"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom">

                            <div class="fw-bold">
                                Request #<?= (int)$request['id'] ?>
                            </div>

                            <div>
                                <?= htmlspecialchars(
                                    $request['customer_name']
                                ) ?>
                            </div>

                            <div class="small text-muted">
                                <?= htmlspecialchars(
                                    $request['service_title']
                                ) ?>
                            </div>

                            <?php if (!empty($request['retention_review_at'])): ?>

                                <div class="small text-warning fw-semibold mt-1">
                                    Review Due:
                                    <?= date(
                                        'd M Y',
                                        strtotime(
                                            $request['retention_review_at']
                                        )
                                    ) ?>
                                </div>

                            <?php endif; ?>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <?php if ($retentionReviewCount > 3): ?>

            <div class="card-footer text-center">

                <a
                    href="?page=retention-review"
                    class="text-decoration-none fw-semibold"
                >
                    View all <?= $retentionReviewCount ?> reviews →
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>

</div>
 
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>