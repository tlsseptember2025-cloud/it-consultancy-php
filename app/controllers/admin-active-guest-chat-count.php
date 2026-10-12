
<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (isset($_SESSION['demo_user']) || isset($_SESSION['demo_super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not available']);
    exit;
}

try {
    require_once CONFIG_PATH . '/database.php';

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM guest_chat_conversations
        WHERE status = 'Open'
    ");

    echo json_encode([
        'count' => (int) $stmt->fetchColumn()
    ]);
} catch (Throwable $e) {
    error_log('Active Guest Chat count endpoint failed: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['error' => 'Unable to retrieve active chats']);
}
