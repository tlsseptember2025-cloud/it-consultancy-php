<?php
// ======================================================
// Admin Proposal Details (Template)
// Replace your existing view-proposal.php with this file
// Adjust include paths/routes if required.
// ======================================================

if (!isset($_SESSION['user'])) {
    header('Location: ?page=public-login');
    exit;
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
    case 'Pending':   $statusClass='warning'; break;
    case 'Approved':  $statusClass='success'; break;
    case 'Completed': $statusClass='primary'; break;
    case 'Cancelled': $statusClass='danger'; break;
}

$statusIcon='bi-question-circle';
switch ($request['status']) {
    case 'Pending':   $statusIcon='bi-clock-history'; break;
    case 'Approved':  $statusIcon='bi-check-circle'; break;
    case 'Completed': $statusIcon='bi-check2-circle'; break;
    case 'Cancelled': $statusIcon='bi-x-circle'; break;
}

require APP_PATH.'/views/layouts/header-admin.php';
?>

<style>
@media print{
nav,footer,.btn,.demo-banner{display:none!important;}
.card{border:none!important;box-shadow:none!important;}
body{background:#fff;}
}
</style>

<div class="card shadow">

<div class="card-header bg-primary text-white">
    <h4 class="mb-1">Proposal Details</h4>
    <p class="mb-3 text-white-50">Request #<?= $request['id']; ?></p>

    <div class="row">
        <div class="col-md-4">
            <div class="small text-white-50"><i class="bi bi-hash"></i> Request ID</div>
            <div>#<?= $request['id']; ?></div>
        </div>
        <div class="col-md-4">
            <div class="small text-white-50"><i class="bi bi-calendar-event"></i> Created</div>
            <div><?= date('d M Y',strtotime($request['created_at'])); ?></div>
        </div>
        <div class="col-md-4">
            <div class="small text-white-50"><i class="bi bi-diagram-3"></i> Workflow Stage</div>
            <div><?= htmlspecialchars($request['workflow_stage']); ?></div>
        </div>
    </div>
</div>

<div class="card-body">

<h5><i class="bi bi-person-circle text-primary"></i> Customer Information</h5>
<div class="row mb-4">
<div class="col-md-6">
<div class="small text-muted">Customer</div>
<div class="fw-semibold fs-5"><?= htmlspecialchars($request['name']); ?></div>
</div>
<div class="col-md-6">
<div class="small text-muted">Email</div>
<div><i class="bi bi-envelope-fill text-primary"></i> <?= htmlspecialchars($request['email']); ?></div>
</div>
</div>

<div class="row mb-4">
<div class="col-md-6">
<div class="small text-muted">Phone</div>
<div><i class="bi bi-telephone-fill text-success"></i> <?= htmlspecialchars($request['phone']); ?></div>
</div>
<div class="col-md-6">
<div class="small text-muted">Company</div>
<div><i class="bi bi-building text-secondary"></i> <?= htmlspecialchars($request['company']); ?></div>
</div>
</div>

<hr>

<h5><i class="bi bi-tools text-primary"></i> Service Information</h5>

<div class="row mb-4">
<div class="col-md-6">
<div class="small text-muted">Service</div>
<div><?= htmlspecialchars($request['service_title']); ?></div>
</div>

<div class="col-md-6">
<div class="small text-muted">Status</div>
<div class="mt-2">
<span class="badge bg-<?= $statusClass; ?>">
<i class="bi <?= $statusIcon; ?>"></i>
<?= htmlspecialchars($request['status']); ?>
</span>
</div>
</div>
</div>

<hr>

<h5><i class="bi bi-chat-left-text text-primary"></i> Request Description</h5>
<div class="border rounded bg-light p-3 mb-4">
<?= nl2br(htmlspecialchars($request['description'])); ?>
</div>

<h5><i class="bi bi-cash-stack text-success"></i> Quotation</h5>
<div class="alert alert-success">
<div class="row">
<div class="col-md-6"><strong>Quoted Price</strong></div>
<div class="col-md-6 text-end">
<h3 class="mb-0">AED <?= number_format($request['quoted_price'],2); ?></h3>
</div>
</div>
</div>

<hr>

<h5><i class="bi bi-file-earmark-text text-primary"></i> Proposal</h5>

<div class="card border-secondary">
<div class="card-header bg-light">
<strong>Proposal Document</strong>
</div>

<div class="card-body">
<div class="mb-4">
<h5 class="mb-1">Proposal</h5>
<small class="text-muted">
Prepared for <?= htmlspecialchars($request['name']); ?>
</small>
</div>

<div class="border rounded bg-white p-4"
style="white-space:pre-wrap;line-height:1.9;font-size:15px;min-height:260px;">
<?php if(!empty(trim($request['proposal']))): ?>
<?= htmlspecialchars($request['proposal']); ?>
<?php else: ?>
<div class="text-muted">No proposal has been created yet.</div>
<?php endif; ?>
</div>

</div>
</div>

<hr>

<div class="d-flex justify-content-between">

<a href="?page=requests" class="btn btn-secondary">
<i class="bi bi-arrow-left"></i> Back
</a>

<div>
<a href="?page=create-proposal&id=<?= $request['id']; ?>" class="btn btn-warning me-2">
<i class="bi bi-pencil-square"></i> Edit Proposal
</a>

<a href="?page=send-proposal&id=<?= $request['id']; ?>"
       class="btn btn-success me-2">
        <i class="bi bi-send"></i>
        Send Proposal
    </a>

<button onclick="window.print();" class="btn btn-primary">
<i class="bi bi-printer"></i> Print
</button>
</div>

</div>

</div>
</div>

<?php require APP_PATH.'/views/layouts/footer.php'; ?>
