<?php

require dirname(__DIR__) . '/layouts/header-admin.php';

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Invalid proposal ID.');
}

$requestId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name,
        c.email,
        c.phone,
        c.company,
        s.title AS service_title
    FROM requests r
    LEFT JOIN customers c ON r.customer_id = c.id
    LEFT JOIN services s ON r.service_id = s.id
    WHERE r.id = ?
");

$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die("Proposal not found.");
}

$statusClass = 'secondary';

switch ($request['status']) {

    case 'Pending':
        $statusClass = 'warning';
        break;

    case 'Approved':
        $statusClass = 'success';
        break;

    case 'Completed':
        $statusClass = 'primary';
        break;

    case 'Cancelled':
        $statusClass = 'danger';
        break;
}

$statusIcon = 'bi-question-circle';

switch ($request['status']) {

    case 'Pending':
        $statusIcon = 'bi-clock-history';
        break;

    case 'Approved':
        $statusIcon = 'bi-check-circle';
        break;

    case 'Completed':
        $statusIcon = 'bi-check2-circle';
        break;

    case 'Cancelled':
        $statusIcon = 'bi-x-circle';
        break;
}

?>

<div class="container mt-4">

    <div class="card shadow">

        <div class="card-header bg-primary text-white">

            <h4 class="mb-0">
    Proposal Details
    <small class="text-white-50">
        (Request #<?= $request['id']; ?>)
    </small>
</h4>

<div class="row mb-4">

    <div class="col-md-4">

        <strong>Request ID</strong><br>

        #<?= $request['id']; ?>

    </div>

    <div class="col-md-4">

        <strong>Created</strong><br>

        <?= date('d M Y', strtotime($request['created_at'])); ?>

    </div>

    <div class="col-md-4">

        <strong>Workflow Stage</strong><br>

        <?= htmlspecialchars($request['workflow_stage']); ?>

    </div>

</div>



        </div>

        <div class="card-body">

            <div class="row mb-4">

            <hr>

           <h5 class="mb-3">

    <i class="bi bi-person-circle text-primary"></i>

    Customer Information

</h5>

    <div class="col-md-6">
        <div class="small text-secondary">

Customer

</div>

<div class="fw-semibold fs-5">

    <?= htmlspecialchars($request['name']); ?>

</div>
    </div>

    <div class="col-md-6">
        <div class="small text-muted">

    Email

</div>

<div>

    <i class="bi bi-envelope-fill text-primary"></i>

    <?= htmlspecialchars($request['email']); ?>

</div>
    </div>

</div>

<div class="row mb-4">

    <div class="col-md-6">
       <div class="small text-muted">

    Phone

</div>

<div>

    <i class="bi bi-telephone-fill text-success"></i>

    <?= htmlspecialchars($request['phone']); ?>

</div>
    </div>

    <div class="col-md-6">
        <div class="small text-muted">

    Company

</div>

<div>

    <i class="bi bi-building text-secondary"></i>

    <?= htmlspecialchars($request['company']); ?>

</div>
    </div>

</div>

<hr>

<h5 class="mb-3">

    <i class="bi bi-tools text-primary"></i>

    Service Information

</h5>

<div class="row mb-4">

    <div class="col-md-6">
        <strong>Service</strong><br>
        <?= htmlspecialchars($request['service_title']); ?>
    </div>

    <div class="col-md-6">
       
        <div class="small text-muted">

    Status

</div>

<div class="mt-2">

    <span class="badge bg-<?= $statusClass; ?>">

        <i class="bi <?= $statusIcon; ?>"></i>

        <?= htmlspecialchars($request['status']); ?>

    </span>

</div>

    </div>

</div>

<hr>

<h5 class="mb-3">

    <i class="bi bi-cash-stack text-success"></i>

    Quotation

</h5>

<div class="alert alert-success">

    <strong>Quoted Price</strong><br>

    <h4 class="mb-0">
        AED <?= number_format($request['quoted_price'], 2); ?>
    </h4>

</div>

<div class="card border-secondary">

    <div class="card-header bg-light">

        <h5 class="mb-3">

    <i class="bi bi-file-earmark-text text-primary"></i>

    Proposal Document

</h5>

<div class="mb-4">

    <h5 class="mb-1">

        Proposal

    </h5>

    <small class="text-muted">

        Prepared for <?= htmlspecialchars($request['name']); ?>

    </small>

</div>

    </div>

    <div class="card-body">

    <div
class="border rounded bg-white p-4"

style="
white-space:pre-wrap;
line-height:1.9;
font-size:15px;
min-height:260px;
">
        <?php if (!empty(trim($request['proposal']))): ?>

<?= htmlspecialchars($request['proposal']); ?>

<?php else: ?>

<div class="text-muted">

No proposal has been created yet.

</div>

<?php endif; ?>

    </div>

</div>

<hr>

<div class="d-flex justify-content-between">

    <a href="?page=requests"
   class="btn btn-secondary">

    <i class="bi bi-arrow-left"></i>

    Back

</a>

    <div>

        <a href="?page=create-proposal&id=<?= $request['id']; ?>"
   class="btn btn-warning me-2">

    <i class="bi bi-pencil-square"></i>

    Edit Proposal

</a>

        <button
    onclick="window.print();"
    class="btn btn-primary">

    <i class="bi bi-printer"></i>

    Print

</button>

    </div>

</div>

</div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>