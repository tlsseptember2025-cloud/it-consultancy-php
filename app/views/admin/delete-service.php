<?php

require_once HELPER_PATH . '/auth.php';
requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ?page=services-admin');
    exit;
}

if (isset($_SESSION['demo_user'])) {
    require_once CONFIG_PATH . '/demo-database.php';
    $servicesPdo = $demoPdo;
} else {
    require CONFIG_PATH . '/database.php';
    $servicesPdo = $pdo;
}

$id = (int) $_GET['id'];

if ($id <= 0) {
    header('Location: ?page=services-admin');
    exit;
}

$csrfKey = 'delete_service_csrf';

if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $servicesPdo->prepare('SELECT id, title FROM services WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        header('Location: ?page=services-admin');
        exit;
    }
    ?>
    <div class="container py-4">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger text-white">
                <strong>Delete Service</strong>
            </div>
            <div class="card-body">
                <p>
                    Are you sure you want to delete
                    <strong><?= htmlspecialchars($service['title']) ?></strong>?
                </p>
                <p class="text-danger mb-4">This action cannot be undone.</p>

                <form method="post" action="?page=delete-service&amp;id=<?= $id ?>">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION[$csrfKey]) ?>"
                    >

                    <button type="submit" class="btn btn-danger">
                        Delete Service
                    </button>
                    <a href="?page=services-admin" class="btn btn-secondary">
                        Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>
    <?php
    exit;
}

$submittedToken = (string) ($_POST['csrf_token'] ?? '');
$sessionToken = (string) ($_SESSION[$csrfKey] ?? '');

if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    exit('Invalid security token. Please try again.');
}

$stmt = $servicesPdo->prepare('SELECT id FROM services WHERE id = ? LIMIT 1');
$stmt->execute([$id]);

if (!$stmt->fetchColumn()) {
    header('Location: ?page=services-admin');
    exit;
}

$deleteStmt = $servicesPdo->prepare('DELETE FROM services WHERE id = ?');
$deleteStmt->execute([$id]);

unset($_SESSION[$csrfKey]);

header('Location: ?page=services-admin');
exit;
