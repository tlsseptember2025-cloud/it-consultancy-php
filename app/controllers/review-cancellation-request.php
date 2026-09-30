<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$requestsPdo = $pdo;
$demoTenantId = 0;

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $requestsPdo = $demoPdo;

    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $requestsPdo->prepare("
        SELECT id, status, expires_at
        FROM demo_tenants
        WHERE id = ?
        LIMIT 1
    ");
    $tenantStmt->execute([$demoTenantId]);
    $demoTenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$demoTenant
        || $demoTenant['status'] !== 'Active'
        || (
            $demoTenant['expires_at'] !== null
            && strtotime($demoTenant['expires_at']) <= time()
        )
    ) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminStmt = $requestsPdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
        LIMIT 1
    ");
    $adminStmt->execute([
        (int) ($_SESSION['demo_user']['id'] ?? 0),
        $demoTenantId
    ]);

    $demoAdmin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!$demoAdmin) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminId = (int) $demoAdmin['id'];
} else {
    $adminStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
          AND is_demo_account = 0
        LIMIT 1
    ");
    $adminStmt->execute([$_SESSION['user']]);
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
    $adminId = isset($admin['id']) ? (int) $admin['id'] : null;
}

require_once APP_PATH . '/helpers/contact_history_helper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($requestId <= 0) {
    die('Invalid request.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (
        $submittedToken === ''
        || !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        die('Invalid security token.');
    }
}

$stmt = $requestsPdo->prepare("
SELECT
    r.*,
    c.name AS customer_name,
    c.email,
    c.phone,
    s.title AS service_name,
    cs.slot_date,
    cs.slot_time,
    cs.consultation_method
FROM requests r
INNER JOIN customers c ON c.id = r.customer_id
INNER JOIN services s ON s.id = r.service_id
INNER JOIN consultation_bookings cb ON cb.request_id = r.id
INNER JOIN consultation_slots cs ON cs.id = cb.slot_id
WHERE r.id = ?
  AND (
      ? = 0
      OR (
          c.demo_tenant_id = ?
          AND c.is_demo_account = 1
      )
  )
LIMIT 1
");
$stmt->execute([$requestId, $demoTenantId, $demoTenantId]);
$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    die('Request not found.');
}

if (isset($_POST['save_customer_response'])) {

    $responseMethod = trim($_POST['response_method']);
    $customerDecision = trim($_POST['customer_decision']);
    $responseNotes = trim($_POST['response_notes']);

    if (
        $responseMethod === '' ||
        $customerDecision === '' ||
        $responseNotes === ''
    ) {

        die('All fields are required.');

    }

    switch ($customerDecision) {

        case 'continue':

            $workflowStage = 'Consultation Confirmed';
            $jobStatus = 'Pending';
            $eventTitle = 'Customer Responded - Continue Consultation';

            break;

        case 'reschedule':

            $workflowStage = 'Needs Admin Review';
            $jobStatus = 'Pending';
            $eventTitle = 'Customer Requested Reschedule';

            break;

        case 'cancel':

            $workflowStage = 'Needs Admin Review';
            $jobStatus = 'Pending';
            $eventTitle = 'Customer Requested Cancellation';

            break;

        default:

            die('Invalid customer decision.');

    }

    $stmt = $requestsPdo->prepare("
        UPDATE requests
        SET
            workflow_stage = ?,
            job_status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $workflowStage,
        $jobStatus,
        $consultation['id']
    ]);

    addContactHistory(

        $requestsPdo,

        $consultation['id'],

        null,

        $adminId,

        'admin',

        RequestEventHelper::EVENT_CUSTOMER_RESPONSE_RECORDED,

        'Customer responded via ' . $responseMethod .
        '. Decision: Customer Requested Cancellation. ' .
        'Administrator Notes: ' . $responseNotes

    );

    RequestEventHelper::add(

        $requestsPdo,

        $consultation['id'],

        'CUSTOMER_RESPONSE_RECORDED',

        RequestEventHelper::TYPE_CONTACT,

        $eventTitle,

        $responseNotes,

        RequestEventHelper::SOURCE_ADMINISTRATOR,

        $adminId,
        true

    );

    header('Location: ?page=awaiting-customer-response&success=response-recorded');
    exit;
}

if (isset($_POST['continue_consultation'])) {

    $consultationDateTime = strtotime(
        $consultation['slot_date'] . ' ' . $consultation['slot_time']
    );

    $currentDateTime = time();

    if ($currentDateTime > $consultationDateTime) {

        $stmt = $requestsPdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Needs Admin Review',
                job_status = 'Pending',
                admin_instruction = '__RESCHEDULE_ALLOWED__'
            WHERE id = ?
        ");

        $stmt->execute([$requestId]);

        addContactHistory(

            $requestsPdo,

            $requestId,

            null,

            $adminId,

            'admin',

            RequestEventHelper::EVENT_CONSULTATION_RESCHEDULE_APPROVED,

            'Administrator approved continuation of the consultation. '
            . 'The previous consultation had expired. '
            . 'Customer may now reschedule the consultation.'

        );

        RequestEventHelper::add(

            $requestsPdo,

            $requestId,

            RequestEventHelper::EVENT_CONSULTATION_RESCHEDULE_APPROVED,

            RequestEventHelper::TYPE_CONSULTATION,

            'Consultation Reschedule Approved',

            'Customer may now reschedule the consultation.',

            RequestEventHelper::SOURCE_ADMINISTRATOR,

            $adminId,
            true

        );

        header('Location: ?page=needs-admin-review&success=reschedule-approved');
        exit;

    } else {

        $stmt = $requestsPdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Consultation Confirmed',
                job_status = 'Pending'
            WHERE id = ?
        ");

        $stmt->execute([$requestId]);

        addContactHistory(

            $requestsPdo,

            $requestId,

            null,

            $adminId,

            'admin',

            RequestEventHelper::EVENT_CUSTOMER_CONTINUED_CURRENT_APPOINTMENT,

            'Administrator approved continuation of the consultation.'

        );

        RequestEventHelper::add(

            $requestsPdo,

            $requestId,

            RequestEventHelper::EVENT_CUSTOMER_CONTINUED_CURRENT_APPOINTMENT,

            RequestEventHelper::TYPE_CONSULTATION,

            'Consultation Continued',

            'Administrator approved continuation of the consultation.',

            RequestEventHelper::SOURCE_ADMINISTRATOR,

            $adminId,
            true

        );

        header('Location: ?page=requests&success=consultation-continued');
        exit;

    }

}

$csrfToken = $_SESSION['csrf_token'];

require VIEW_PATH . '/admin/review-cancellation-request.php';