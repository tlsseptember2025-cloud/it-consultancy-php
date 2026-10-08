<?php

$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);
$isDemoAdmin = isset($_SESSION['demo_user']);
$isMainAdmin = isset($_SESSION['user']);

/*
 * -------------------------------------------------
 * Main/Dev Admin - mark offline
 * -------------------------------------------------
 */
if ($isMainAdmin) {

    require_once CONFIG_PATH . '/database.php';

    $adminEmail = trim((string) $_SESSION['user']);

    if ($adminEmail !== '') {

        $presenceStmt = $pdo->prepare("
            UPDATE admin_presence ap
            INNER JOIN users u
                ON u.id = ap.admin_id
            SET
                ap.is_online = 0,
                ap.last_seen = UTC_TIMESTAMP()
            WHERE u.email = ?
        ");

        $presenceStmt->execute([$adminEmail]);
    }
}

/*
 * -------------------------------------------------
 * Demo Admin / Demo Super Admin
 * -------------------------------------------------
 *
 * We are not changing Demo presence yet.
 * Demo uses the Demo database and will be handled
 * separately in the Demo login/presence step.
 */

/*
 * Clear the current session completely.
 *
 * Destroy the server-side session and remove the session cookie so
 * the browser cannot continue sending the old session identifier.
 */
$_SESSION = [];

if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

/*
 * Redirect according to the previous role.
 */
if ($isDemoSuperAdmin) {

    header('Location: ?page=demo-super-admin-login');
    exit;
}

if ($isDemoAdmin) {

    header('Location: ?page=demo-login');
    exit;
}

header('Location: ?page=home');
exit;