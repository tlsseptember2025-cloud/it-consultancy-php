<?php

require_once HELPER_PATH . '/auth.php';


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
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainAdmin && !$isDemoAdmin) {

    header("Location: ?page=login");
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
| Mark All Admin Notifications as Read
|--------------------------------------------------------------------------
*/

$stmt = $adminPdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE recipient_type = 'admin'
      AND is_read = 0
");

$stmt->execute();


/*
|--------------------------------------------------------------------------
| Return to Notification History
|--------------------------------------------------------------------------
*/

header('Location: ?page=notifications');
exit;