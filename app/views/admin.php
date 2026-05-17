<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}
?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<?php
require dirname(__DIR__, 2) . '/config/database.php';

// Total messages
$totalStmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
$total = $totalStmt->fetch()['total'];

// Today's messages
$todayStmt = $pdo->query("SELECT COUNT(*) as today FROM messages WHERE DATE(created_at) = CURDATE()");
$today = $todayStmt->fetch()['today'];
?>

<h1 class="mb-4 pt-3">
    Admin - Messages
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
require dirname(__DIR__, 2) . '/config/database.php';

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$limit = 5;

$pageNumber = $_GET['p'] ?? 1;

if ($pageNumber < 1) {
    $pageNumber = 1;
}

$offset = ($pageNumber - 1) * $limit;

$sql = "SELECT * FROM messages WHERE 1";

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

<?php
// Total messages
$totalStmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
$total = $totalStmt->fetch()['total'];

// Today's messages
$todayStmt = $pdo->query("SELECT COUNT(*) as today FROM messages WHERE DATE(created_at) = CURDATE()");
$today = $todayStmt->fetch()['today'];
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

<table border="1" cellpadding="10">
    <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Message</th>
        <th>Date</th>
        <th>Status</th>
        <th>Action</th>
    </tr>

    <?php foreach ($messages as $msg): ?>
    <tr>
        <td><?= htmlspecialchars($msg['name']) ?></td>
        <td><?= htmlspecialchars($msg['email']) ?></td>
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
            <a class="btn btn-sm btn-warning" href="?page=edit&id=<?= $msg['id'] ?>">Edit</a>
            <a class="btn btn-sm btn-danger" href="?page=delete&id=<?= $msg['id'] ?>" onclick="return confirm('Delete this message?')">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<nav class="mt-4">

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

        <a
            class="btn btn-sm <?= $i == $pageNumber ? 'btn-primary' : 'btn-outline-primary' ?> me-1"

            href="?page=admin&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

            <?= $i ?>

        </a>

    <?php endfor; ?>

</nav>

<?php require __DIR__ . '/layouts/footer.php'; ?>