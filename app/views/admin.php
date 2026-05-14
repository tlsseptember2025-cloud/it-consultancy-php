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

<h1>Admin - Messages</h1>

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

if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM messages 
        WHERE name LIKE ? OR email LIKE ? 
        ORDER BY created_at DESC");

    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC");
}
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

<form method="GET" class="mb-3">
    <input type="hidden" name="page" value="admin">
    
    <input type="text" name="search" placeholder="Search by name or email" 
           value="<?= $_GET['search'] ?? '' ?>" class="form-control w-25 d-inline">

    <button type="submit" class="btn btn-primary">Search</button>
</form>

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

<?php require __DIR__ . '/layouts/footer.php'; ?>