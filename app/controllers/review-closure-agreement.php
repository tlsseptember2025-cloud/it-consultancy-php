<?php

$pageTitle = 'Review Closure Agreement';

require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$reviewPdo = $pdo;
$demoTenantId = null;

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';

    $reviewPdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $reviewPdo->prepare("
        SELECT id, status, expires_at
        FROM demo_tenants
        WHERE id = ?
        LIMIT 1
    ");
    $tenantStmt->execute([$demoTenantId]);
    $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$tenant ||
        $tenant['status'] !== 'Active' ||
        ($tenant['expires_at'] !== null && strtotime($tenant['expires_at']) <= time())
    ) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminStmt = $reviewPdo->prepare("
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

    if (!$adminStmt->fetch(PDO::FETCH_ASSOC)) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
}

$agreementId = (int) ($_GET['agreement_id'] ?? 0);

if ($agreementId <= 0) {
    die('Invalid agreement.');
}

$stmt = $reviewPdo->prepare("
    SELECT
        cca.*,
        r.id AS request_number,
        c.name AS customer_name,
        s.title AS service_name
    FROM consultation_closure_agreements AS cca
    INNER JOIN requests AS r
        ON r.id = cca.request_id
    INNER JOIN customers AS c
        ON c.id = cca.customer_id
    INNER JOIN services AS s
        ON s.id = r.service_id
    WHERE cca.id = ?
      AND (
          ? = 0
          OR (
              c.demo_tenant_id = ?
              AND c.is_demo_account = 1
          )
      )
    LIMIT 1
");

$stmt->execute([$agreementId, $demoTenantId ?? 0, $demoTenantId ?? 0]);

$agreement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agreement) {
    die('Agreement not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    }

    $decision = $_POST['decision'] ?? '';

    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (!in_array($decision, ['Approved', 'Rejected'], true)) {

        $errors[] = 'Please select a review decision.';

    }

    if (
        $decision === 'Rejected' &&
        $adminNotes === ''
    ) {

        $errors[] = 'Administrator notes are required when rejecting a closure request.';

    }

    if (mb_strlen($adminNotes) > 5000) {
        $errors[] = 'Administrator notes are too long.';
    }

    if (empty($errors)) {

    $stmt = $reviewPdo->prepare("
        UPDATE consultation_closure_agreements
        SET
            status = ?,
            admin_notes = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $decision,
        $adminNotes,
        $agreementId
    ]);

    $stmt = $reviewPdo->prepare("
    UPDATE requests
    SET workflow_stage = ?
    WHERE id = ?
");

$stmt->execute([
    $decision === 'Approved'
        ? 'Closure Approved'
        : 'Closure Rejected',
    $agreement['request_id']
]);

/*
|--------------------------------------------------------------------------
| Record Closure Agreement Review Event
|--------------------------------------------------------------------------
*/

if ($decision === 'Approved') {

    RequestEventHelper::addCurrentUser(
        $reviewPdo,
        (int) $agreement['request_id'],
        RequestEventHelper::EVENT_CLOSURE_AGREEMENT_APPROVED,
        RequestEventHelper::TYPE_CONSULTATION,
        'Closure Agreement Approved',
        'The administrator approved the customer’s Consultation Closure Agreement.',
        false
    );

} else {

    RequestEventHelper::addCurrentUser(
        $reviewPdo,
        (int) $agreement['request_id'],
        RequestEventHelper::EVENT_CLOSURE_AGREEMENT_REJECTED,
        RequestEventHelper::TYPE_CONSULTATION,
        'Closure Agreement Rejected',
        'The administrator rejected the customer’s Consultation Closure Agreement.',
        false
    );

}

header('Location: index.php?page=closure-agreements&success=review-saved');
exit;

}

}

require VIEW_PATH . '/admin/review-closure-agreement.php';