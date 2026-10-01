<?php

require_once CONFIG_PATH . '/demo-database.php';

if (!isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-login');
    exit;
}

$tenantId = (int) ($_GET['id'] ?? 0);

if ($tenantId <= 0) {
    header('Location: ?page=demo-super-admin');
    exit;
}

$tenantStmt = $demoPdo->prepare("SELECT * FROM demo_tenants WHERE id = ? LIMIT 1");
$tenantStmt->execute([$tenantId]);
$tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    $_SESSION['error'] = 'Demo company not found.';
    header('Location: ?page=demo-super-admin');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fixed Demo Accounts
|--------------------------------------------------------------------------
*/

$adminStmt = $demoPdo->prepare("
    SELECT id, username, email, force_password_change
    FROM users
    WHERE demo_tenant_id = ?
      AND is_demo_account = 1
      AND is_super_admin = 0
    ORDER BY id ASC
    LIMIT 1
");
$adminStmt->execute([$tenantId]);
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$customerStmt = $demoPdo->prepare("
    SELECT id, name, username, email
    FROM customers
    WHERE demo_tenant_id = ?
      AND is_demo_account = 1
    ORDER BY id ASC
    LIMIT 1
");
$customerStmt->execute([$tenantId]);
$customer = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$agentsStmt = $demoPdo->prepare("
    SELECT id, name, username, email
    FROM agents
    WHERE demo_tenant_id = ?
      AND is_demo_account = 1
    ORDER BY id ASC
    LIMIT 2
");
$agentsStmt->execute([$tenantId]);
$agents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Helper: safely execute a count query.
|--------------------------------------------------------------------------
*/

$countValue = static function (PDO $pdo, string $sql, array $params = []): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Demo company report count failed: ' . $e->getMessage());
        return 0;
    }
};

$sumValue = static function (PDO $pdo, string $sql, array $params = []): float {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Demo company report total failed: ' . $e->getMessage());
        return 0.0;
    }
};

/*
|--------------------------------------------------------------------------
| Usage Metrics
|--------------------------------------------------------------------------
*/

$requestCount = $countValue(
    $demoPdo,
    "SELECT COUNT(*)
     FROM requests r
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1",
    [$tenantId]
);

$completedJobs = $countValue(
    $demoPdo,
    "SELECT COUNT(*)
     FROM requests r
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ?
       AND c.is_demo_account = 1
       AND (r.job_status = 'Completed' OR r.status = 'Completed')",
    [$tenantId]
);

$cancelledJobs = $countValue(
    $demoPdo,
    "SELECT COUNT(*)
     FROM requests r
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ?
       AND c.is_demo_account = 1
       AND (r.job_status = 'Cancelled' OR r.status = 'Cancelled')",
    [$tenantId]
);

$consultationCount = $countValue(
    $demoPdo,
    "SELECT COUNT(*)
     FROM consultation_bookings cb
     INNER JOIN requests r ON r.id = cb.request_id
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1",
    [$tenantId]
);

$paymentCount = $countValue(
    $demoPdo,
    "SELECT COUNT(*)
     FROM payments p
     INNER JOIN requests r ON r.id = p.request_id
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1",
    [$tenantId]
);

$paymentTotal = $sumValue(
    $demoPdo,
    "SELECT COALESCE(SUM(p.amount), 0)
     FROM payments p
     INNER JOIN requests r ON r.id = p.request_id
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1",
    [$tenantId]
);

$refundCount = $countValue(
    $demoPdo,
    "SELECT COUNT(*)
     FROM refund_requests rr
     INNER JOIN requests r ON r.id = rr.request_id
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1",
    [$tenantId]
);

$refundTotal = $sumValue(
    $demoPdo,
    "SELECT COALESCE(SUM(rr.refund_amount), 0)
     FROM refund_requests rr
     INNER JOIN requests r ON r.id = rr.request_id
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1",
    [$tenantId]
);

$notificationCount = $countValue(
    $demoPdo,
    "SELECT COUNT(*)
     FROM notifications
     WHERE recipient_type = 'customer'
       AND recipient_id = ?",
    [$customer['id'] ?? 0]
);

/*
|--------------------------------------------------------------------------
| Live Chat Activity
|--------------------------------------------------------------------------
|
| Demo Live Chat is counted against the provisioned Demo customer's email.
| This keeps guest-chat records attributable to the company without
| introducing a second chat system.
|--------------------------------------------------------------------------
*/

$liveChatConversations = 0;
$liveChatMessages = 0;
$liveChatAdminReplies = 0;

try {
    $chatTableStmt = $demoPdo->query("SHOW TABLES LIKE 'guest_chat_conversations'");

    if ($chatTableStmt && $chatTableStmt->fetchColumn() && $customer) {
        $chatEmail = (string) ($customer['email'] ?? '');

        $liveChatConversations = $countValue(
            $demoPdo,
            "SELECT COUNT(*)
             FROM guest_chat_conversations
             WHERE guest_email = ?",
            [$chatEmail]
        );

        $liveChatMessages = $countValue(
            $demoPdo,
            "SELECT COUNT(*)
             FROM guest_chat_messages m
             INNER JOIN guest_chat_conversations gc
                 ON gc.id = m.conversation_id
             WHERE gc.guest_email = ?",
            [$chatEmail]
        );

        $liveChatAdminReplies = $countValue(
            $demoPdo,
            "SELECT COUNT(*)
             FROM guest_chat_messages m
             INNER JOIN guest_chat_conversations gc
                 ON gc.id = m.conversation_id
             WHERE gc.guest_email = ?
               AND m.sender_type = 'admin'",
            [$chatEmail]
        );
    }
} catch (Throwable $e) {
    error_log('Demo company report live chat failed: ' . $e->getMessage());
}

$eventCount = $countValue(
    $demoPdo,
    "SELECT COUNT(*)
     FROM request_events re
     INNER JOIN requests r ON r.id = re.request_id
     INNER JOIN customers c ON c.id = r.customer_id
     WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1",
    [$tenantId]
);

/*
|--------------------------------------------------------------------------
| Customer Activity
|--------------------------------------------------------------------------
*/

$customerRequests = $customer
    ? $countValue($demoPdo, "SELECT COUNT(*) FROM requests WHERE customer_id = ?", [$customer['id']])
    : 0;

$customerNotifications = $customer
    ? $countValue($demoPdo, "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'customer' AND recipient_id = ?", [$customer['id']])
    : 0;

$customerPayments = $customer
    ? $countValue(
        $demoPdo,
        "SELECT COUNT(*) FROM payments p INNER JOIN requests r ON r.id = p.request_id WHERE r.customer_id = ?",
        [$customer['id']]
    )
    : 0;

$customerRefunds = $customer
    ? $countValue(
        $demoPdo,
        "SELECT COUNT(*) FROM refund_requests rr INNER JOIN requests r ON r.id = rr.request_id WHERE r.customer_id = ?",
        [$customer['id']]
    )
    : 0;

/*
|--------------------------------------------------------------------------
| Agent Activity
|--------------------------------------------------------------------------
*/

$agentMetrics = [];

foreach ($agents as $agent) {
    $agentId = (int) $agent['id'];

    $agentMetrics[$agentId] = [
        'requests' => $countValue(
            $demoPdo,
            "SELECT COUNT(*) FROM requests r INNER JOIN customers c ON c.id = r.customer_id WHERE r.agent_id = ? AND c.demo_tenant_id = ? AND c.is_demo_account = 1",
            [$agentId, $tenantId]
        ),
        'consultations' => $countValue(
            $demoPdo,
            "SELECT COUNT(*) FROM consultation_bookings cb INNER JOIN requests r ON r.id = cb.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE cb.agent_id = ? AND c.demo_tenant_id = ? AND c.is_demo_account = 1",
            [$agentId, $tenantId]
        ),
        'completed' => $countValue(
            $demoPdo,
            "SELECT COUNT(*) FROM requests r INNER JOIN customers c ON c.id = r.customer_id WHERE r.agent_id = ? AND c.demo_tenant_id = ? AND c.is_demo_account = 1 AND (r.job_status = 'Completed' OR r.status = 'Completed')",
            [$agentId, $tenantId]
        ),
    ];
}

/*
|--------------------------------------------------------------------------
| Request Activity Timeline
|--------------------------------------------------------------------------
*/

$events = [];

try {
    $eventStmt = $demoPdo->prepare("
        SELECT
            re.*,
            r.id AS request_number,
            c.name AS customer_name,
            s.title AS service_name
        FROM request_events re
        INNER JOIN requests r ON r.id = re.request_id
        INNER JOIN customers c ON c.id = r.customer_id
        LEFT JOIN services s ON s.id = r.service_id
        WHERE c.demo_tenant_id = ?
          AND c.is_demo_account = 1
        ORDER BY re.created_at DESC, re.id DESC
        LIMIT 100
    ");
    $eventStmt->execute([$tenantId]);
    $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Demo company report events failed: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Recent Requests
|--------------------------------------------------------------------------
*/

$recentRequests = [];

try {
    $requestStmt = $demoPdo->prepare("
        SELECT
            r.id,
            r.status,
            r.workflow_stage,
            r.job_status,
            r.created_at,
            c.name AS customer_name,
            s.title AS service_name,
            a.name AS agent_name
        FROM requests r
        INNER JOIN customers c ON c.id = r.customer_id
        LEFT JOIN services s ON s.id = r.service_id
        LEFT JOIN agents a ON a.id = r.agent_id
        WHERE c.demo_tenant_id = ?
          AND c.is_demo_account = 1
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT 10
    ");
    $requestStmt->execute([$tenantId]);
    $recentRequests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Demo company report requests failed: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Latest Activity
|--------------------------------------------------------------------------
*/
$latestActivity = $events[0]['created_at'] ?? ($recentRequests[0]['created_at'] ?? null);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1"><?= htmlspecialchars($tenant['company_name'] ?? 'Demo Company', ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted mb-0">
                Demo Usage Report · Tenant #<?= $tenantId ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=demo-super-admin" class="btn btn-outline-secondary">Demo Companies</a>
            <a href="?page=demo-usage-reports&tenant_id=<?= $tenantId ?>" class="btn btn-primary">Overall Usage</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Requests', $requestCount, 'bi-inbox'],
            ['Consultations', $consultationCount, 'bi-calendar-check'],
            ['Completed Jobs', $completedJobs, 'bi-check-circle'],
            ['Cancelled Jobs', $cancelledJobs, 'bi-x-circle'],
            ['Payments', $paymentCount, 'bi-credit-card'],
            ['Refunds', $refundCount, 'bi-arrow-counterclockwise'],
            ['Notifications', $notificationCount, 'bi-bell'],
            ['Live Chats', $liveChatConversations, 'bi-chat-dots'],
            ['Recorded Events', $eventCount, 'bi-clock-history'],
        ];
        foreach ($cards as [$label, $value, $icon]):
        ?>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-2"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></div>
                        <div class="fs-3 fw-semibold"><?= number_format((int) $value) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Company</strong></div>
                <div class="card-body">
                    <p><strong>Domain:</strong> <?= htmlspecialchars($tenant['company_domain'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($tenant['registered_email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                    <?php
                    $reportStatus = $tenant['status'] ?? '-';

                    if (
                        !empty($tenant['expires_at']) &&
                        strtotime($tenant['expires_at']) < time()
                    ) {
                        $reportStatus = 'Expired';
                    }
                    ?>
                    <p><strong>Status:</strong> <?= htmlspecialchars($reportStatus, ENT_QUOTES, 'UTF-8') ?></p>
                    <p><strong>Started:</strong> <?= htmlspecialchars($tenant['started_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0"><strong>Last recorded activity:</strong> <?= htmlspecialchars($latestActivity ?? 'No activity yet', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Customer Activity</strong></div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?= htmlspecialchars($customer['name'] ?? 'Not provisioned', ENT_QUOTES, 'UTF-8') ?></p>
                    <p><strong>Requests:</strong> <?= number_format($customerRequests) ?></p>
                    <p><strong>Payments:</strong> <?= number_format($customerPayments) ?></p>
                    <p><strong>Refund requests:</strong> <?= number_format($customerRefunds) ?></p>
                    <p><strong>Live chats:</strong> <?= number_format($liveChatConversations) ?></p>
                    <p class="mb-0"><strong>Notifications:</strong> <?= number_format($customerNotifications) ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Financial Usage</strong></div>
                <div class="card-body">
                    <p><strong>Total payments:</strong> AED <?= number_format($paymentTotal, 2) ?></p>
                    <p><strong>Total refunds requested:</strong> AED <?= number_format($refundTotal, 2) ?></p>
                    <p><strong>Live Chat messages:</strong> <?= number_format($liveChatMessages) ?></p>
                    <p class="mb-0"><strong>Admin Chat replies:</strong> <?= number_format($liveChatAdminReplies) ?></p>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Agent Activity</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Username</th>
                            <th>Requests</th>
                            <th>Consultations</th>
                            <th>Completed Jobs</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($agents)): ?>
                        <tr><td colspan="5" class="text-muted">No Demo agents provisioned.</td></tr>
                    <?php else: ?>
                        <?php foreach ($agents as $agent): ?>
                            <?php $m = $agentMetrics[(int) $agent['id']] ?? ['requests'=>0,'consultations'=>0,'completed'=>0]; ?>
                            <tr>
                                <td><?= htmlspecialchars($agent['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($agent['username'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= number_format($m['requests']) ?></td>
                                <td><?= number_format($m['consultations']) ?></td>
                                <td><?= number_format($m['completed']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Recent Requests</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Agent</th>
                            <th>Status</th>
                            <th>Workflow</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentRequests)): ?>
                        <tr><td colspan="7" class="p-3 text-muted">No requests recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $request): ?>
                            <tr>
                                <td><?= (int) $request['id'] ?></td>
                                <td><?= htmlspecialchars($request['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($request['service_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($request['agent_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($request['status'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($request['workflow_stage'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($request['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>Activity Timeline</strong></div>
        <div class="card-body">
            <?php if (empty($events)): ?>
                <div class="alert alert-secondary mb-0">
                    No RequestEvent records have been recorded for this company yet.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($events as $event): ?>
                        <?php
                        $eventTitle = $event['event_title']
                            ?? $event['title']
                            ?? $event['event_type']
                            ?? 'Activity';
                        $eventDescription = $event['description']
                            ?? $event['details']
                            ?? '';
                        $actor = $event['user_name']
                            ?? $event['actor_name']
                            ?? $event['user_type']
                            ?? 'System';
                        ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars((string) $actor, ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($event['request_number'])): ?>
                                            · Request #<?= (int) $event['request_number'] ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($eventDescription !== ''): ?>
                                        <div class="mt-1">
                                            <?= nl2br(htmlspecialchars((string) $eventDescription, ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted text-nowrap">
                                    <?= htmlspecialchars((string) ($event['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
