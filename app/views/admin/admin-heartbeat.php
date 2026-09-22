<?php

require_once CONFIG_PATH . '/database.php';

/*
 * Only an authenticated Main/Dev Admin can send
 * the Main/Dev Admin heartbeat.
 */
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit;
}

$adminEmail = trim((string) $_SESSION['user']);

if ($adminEmail === '') {
    http_response_code(403);
    exit;
}

/*
 * Find the current Admin.
 */
$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$adminEmail]);

$adminId = (int) $stmt->fetchColumn();

if ($adminId <= 0) {
    http_response_code(403);
    exit;
}

/*
 * Update Admin presence.
 */
$presenceStmt = $pdo->prepare("
    INSERT INTO admin_presence
        (
            admin_id,
            last_seen,
            is_online
        )
    VALUES
        (
            ?,
            CURRENT_TIMESTAMP,
            1
        )
    ON DUPLICATE KEY UPDATE
        last_seen = CURRENT_TIMESTAMP,
        is_online = 1
");

$presenceStmt->execute([$adminId]);

/*
 * Return a small JSON response.
 */
header('Content-Type: application/json');

echo json_encode([
    'success' => true
]);

exit;