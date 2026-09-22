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
| Notification ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($id <= 0) {

    header("Location: ?page=dashboard");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Notification
|--------------------------------------------------------------------------
*/

$stmt = $adminPdo->prepare("
    SELECT *
    FROM notifications
    WHERE id = ?
      AND recipient_type = 'admin'
");

$stmt->execute([
    $id
]);

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

$stmt = $adminPdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE id = ?
");

$stmt->execute([
    $id
]);


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