<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);

// Demo Super Admin has its own dashboard and navbar.
// It must never use this normal Admin / Demo Admin dashboard.
if (isset($_SESSION['demo_super_admin'])) {
    die('Demo Super Admin must use the Demo Super Admin dashboard.');
}

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $adminPdo = $demoPdo;
} else {
    require_once CONFIG_PATH . '/database.php';
    require_once CONFIG_PATH . '/demo-database.php';
    $adminPdo = $pdo;
}

$demoTenantId = null;

if ($isDemoAdmin) {
    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        die('Invalid Demo tenant.');
    }
}

/*
|--------------------------------------------------------------------------
| Demo Extension Status
|--------------------------------------------------------------------------
|
| Demo rules:
| - Original Demo period = 5 days.
| - Extension can be requested only during the final 24 hours.
| - One extension only.
| - Approved extension adds 5 days after the original expiry.
| - Maximum total lifetime = 10 days from started_at.
|
*/
$demoExtensionRequest = null;
$demoTenantStarted = null;
$demoTenantExpiry = null;
$demoExtensionCanRequest = false;
$demoExtensionNotice = null;
$demoExtensionNoticeClass = 'alert-info';

if ($isDemoAdmin) {
    try {
        $tenantStatusStmt = $adminPdo->prepare("
            SELECT started_at, expires_at, credentials_resend_used
            FROM demo_tenants
            WHERE id = ?
            LIMIT 1
        ");
        $tenantStatusStmt->execute([$demoTenantId]);
        $demoTenant = $tenantStatusStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $demoTenantStarted = $demoTenant['started_at'] ?? null;
        $demoTenantExpiry = $demoTenant['expires_at'] ?? null;
        $demoCredentialsResendUsed =
            (int) ($demoTenant['credentials_resend_used'] ?? 0);

        $demoSetupStatusStmt = $adminPdo->prepare("
            SELECT
                (
                    SELECT COUNT(*)
                    FROM customers
                    WHERE demo_tenant_id = ?
                      AND is_demo_account = 1
                      AND password IS NOT NULL
                      AND email IS NOT NULL
                ) AS customer_count,
                (
                    SELECT COUNT(*)
                    FROM agents
                    WHERE demo_tenant_id = ?
                      AND is_demo_account = 1
                      AND username LIKE CONCAT(?, '_agent%')
                      AND password IS NOT NULL
                      AND email IS NOT NULL
                ) AS agent_count
        ");
        $demoSetupStatusStmt->execute([
            $demoTenantId,
            $demoTenantId,
            preg_replace('/_admin$/', '', (string) ($_SESSION['demo_user']['username'] ?? ''))
        ]);
        $demoSetupStatus =
            $demoSetupStatusStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $demoSetupComplete =
            (int) ($demoSetupStatus['customer_count'] ?? 0) >= 1
            && (int) ($demoSetupStatus['agent_count'] ?? 0) >= 2;

        $demoCanResendCredentials =
            $demoSetupComplete && $demoCredentialsResendUsed === 0;

        $extensionTableStmt = $adminPdo->query(
            "SHOW TABLES LIKE 'demo_extension_requests'"
        );

        if ($extensionTableStmt && $extensionTableStmt->fetchColumn()) {
            $extensionStmt = $adminPdo->prepare("
                SELECT
                    status,
                    requested_at,
                    reviewed_at,
                    review_notes
                FROM demo_extension_requests
                WHERE demo_tenant_id = ?
                LIMIT 1
            ");
            $extensionStmt->execute([$demoTenantId]);
            $demoExtensionRequest =
                $extensionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $now = time();
        $expiryTimestamp = $demoTenantExpiry
            ? strtotime((string) $demoTenantExpiry)
            : false;

        if ($expiryTimestamp !== false) {
            $secondsRemaining = $expiryTimestamp - $now;

            // If the extension has already been approved, the current
            // expiry is Day 10. Day 6 starts exactly 5 days before it.
            if (
                $demoExtensionRequest &&
                ($demoExtensionRequest['status'] ?? '') === 'Approved'
            ) {
                $extensionPeriodStart = $expiryTimestamp - (5 * 86400);
                $daySixEnd = $extensionPeriodStart + 86400;

                if ($now >= $extensionPeriodStart && $now < $daySixEnd) {
                    $demoExtensionNotice =
                        'Demo extended for another 5 days. There will be no more extension periods.';
                    $demoExtensionNoticeClass = 'alert-success';
                }
            } elseif ($secondsRemaining > 0) {

                // Final 24 hours = Day 5.
                if ($secondsRemaining <= 86400) {
                    $demoExtensionCanRequest = true;
                    $demoExtensionNotice =
                        'This is your last day. You can request a 5-day extension.';
                    $demoExtensionNoticeClass = 'alert-warning';

                // More than 24 hours but no more than 48 hours = Day 4.
                } elseif ($secondsRemaining <= 172800) {
                    $demoExtensionNotice =
                        'You have 2 days left. You can request a 5-day extension on your last day.';
                    $demoExtensionNoticeClass = 'alert-warning';
                }
            }
        }

        // Never show the request button if a request already exists.
        if ($demoExtensionRequest) {
            $demoExtensionCanRequest = false;
        }

    } catch (Throwable $e) {
        error_log(
            'Demo extension dashboard lookup failed: ' .
            $e->getMessage()
        );
    }
}

require_once APP_PATH . '/helpers/retention_review_helper.php';
require dirname(__DIR__) . '/layouts/header-admin.php';



/*
 * Active Guest Chats — Main Admin only.
 * Guest Chat is not part of the Demo tenant workflow.
 */
$activeGuestChatCount = 0;

if (!$isDemoAdmin) {
    try {
        $guestChatCountStmt = $adminPdo->query("
            SELECT COUNT(*)
            FROM guest_chat_conversations
            WHERE status = 'Open'
        ");

        $activeGuestChatCount = (int) $guestChatCountStmt->fetchColumn();
    } catch (Throwable $e) {
        error_log(
            'Dashboard active Guest Chat count failed: ' . $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Pending Payments
|--------------------------------------------------------------------------
*/

$pendingPayments = [];

if (isset($_SESSION['demo_user'])) {

    $stmt = $adminPdo->prepare("
        SELECT
            ps.id,
            ps.request_id,
            ps.status,
            ps.uploaded_at,
            c.name AS customer_name,
            s.title AS service_title,
            r.quoted_price
        FROM payment_slips ps
        JOIN requests r
            ON r.id = ps.request_id
        JOIN customers c
            ON c.id = ps.customer_id
        JOIN services s
            ON s.id = r.service_id
        WHERE ps.status = 'Pending'
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
        ORDER BY ps.uploaded_at ASC
    ");

    $stmt->execute([
        (int) $_SESSION['demo_user']['demo_tenant_id'],
        (int) $_SESSION['demo_user']['demo_tenant_id']
    ]);

    $pendingPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $pendingPayments = $adminPdo->query("
        SELECT
            ps.id,
            ps.request_id,
            ps.status,
            ps.uploaded_at,
            c.name AS customer_name,
            s.title AS service_title,
            r.quoted_price
        FROM payment_slips ps
        JOIN requests r
            ON r.id = ps.request_id
        JOIN customers c
            ON c.id = ps.customer_id
        JOIN services s
            ON s.id = r.service_id
        WHERE ps.status = 'Pending'
        ORDER BY ps.uploaded_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

}

$newLeads = 0;
$contactedLeads = 0;
$convertedLeads = 0;
$closedLeads = 0;
$archivedLeads = 0;
$pendingLeads = 0;

/*
 * Company support leads are a Main Admin workflow. Demo Admins do not
 * receive or manage these records, so the metrics remain zero for Demo.
 */
if (!$isDemoAdmin) {
    $newLeads = $adminPdo->query("
        SELECT COUNT(*)
        FROM contract_leads
        WHERE status = 'New'
    " )->fetchColumn();

    $contactedLeads = $adminPdo->query("
        SELECT COUNT(*)
        FROM contract_leads
        WHERE status = 'Contacted'
    " )->fetchColumn();

    $convertedLeads = $adminPdo->query("
        SELECT COUNT(*)
        FROM contract_leads
        WHERE status = 'Converted'
    " )->fetchColumn();

    $closedLeads = $adminPdo->query("
        SELECT COUNT(*)
        FROM contract_leads
        WHERE status = 'Closed'
    " )->fetchColumn();

    $archivedLeads = $adminPdo->query("
        SELECT COUNT(*)
        FROM contract_leads
        WHERE status = 'Archived'
    " )->fetchColumn();

    $pendingLeads = $adminPdo->query("
        SELECT COUNT(*)
        FROM contract_leads
        WHERE approval_status = 'Pending'
    " )->fetchColumn();
}

/*
 * Financial totals are tenant-scoped for Demo Admins. Main Admin sees
 * the Main database. Demo Super Admin uses its own dashboard.
 */
if ($isDemoAdmin) {

    $totalPaymentsStmt = $adminPdo->prepare("
        SELECT COALESCE(SUM(p.amount), 0)
        FROM payments p
        INNER JOIN requests r ON r.id = p.request_id
        INNER JOIN customers c ON c.id = r.customer_id
        WHERE c.demo_tenant_id = ?
          AND c.is_demo_account = 1
    " );
    $totalPaymentsStmt->execute([$demoTenantId]);
    $totalPayments = $totalPaymentsStmt->fetchColumn();

    $totalQuotedStmt = $adminPdo->prepare("
        SELECT COALESCE(SUM(r.quoted_price), 0)
        FROM requests r
        INNER JOIN customers c ON c.id = r.customer_id
        WHERE c.demo_tenant_id = ?
          AND c.is_demo_account = 1
    " );
    $totalQuotedStmt->execute([$demoTenantId]);
    $totalQuoted = $totalQuotedStmt->fetchColumn();

    $totalRefundedStmt = $adminPdo->prepare("
        SELECT COALESCE(SUM(rr.amount), 0)
        FROM refunds rr
        INNER JOIN requests r ON r.id = rr.request_id
        INNER JOIN customers c ON c.id = r.customer_id
        WHERE c.demo_tenant_id = ?
          AND c.is_demo_account = 1
    " );
    $totalRefundedStmt->execute([$demoTenantId]);
    $totalRefunded = $totalRefundedStmt->fetchColumn();

} else {

    $totalPayments = $adminPdo->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM payments
    " )->fetchColumn();

    $totalQuoted = $adminPdo->query("
        SELECT COALESCE(SUM(quoted_price), 0)
        FROM requests
    " )->fetchColumn();

    $totalRefunded = $adminPdo->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM refunds
    " )->fetchColumn();
}

$totalRevenue = $totalPayments;

/*
|--------------------------------------------------------------------------
| Dashboard Customer Scope
|--------------------------------------------------------------------------
| Demo Admin sees only the current tenant. Main Admin sees the Main
| database without a Demo restriction. Demo Super Admin is handled by
| its own dashboard and never reaches this file.
|--------------------------------------------------------------------------
*/

$dashboardCustomerScope = '';
$dashboardCustomerParams = [];

if ($isDemoAdmin) {
    $dashboardCustomerScope = "
        AND c.demo_tenant_id = :dashboard_demo_tenant_id
        AND c.is_demo_account = 1
        AND s.demo_tenant_id = :dashboard_demo_tenant_id
    ";
    $dashboardCustomerParams['dashboard_demo_tenant_id'] = $demoTenantId;
}

$fetchDashboardRows = static function (
    PDO $pdo,
    string $sql,
    array $params = []
): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};


/*
|--------------------------------------------------------------------------
| Demo Requests
|--------------------------------------------------------------------------
|
| Demo provisioning requests belong to the Main/Dev environment.
| They are not part of the Demo tenant database.
|
| Therefore the Demo Dashboard does not query demo_requests.
|
*/

$demoRequestsActionCount = 0;

/*
|--------------------------------------------------------------------------
| Demo Password Recovery Requests
|--------------------------------------------------------------------------
| Main Admin only.
| Recovery requests are stored in the Demo database.
|--------------------------------------------------------------------------
*/

$demoPasswordRecoveryCount = 0;

if (!isset($_SESSION['demo_user'])) {
    $stmt = $demoPdo->query("
        SELECT COUNT(*)
        FROM demo_password_recovery_requests
        WHERE status = 'Pending'
    ");

    $demoPasswordRecoveryCount = (int) $stmt->fetchColumn();
}

if (!isset($_SESSION['demo_user'])) {
    $demoRequestsActionCount = $pdo->query("
        SELECT COUNT(*)
        FROM demo_requests
        WHERE status IN ('Confirmed', 'Customer Confirmed')
    ")->fetchColumn();
}

$netRevenue = (float) $totalPayments - (float) $totalRefunded;
$outstandingBalance = max(
    0,
    (float) $totalQuoted - (float) $totalPayments
);

/*
|--------------------------------------------------------------------------
| Retention Review
|--------------------------------------------------------------------------
*/

$retentionReviewRequests = getRetentionReviewRequests($adminPdo);
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

$needsAdminReview = $fetchDashboardRows(
    $adminPdo,
    "
    SELECT
        r.id,
        r.created_at,
        r.review_type,
        r.workflow_stage,
        r.missed_consultation_reason,
        c.name AS customer_name,
        s.title AS service_title

    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.workflow_stage = 'Needs Admin Review'
    $dashboardCustomerScope
    ORDER BY r.id DESC
    LIMIT 3
",
    $dashboardCustomerParams
);

/*
|--------------------------------------------------------------------------
| Awaiting Reschedule Approval
|--------------------------------------------------------------------------
*/

$awaitingRescheduleApproval = $fetchDashboardRows(
    $adminPdo,
    "
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
    $dashboardCustomerScope
    ORDER BY r.pending_reschedule_requested_at ASC
    LIMIT 3
",
    $dashboardCustomerParams
);

/*
|--------------------------------------------------------------------------
| Upcoming Schedule
|--------------------------------------------------------------------------
| Shows future confirmed consultations and scheduled services.
|--------------------------------------------------------------------------
*/

$upcomingSchedule = [];

$upcomingParams = $dashboardCustomerParams;
$upcomingCustomerScope = $dashboardCustomerScope;

if ($isDemoAdmin) {
    $upcomingCustomerScope = str_replace(
        ':dashboard_demo_tenant_id',
        ':dashboard_demo_tenant_id_2',
        $upcomingCustomerScope
    );
    $upcomingParams['dashboard_demo_tenant_id_2'] = $demoTenantId;
}

$stmt = $adminPdo->prepare("
    SELECT
        r.id AS request_id,
        c.name AS customer_name,
        s.title AS service_title,
        'Consultation' AS schedule_type,
        cs.slot_date AS schedule_date,
        cs.slot_time AS schedule_time

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    JOIN consultation_bookings cb
        ON cb.request_id = r.id

    JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE r.workflow_stage IN (
    'Consultation Scheduled',
    'Consultation Confirmed'
)
AND TIMESTAMP(cs.slot_date, cs.slot_time) >= NOW()
    $upcomingCustomerScope


    UNION ALL


    SELECT
        r.id AS request_id,
        c.name AS customer_name,
        s.title AS service_title,
        'Service' AS schedule_type,
        ss.service_date AS schedule_date,
        ss.service_time AS schedule_time

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    JOIN service_bookings sb
        ON sb.request_id = r.id

    JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE r.workflow_stage = 'Service Scheduled'
      AND TIMESTAMP(ss.service_date, ss.service_time) >= NOW()
    $dashboardCustomerScope


    ORDER BY schedule_date ASC, schedule_time ASC
");

$stmt->execute($upcomingParams);

$upcomingSchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Agent Assignment Needed
|--------------------------------------------------------------------------
*/

$agentAssignmentNeeded = $fetchDashboardRows(
    $adminPdo,
    "
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
    $dashboardCustomerScope
    ORDER BY r.id DESC
    LIMIT 3
",
    $dashboardCustomerParams
);

/*
|--------------------------------------------------------------------------
| Awaiting Customer Response
|--------------------------------------------------------------------------
*/

$awaitingCustomerResponse = $fetchDashboardRows(
    $adminPdo,
    "
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
    $dashboardCustomerScope
    ORDER BY r.id DESC
    LIMIT 3
",
    $dashboardCustomerParams
);

/*
|--------------------------------------------------------------------------
| Pending Closure Agreements
|--------------------------------------------------------------------------
*/

$pendingClosureAgreements = $fetchDashboardRows(
    $adminPdo,
    "
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
    $dashboardCustomerScope
    ORDER BY ca.id DESC
    LIMIT 3
",
    $dashboardCustomerParams
);

/*
|--------------------------------------------------------------------------
| Refund Requests
|--------------------------------------------------------------------------
*/

$refundRequests = $fetchDashboardRows(
    $adminPdo,
    "
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
    $dashboardCustomerScope
    ORDER BY rr.id DESC
    LIMIT 3
",
    $dashboardCustomerParams
);

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

<?php if (!empty($_SESSION['admin_password_change_success'])): ?>

    <div class="alert alert-success mx-3 mt-3">
        <?= htmlspecialchars($_SESSION['admin_password_change_success']) ?>
    </div>

    <?php unset($_SESSION['admin_password_change_success']); ?>

<?php endif; ?>

<?php if (isset($_SESSION['demo_user'])): ?>

    <?php
        $demoAdminName = trim((string) (
            $_SESSION['demo_user']['username']
            ?? $_SESSION['demo_user']['name']
            ?? $_SESSION['demo_user']['email']
            ?? 'Demo Administrator'
        ));

        if ($demoAdminName === '') {
            $demoAdminName = 'Demo Administrator';
        }
    ?>

    <div class="container-fluid px-3 pt-3">
        <div class="card shadow-sm border-primary">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="text-muted small mb-1">
                            Demo Administration Portal
                        </div>

                        <h3 class="mb-1">
                            Welcome, <?= htmlspecialchars($demoAdminName) ?>
                        </h3>

                        <p class="mb-0 text-muted">
                            Manage demo requests, consultations, services, customers, and administrative workflows.
                        </p>
                    </div>

                    <span class="badge bg-primary px-3 py-2">
                        Demo Environment
                    </span>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php if (isset($_SESSION['demo_user'])): ?>

    <div class="container-fluid px-3 mt-3">
        <div class="card shadow-sm border-warning">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                    <div class="flex-grow-1">
                        <div class="small text-muted">Demo Period</div>

                        <div class="fw-semibold">
    <?php if ($demoTenantExpiry): ?>
        <?php
            try {
                $expiryDisplayDate = new DateTimeImmutable(
                    (string) $demoTenantExpiry,
                    new DateTimeZone('Asia/Dubai')
                );

                $expiryDisplay = $expiryDisplayDate->format(
                    'd/m/Y h:i A'
                );
            } catch (Exception $e) {
                $expiryDisplay = (string) $demoTenantExpiry;
            }
        ?>

        Expires <?= htmlspecialchars(
            $expiryDisplay,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    <?php else: ?>
        Expiry date unavailable
    <?php endif; ?>
</div>

                        <?php if ($demoExtensionRequest): ?>
                            <?php
                                $extensionStatus = (string) (
                                    $demoExtensionRequest['status'] ?? 'Pending'
                                );

                                $extensionBadge = match ($extensionStatus) {
                                    'Approved' => 'bg-success',
                                    'Rejected' => 'bg-danger',
                                    default => 'bg-warning text-dark',
                                };
                            ?>

                            <div class="small mt-1">
                                Extension request:
                                <span class="badge <?= $extensionBadge ?>">
                                    <?= htmlspecialchars(
                                        $extensionStatus,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($demoExtensionNotice): ?>
                            <div class="alert <?= $demoExtensionNoticeClass ?> small mt-2 mb-0 py-2">
                                <?= htmlspecialchars(
                                    $demoExtensionNotice,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2">

                        <?php if ($demoCanResendCredentials): ?>
                            <a
                                href="?page=demo-setup"
                                class="btn btn-outline-primary"
                            >
                                <i class="bi bi-people me-1"></i>
                                Resend Demo Credentials
                            </a>
                        <?php endif; ?>

                        <?php if ($demoExtensionCanRequest): ?>
                            <a
                                href="?page=demo-extension-request"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-calendar-plus me-1"></i>
                                Request 5-Day Extension
                            </a>

                        <?php elseif ($demoExtensionRequest): ?>
                            <a
                                href="?page=demo-extension-request"
                                class="btn btn-outline-secondary"
                            >
                                View Extension Request
                            </a>
                        <?php endif; ?>

                    </div>

                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php if ($isDemoAdmin): ?>

<div class="container-fluid mt-4">

    <div class="row justify-content-center">

        <div class="col-12 col-xl-10">

            <div class="card shadow-sm mb-4">

                <div class="card-header bg-dark text-white">
                    <strong>💰 Financial Summary</strong>
                </div>

                <div class="card-body">

                    <div class="row g-3">

                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="card bg-info text-white shadow-sm h-100">
                                <div class="card-body">
                                    <h5>Total Payments</h5>
                                    <h3>
                                        AED <?= number_format($totalPayments, 2) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="card bg-danger text-white shadow-sm h-100">
                                <div class="card-body">
                                    <h5>Total Refunded</h5>
                                    <h3>
                                        AED <?= number_format($totalRefunded, 2) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="card bg-success text-white shadow-sm h-100">
                                <div class="card-body">
                                    <h5>Net Revenue</h5>
                                    <h3>
                                        AED <?= number_format($netRevenue, 2) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <div
                                class="card shadow-sm h-100"
                                style="background-color: var(--bs-orange); color: white;"
                            >
                                <div class="card-body">
                                    <h5>Outstanding Balance</h5>
                                    <h3>
                                        AED <?= number_format($outstandingBalance, 2) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php endif; ?>

<div class="container-fluid mt-4 dashboard-layout">

    <div class="row g-4">

        <!-- =========================================================
             LEFT SIDEBAR
             ========================================================= -->
        <?php if (!$isDemoAdmin): ?>
        <div class="col-lg-2">

            

<!-- Active Guest Chats -->
<div
    id="activeGuestChatsCard"
    class="card shadow-sm border-primary mb-4"
>
    <div class="card-header bg-primary text-white">
        <strong>💬 Active Guest Chats</strong>

        <span
            id="activeGuestChatCount"
            class="badge <?= $activeGuestChatCount > 0
                ? 'bg-warning text-dark'
                : 'bg-light text-primary' ?> float-end"
            aria-live="polite"
        >
            <?= (int) $activeGuestChatCount ?>
        </span>
    </div>

    <div class="card-body text-center p-3">
        <p id="activeGuestChatMessage" class="mb-3">
            <?php if ($activeGuestChatCount > 0): ?>
                <?= (int) $activeGuestChatCount ?>
                open conversation<?= $activeGuestChatCount === 1 ? '' : 's' ?>
                may need attention.
            <?php else: ?>
                <span class="text-muted">No active Guest Chats.</span>
            <?php endif; ?>
        </p>

        <a href="?page=guest-chats" class="btn btn-sm btn-primary">
            View Guest Chats
        </a>
    </div>
</div>



<script>
(function () {
    const card = document.getElementById('activeGuestChatsCard');
    const countBadge = document.getElementById('activeGuestChatCount');
    const message = document.getElementById('activeGuestChatMessage');

    if (!card || !countBadge || !message) {
        return;
    }

    async function refreshActiveGuestChats() {
        try {
            const response = await fetch(
                '?page=admin-active-guest-chat-count',
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const count = Number(data.count);

            if (!Number.isSafeInteger(count) || count < 0) {
                return;
            }

            countBadge.textContent = String(count);
            countBadge.className = count > 0
                ? 'badge bg-warning text-dark float-end'
                : 'badge bg-light text-primary float-end';

            message.textContent = count === 0
                ? 'No active Guest Chats.'
                : count + ' open conversation'
                    + (count === 1 ? '' : 's')
                    + ' may need attention.';

            card.classList.toggle('border-danger', count > 0);
            card.classList.toggle('border-primary', count === 0);

            const header = card.querySelector('.card-header');
            if (header) {
                header.classList.toggle('bg-danger', count > 0);
                header.classList.toggle('bg-primary', count === 0);
            }
        } catch (error) {
            // Keep the last successful count if a refresh fails.
        }
    }

    refreshActiveGuestChats();
    window.setInterval(refreshActiveGuestChats, 30000);
})();
</script>

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

                    <div
                        class="card shadow-sm"
                        style="background-color: var(--bs-orange); color: white;"
                    >
                        <div class="card-body">
                            <h5>Outstanding Balance</h5>
                            <h3>
                                AED <?= number_format($outstandingBalance, 2) ?>
                            </h3>
                        </div>
                    </div>

                </div>

            </div>

        </div>


        <?php endif; ?>


        <!-- =========================================================
            MIDDLE - EXISTING DASHBOARD
            ========================================================= -->
        <div class="<?= $isDemoAdmin ? 'col-lg-8 mx-auto' : 'col-lg-8' ?>">

            <div class="row g-4">

       
        <!-- Upcoming Schedule -->
        <div class="col-lg-6">

            <div class="card shadow-sm border-primary h-100">

                <div class="card-header bg-primary text-white">
                    <strong>📅 Upcoming Schedule</strong>

                    <?php if (!empty($upcomingSchedule)): ?>

                        <span class="badge bg-light text-primary float-end">
                            <?= count($upcomingSchedule) ?>
                        </span>

                    <?php endif; ?>

                </div>

                <div class="card-body p-0">

                   <?php if (empty($upcomingSchedule)): ?>

    <div class="p-4 text-muted text-center">
        No upcoming consultations or services are currently scheduled.
    </div>

<?php else: ?>

    <?php foreach ($upcomingSchedule as $item): ?>

        <a
            href="<?=
                $item['schedule_type'] === 'Consultation'
                    ? '?page=approve-consultation&id=' . (int)$item['request_id']
                    : '?page=approve-service-schedule&id=' . (int)$item['request_id']
            ?>"
            class="text-decoration-none text-dark d-block"
        >

            <div class="p-3 border-bottom dashboard-action-item">

                <div class="fw-bold">
                    Request #<?= (int)$item['request_id'] ?>
                </div>

                <div>
                    <?= htmlspecialchars($item['customer_name']) ?>
                </div>

                <div class="small text-muted">
                    <?= htmlspecialchars($item['service_title']) ?>
                </div>

                <div class="mt-2">

                    <?php if ($item['schedule_type'] === 'Consultation'): ?>

                        <span class="badge bg-primary">
                            📞 Consultation
                        </span>

                    <?php else: ?>

                        <span class="badge bg-success">
                            🛠️ Service
                        </span>

                    <?php endif; ?>

                </div>

                <div class="small fw-semibold mt-2">

                    <?= date(
                        'd M Y',
                        strtotime($item['schedule_date'])
                    ) ?>

                    at

                    <?= date(
                        'h:i A',
                        strtotime($item['schedule_time'])
                    ) ?>

                </div>

            </div>

        </a>

    <?php endforeach; ?>

<?php endif; ?>

                </div>

            </div>

        </div>

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

    <?php

if ($item['review_type'] === 'consultation_overdue') {

    $reviewLabel = 'Consultation Overdue';

    $reviewDescription =
        'Consultation exceeded the scheduled one-hour session.';

} elseif ($item['review_type'] === 'service_missed') {

    $reviewLabel = 'Missed Service';

    $reviewDescription =
        'Service was not started within the one-hour start window.';

} elseif ($item['review_type'] === 'service_overdue') {

    $reviewLabel = 'Service Overdue';

    $reviewDescription =
        'Service remained In Progress after the scheduled one-hour session.';

} elseif ($item['review_type'] === 'customer_contact') {

    $reviewLabel = 'Customer Contact Review';

    $reviewDescription =
        'Customer contact requires administrator action.';

} else {

    $reviewLabel = 'Consultation Review';

    $reviewDescription =
        'Consultation requires administrator review.';
}




?>

    <a

    href="<?=

    (
        $item['workflow_stage'] === 'Needs Admin Review'
        && !empty($item['missed_consultation_reason'])
    )

        ? '?page=review-missed-consultation&id=' . (int)$item['id']

        : (

            in_array(
                $item['review_type'],
                ['service_missed', 'service_overdue'],
                true
            )

                ? '?page=admin-review-service-job&id=' . (int)$item['id']

                : '?page=admin-review-consultation&id=' . (int)$item['id']
        )
?>"
    
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

                <span class="badge bg-danger">
                    <?= htmlspecialchars($reviewLabel) ?>
                </span>

            </div>

            <div class="small text-muted mt-1">

                <?= htmlspecialchars($reviewDescription) ?>

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
                        href="?page=admin-assign-agent&id=<?= (int)$item['id'] ?>"
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
                        href="?page=view-awaiting-customer-response&id=<?= (int)$item['id'] ?>"
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
                        href="?page=view-slip&id=<?= (int)$payment['id'] ?>"
                        class="text-decoration-none text-dark d-block"
                    >

                        <div class="p-3 border-bottom dashboard-action-item">

    <div class="fw-bold">
        Request #<?= (int)$payment['request_id'] ?>
    </div>

    <div>
        <?= htmlspecialchars($payment['customer_name']) ?>
    </div>

    <div class="small text-muted">
        <?= htmlspecialchars($payment['service_title']) ?>
    </div>

    <div class="small mt-2">
        <strong>Quoted Price:</strong>
        AED <?= number_format($payment['quoted_price'], 2) ?>
    </div>

    <div class="small">
        <strong>Payment Submitted:</strong>
        AED <?= number_format($payment['quoted_price'], 2) ?>
    </div>

    <div class="mt-2">

        <span class="badge bg-warning text-dark">
            <?= htmlspecialchars($payment['status']) ?>
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
<!-- End middle dashboard row -->

</div>
<!-- End middle dashboard column -->

<!-- =========================================================
     RIGHT SIDEBAR
     ========================================================= -->
<?php if (!$isDemoAdmin): ?>
<div class="col-lg-2">

    <!-- Demo Requests -->
    <?php if (!isset($_SESSION['demo_user'])): ?>

        <div class="card shadow-sm border-primary mb-4">

            <div class="card-header bg-primary text-white">
                <strong>🖥️ Demo Requests</strong>

                <?php if ($demoRequestsActionCount > 0): ?>
                    <span class="badge bg-light text-primary float-end">
                        <?= (int) $demoRequestsActionCount ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="card-body text-center p-3">

                <?php if ($demoRequestsActionCount > 0): ?>

                    <div class="mb-2">
                        <strong><?= (int) $demoRequestsActionCount ?></strong>
                        request<?= $demoRequestsActionCount == 1 ? '' : 's' ?>
                        need<?= $demoRequestsActionCount == 1 ? 's' : '' ?>
                        admin action.
                    </div>

                <?php else: ?>

                    <div class="text-muted mb-2">
                        No Demo requests need action.
                    </div>

                <?php endif; ?>

                <a
                    href="?page=demo-requests"
                    class="btn btn-sm btn-primary"
                >
                    View Requests
                </a>

            </div>

        </div>

    <?php endif; ?>


    <!-- Demo Password Recovery -->
    <?php if (!isset($_SESSION['demo_user'])): ?>

        <div class="card shadow-sm border-danger mb-4">

            <div class="card-header bg-danger text-white">
                <strong>🔐 Demo Password Recovery</strong>

                <?php if ($demoPasswordRecoveryCount > 0): ?>
                    <span class="badge bg-light text-danger float-end">
                        <?= (int) $demoPasswordRecoveryCount ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="card-body text-center p-3">

                <?php if ($demoPasswordRecoveryCount > 0): ?>

                    <div class="mb-2">
                        <strong><?= (int) $demoPasswordRecoveryCount ?></strong>
                        pending recovery
                        request<?= $demoPasswordRecoveryCount == 1 ? '' : 's' ?>.
                    </div>

                    <a
                        href="?page=demo-password-recovery-requests"
                        class="btn btn-sm btn-danger"
                    >
                        Review Requests
                    </a>

                <?php else: ?>

                    <div class="text-muted mb-2">
                        No pending password recovery requests.
                    </div>

                    <a
                        href="?page=demo-password-recovery-requests"
                        class="btn btn-sm btn-outline-danger"
                    >
                        View Requests
                    </a>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>


    <!-- Company Support Leads -->
    <?php if (!isset($_SESSION['demo_user'])): ?>

        <div class="card shadow-sm border-success mb-4">

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

                <p class="mb-3">
                    🗄️ Archived:
                    <strong><?= $archivedLeads ?></strong>
                </p>

                <a
                    href="?page=contract-leads"
                    class="btn btn-success"
                >
                    View Leads
                </a>

                <a
                    href="?page=pending-contract-leads"
                    class="btn btn-warning mt-2"
                >
                    🔍 Pending Reviews
                    <?php if ($pendingLeads > 0): ?>
                        (<?= (int) $pendingLeads ?>)
                    <?php endif; ?>
                </a>

            </div>

        </div>

    <?php endif; ?>

</div>
<!-- End right sidebar -->
<?php endif; ?>

</div>
<!-- End main dashboard row -->

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>