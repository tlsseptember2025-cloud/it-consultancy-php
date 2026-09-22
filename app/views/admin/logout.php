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
                ap.last_seen = CURRENT_TIMESTAMP
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
 * Destroy the current session.
 */
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