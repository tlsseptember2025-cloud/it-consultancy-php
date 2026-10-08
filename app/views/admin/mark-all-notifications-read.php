<?php

require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    // Demo Super Admin uses the Demo database and is intentionally allowed here.
}

/*
|--------------------------------------------------------------------------
| CSRF protection
|--------------------------------------------------------------------------
| This route changes notification state, so it must not be executable
| through an unprotected GET request.
*/
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$submittedToken = (string) ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');

if (
    $submittedToken === '' ||
    !hash_equals((string) $csrfToken, $submittedToken)
) {
    http_response_code(403);
    die('Invalid security token.');
}

/*
|--------------------------------------------------------------------------
| Determine Admin Type
|--------------------------------------------------------------------------
*/
$isDemoAdmin =
    isset($_SESSION['demo_user']) ||
    isset($_SESSION['demo_super_admin']);

$isMainAdmin = isset($_SESSION['user']);

/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/
if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $adminPdo = $demoPdo;
} else {
    require_once CONFIG_PATH . '/database.php';
    $adminPdo = $pdo;
}

/*
|--------------------------------------------------------------------------
| Mark All Admin Notifications as Read
|--------------------------------------------------------------------------
*/
if ($isDemoAdmin && !$isMainAdmin) {
    $demoAdminId = (int) ($_SESSION['demo_user']['id'] ?? 0);

    if ($demoAdminId <= 0) {
        http_response_code(403);
        exit('Invalid Demo Admin session.');
    }

    $stmt = $adminPdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_type = 'admin'
          AND recipient_id = ?
          AND is_read = 0
    ");

    $stmt->execute([$demoAdminId]);
} else {
    $stmt = $adminPdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_type = 'admin'
          AND recipient_id IS NULL
          AND is_read = 0
    ");

    $stmt->execute();
}

/*
|--------------------------------------------------------------------------
| Return to Notification History
|--------------------------------------------------------------------------
*/
header('Location: ?page=notifications');
exit;
