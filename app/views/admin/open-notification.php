<?php

require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Admin Type
|--------------------------------------------------------------------------
*/

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);
$isMainAdmin = isset($_SESSION['user']);
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));


/*
|--------------------------------------------------------------------------
| Demo Super Admin Uses Separate Portal
|--------------------------------------------------------------------------
*/

if ($isDemoSuperAdmin) {

    header('Location: ?page=demo-super-admin');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    if (!isset($demoPdo)) {
        require_once CONFIG_PATH . '/demo-database.php';
    }

    $adminPdo = $demoPdo;

} else {

    require_once CONFIG_PATH . '/database.php';

    $adminPdo = $pdo;
}


/*
|--------------------------------------------------------------------------
| Notification ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if (
    !isset($_GET['csrf_token'])
    || !hash_equals((string) $csrfToken, (string) $_GET['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid security token.');
}


if ($id <= 0) {

    header("Location: ?page=dashboard");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Notification
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {
    $demoAdminId = (int) ($_SESSION['demo_user']['id'] ?? 0);

    if ($demoAdminId <= 0) {
        http_response_code(403);
        exit('Invalid Demo Admin session.');
    }

    $stmt = $adminPdo->prepare("
        SELECT *
        FROM notifications
        WHERE id = ?
          AND recipient_type = 'admin'
          AND recipient_id = ?
    ");

    $stmt->execute([
        $id,
        $demoAdminId
    ]);
} else {
    $stmt = $adminPdo->prepare("
        SELECT *
        FROM notifications
        WHERE id = ?
          AND recipient_type = 'admin'
          AND recipient_id IS NULL
    ");

    $stmt->execute([
        $id
    ]);
}

$notification = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$notification) {

    header("Location: ?page=dashboard");
    exit;
}


/*
|--------------------------------------------------------------------------
| Mark Notification as Read
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {
    $stmt = $adminPdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
          AND recipient_type = 'admin'
          AND recipient_id = ?
    ");

    $stmt->execute([
        $id,
        $demoAdminId
    ]);
} else {
    $stmt = $adminPdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
          AND recipient_type = 'admin'
          AND recipient_id IS NULL
    ");

    $stmt->execute([
        $id
    ]);
}


/*
|--------------------------------------------------------------------------
| Redirect to Stored Link
|--------------------------------------------------------------------------
*/

$link = trim($notification['link'] ?? '');


if ($link === '') {

    header("Location: ?page=notifications");
    exit;
}


header("Location: " . $link);
exit;