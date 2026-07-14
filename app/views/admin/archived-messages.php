<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

require dirname(__DIR__) . '/layouts/header-admin.php';
require CONFIG_PATH . '/database.php';

// Total messages
$totalStmt = $pdo->query("SELECT COUNT(*) as total
FROM messages
WHERE is_closed = 1");
$total = $totalStmt->fetch()['total'];

// Today's messages
$todayStmt = $pdo->query("SELECT COUNT(*) as today
FROM messages
WHERE is_closed = 1
  AND DATE(created_at) = CURDATE()");
$today = $todayStmt->fetch()['today'];

?>

<h1 class="mb-4 pt-3">
    Admin - Archived Messages
</h1>

<div class="row mb-4">

    <div class="col-md-6">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <h5 class="card-title">Total Messages</h5>
                <p class="card-text fs-3"><?= $total ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <h5 class="card-title">Today's Messages</h5>
                <p class="card-text fs-3"><?= $today ?></p>
            </div>
        </div>
    </div>

</div>

<?php
require CONFIG_PATH . '/database.php';

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$limit = 5;

$pageNumber = $_GET['p'] ?? 1;

if ($pageNumber < 1) {
    $pageNumber = 1;
}

$offset = ($pageNumber - 1) * $limit;

$sql = "SELECT * FROM messages WHERE is_closed = 1";

$params = [];

if ($search) {

    $sql .= " AND (name LIKE ? OR email LIKE ?)";

    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {

    $sql .= " AND status = ?";

    $params[] = $status;
}

$countSql = str_replace("SELECT *", "SELECT COUNT(*)", $sql);

$countStmt = $pdo->prepare($countSql);

$countStmt->execute($params);

$totalMessages = $countStmt->fetchColumn();

$totalPages = ceil($totalMessages / $limit);

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$messages = $stmt->fetchAll();

?>

<div class="card p-3 mb-4">

    <form method="GET" class="row g-3 align-items-center">

        <input type="hidden" name="page" value="admin">

        <div class="col-md-4">
            <input
                type="text"
                id="searchInput"
                name="search"
                placeholder="Search by name or email"
                value="<?= $_GET['search'] ?? '' ?>"
                class="form-control">
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">

                <option value="">All Status</option>

                <option value="read"
                    <?= ($_GET['status'] ?? '') === 'read' ? 'selected' : '' ?>>
                    Read
                </option>

                <option value="unread"
                    <?= ($_GET['status'] ?? '') === 'unread' ? 'selected' : '' ?>>
                    Unread
                </option>

            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100">
                Filter
            </button>
        </div>

    </form>

</div>

<div id="results">
    <div class="table-responsive">

    <table class="table table-hover table-bordered align-middle">

            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Preferred</th>
            <th>Service</th>
            <th>Message</th>
        
        <?php foreach ($messages as $msg): ?>
        <tr>
            <td><?= htmlspecialchars($msg['name']) ?></td>
            <td><?= htmlspecialchars($msg['email']) ?></td>

            <td>
                <?= htmlspecialchars($msg['phone'] ?? '-') ?>
            </td>

            <td>
                <?= htmlspecialchars($msg['preferred_contact'] ?? 'Email') ?>
            </td>

            <td><?= htmlspecialchars($msg['service']) ?></td>

            <td><?= htmlspecialchars($msg['message']) ?></td>
            
            <td><?= $msg['created_at'] ?></td>
            
            <td>
                <?php if ($msg['status'] === 'unread'): ?>
                    <span style="color:red;">Unread</span>
                <?php else: ?>
                    <span style="color:green;">Read</span>
                <?php endif; ?>
            </td>
            
            <td>
                <a class="btn btn-sm btn-info" href="?page=view&id=<?= $msg['id'] ?>">View</a>
                <?php if (!$msg['is_closed']): ?>
                    <span class="badge bg-success">
                        Active
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary">
                        Archived
                    </span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>

    <nav class="mt-4">

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>

            <a
                class="btn btn-sm <?= $i == $pageNumber ? 'btn-primary' : 'btn-outline-primary' ?> me-1"

                href="?page=messages&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>

    </nav>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>