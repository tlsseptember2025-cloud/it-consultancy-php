<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once CONFIG_PATH . '/database.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid Demo request.');
}

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        email,
        phone,
        company_name,
        company_domain,
        explore_options,
        confirmation_token,
        confirmation_expires_at,
        email_confirmed_at,
        status,
        rejection_reason,
        approved_at,
        approved_by,
        customer_confirmed_at,
        demo_created_at,
        demo_expires_at,
        created_at
    FROM demo_requests
    WHERE id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {
    die('Demo request not found.');
}

$exploreOptions = json_decode(
    $request['explore_options'] ?? '[]',
    true
);

if (!is_array($exploreOptions)) {
    $exploreOptions = [];
}

function formatDemoDate(?string $date): string
{
    if (empty($date)) {
        return '';
    }

    return date('d M Y, h:i A', strtotime($date));
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-12 col-xl-10">

            <div class="card shadow-sm">

                <!-- Header -->

                <div class="card-header bg-dark text-white">

                    <div class="d-flex justify-content-between align-items-center">

                        <h2 class="mb-0">
                            Demo Request #<?= (int)$request['id'] ?>
                        </h2>

                        <?php if ($request['status'] === 'Confirmed'): ?>

                            <span class="badge bg-success">
                                Confirmed
                            </span>

                        <?php elseif ($request['status'] === 'Approved'): ?>

                            <span class="badge bg-primary">
                                Approved
                            </span>

                        <?php elseif ($request['status'] === 'Rejected'): ?>

                            <span class="badge bg-danger">
                                Rejected
                            </span>

                        <?php elseif ($request['status'] === 'Customer Confirmed'): ?>

                            <span class="badge bg-info">
                                Customer Confirmed
                            </span>

                        <?php elseif ($request['status'] === 'Demo Created'): ?>

                            <span class="badge bg-success">
                                Demo Created
                            </span>

                        <?php elseif ($request['status'] === 'Expired'): ?>

                            <span class="badge bg-secondary">
                                Expired
                            </span>

                        <?php else: ?>

                            <span class="badge bg-warning text-dark">
                                <?= htmlspecialchars($request['status']) ?>
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="card-body">

                    <!-- Request Information -->

                    <div class="row g-4">

                        <!-- Requester Details -->

                        <div class="col-md-6">

                            <div class="card h-100 border">

                                <div class="card-header">

                                    <strong>
                                        Requester Details
                                    </strong>

                                </div>

                                <div class="card-body">

                                    <p class="mb-3">

                                        <strong>Full Name:</strong><br>

                                        <?= htmlspecialchars(
                                            $request['full_name']
                                        ) ?>

                                    </p>

                                    <p class="mb-3">

                                        <strong>Email:</strong><br>

                                        <?= htmlspecialchars(
                                            $request['email']
                                        ) ?>

                                    </p>

                                    <p class="mb-3">

                                        <strong>Phone:</strong><br>

                                        <?= htmlspecialchars(
                                            $request['phone'] ?? ''
                                        ) ?: 'Not provided' ?>

                                    </p>

                                    <p class="mb-0">

                                        <strong>Email Confirmed:</strong><br>

                                        <?php if (!empty($request['email_confirmed_at'])): ?>

                                            <?= formatDemoDate(
                                                $request['email_confirmed_at']
                                            ) ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                Awaiting confirmation
                                            </span>

                                        <?php endif; ?>

                                    </p>

                                </div>

                            </div>

                        </div>


                        <!-- Company Details -->

                        <div class="col-md-6">

                            <div class="card h-100 border">

                                <div class="card-header">

                                    <strong>
                                        Company Details
                                    </strong>

                                </div>

                                <div class="card-body">

                                    <p class="mb-3">

                                        <strong>Company Name:</strong><br>

                                        <?= htmlspecialchars(
                                            $request['company_name'] ?? ''
                                        ) ?: 'Not provided' ?>

                                    </p>

                                    <p class="mb-3">

                                        <strong>Company Domain:</strong><br>

                                        <?= htmlspecialchars(
                                            $request['company_domain']
                                        ) ?>

                                    </p>

                                    <p class="mb-3">

                                        <strong>Request Date:</strong><br>

                                        <?= formatDemoDate(
                                            $request['created_at']
                                        ) ?>

                                    </p>

                                    <p class="mb-0">

                                        <strong>Current Status:</strong><br>

                                        <?php if ($request['status'] === 'Confirmed'): ?>

                                            <span class="badge bg-success">
                                                Confirmed
                                            </span>

                                        <?php elseif ($request['status'] === 'Approved'): ?>

                                            <span class="badge bg-primary">
                                                Approved
                                            </span>

                                        <?php elseif ($request['status'] === 'Rejected'): ?>

                                            <span class="badge bg-danger">
                                                Rejected
                                            </span>

                                        <?php elseif ($request['status'] === 'Customer Confirmed'): ?>

                                            <span class="badge bg-info">
                                                Customer Confirmed
                                            </span>

                                        <?php elseif ($request['status'] === 'Demo Created'): ?>

                                            <span class="badge bg-success">
                                                Demo Created
                                            </span>

                                        <?php elseif ($request['status'] === 'Expired'): ?>

                                            <span class="badge bg-secondary">
                                                Expired
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-warning text-dark">
                                                <?= htmlspecialchars(
                                                    $request['status']
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Areas to Explore -->

                    <div class="card border mt-4">

                        <div class="card-header">

                            <strong>
                                Areas to Explore
                            </strong>

                        </div>

                        <div class="card-body">

                            <?php if (empty($exploreOptions)): ?>

                                <span class="text-muted">
                                    No areas selected.
                                </span>

                            <?php else: ?>

                                <div class="d-flex flex-wrap gap-2">

                                    <?php foreach ($exploreOptions as $option): ?>

                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars(
                                                (string)$option
                                            ) ?>
                                        </span>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- Demo Lifecycle -->

                    <div class="card border mt-4">

                        <div class="card-header">

                            <strong>
                                Demo Lifecycle
                            </strong>

                        </div>

                        <div class="card-body">

                            <div class="row g-3">

                                <!-- Email Confirmed -->

                                <div class="col-md-4">

                                    <strong>
                                        Email Confirmed
                                    </strong>

                                    <div class="mt-2">

                                        <?php if (!empty($request['email_confirmed_at'])): ?>

                                            <span class="badge bg-success">
                                                Confirmed
                                            </span>

                                            <div class="text-muted small mt-1">
                                                <?= formatDemoDate(
                                                    $request['email_confirmed_at']
                                                ) ?>
                                            </div>

                                        <?php else: ?>

                                            <span class="badge bg-warning text-dark">
                                                Awaiting Confirmation
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- Approved -->

                                <div class="col-md-4">

                                    <strong>
                                        Approved
                                    </strong>

                                    <div class="mt-2">

                                        <?php if (!empty($request['approved_at'])): ?>

                                            <span class="badge bg-success">
                                                Approved
                                            </span>

                                            <div class="text-muted small mt-1">
                                                <?= formatDemoDate(
                                                    $request['approved_at']
                                                ) ?>
                                            </div>

                                        <?php elseif ($request['status'] === 'Rejected'): ?>

                                            <span class="badge bg-danger">
                                                Rejected
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-warning text-dark">
                                                Awaiting Approval
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- Customer Confirmed -->

                                <div class="col-md-4">

                                    <strong>
                                        Customer Confirmed
                                    </strong>

                                    <div class="mt-2">

                                        <?php if (!empty($request['customer_confirmed_at'])): ?>

                                            <span class="badge bg-success">
                                                Confirmed
                                            </span>

                                            <div class="text-muted small mt-1">
                                                <?= formatDemoDate(
                                                    $request['customer_confirmed_at']
                                                ) ?>
                                            </div>

                                        <?php elseif ($request['status'] === 'Rejected'): ?>

                                            <span class="badge bg-secondary">
                                                Not Applicable
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-warning text-dark">
                                                Awaiting Confirmation
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- Demo Created -->

                                <div class="col-md-4">

                                    <strong>
                                        Demo Created
                                    </strong>

                                    <div class="mt-2">

                                        <?php if (!empty($request['demo_created_at'])): ?>

                                            <span class="badge bg-success">
                                                Created
                                            </span>

                                            <div class="text-muted small mt-1">
                                                <?= formatDemoDate(
                                                    $request['demo_created_at']
                                                ) ?>
                                            </div>

                                        <?php elseif ($request['status'] === 'Rejected'): ?>

                                            <span class="badge bg-secondary">
                                                Not Applicable
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-secondary">
                                                Not Created
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- Demo Expires -->

                                <div class="col-md-4">

                                    <strong>
                                        Demo Expires
                                    </strong>

                                    <div class="mt-2">

                                        <?php if (!empty($request['demo_expires_at'])): ?>

                                            <span class="badge bg-info">
                                                Active Until
                                            </span>

                                            <div class="text-muted small mt-1">
                                                <?= formatDemoDate(
                                                    $request['demo_expires_at']
                                                ) ?>
                                            </div>

                                        <?php elseif ($request['status'] === 'Rejected'): ?>

                                            <span class="badge bg-secondary">
                                                Not Applicable
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-secondary">
                                                Not Started
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- Approved By -->

                                <div class="col-md-4">

                                    <strong>
                                        Approved By
                                    </strong>

                                    <div class="mt-2">

                                        <?php if (!empty($request['approved_by'])): ?>

                                            <span class="badge bg-success">
                                                <?= htmlspecialchars(
                                                    $request['approved_by']
                                                ) ?>
                                            </span>

                                        <?php elseif ($request['status'] === 'Rejected'): ?>

                                            <span class="badge bg-secondary">
                                                Not Applicable
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-warning text-dark">
                                                Awaiting Approval
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Rejection Reason -->

                    <?php if (!empty($request['rejection_reason'])): ?>

                        <div class="alert alert-danger mt-4 mb-0">

                            <strong>
                                Rejection Reason:
                            </strong>

                            <div class="mt-1">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $request['rejection_reason']
                                    )
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- Actions -->

                    <div class="d-flex justify-content-center gap-2 mt-4">

                        <?php if ($request['status'] === 'Confirmed'): ?>

                            <a
                                href="?page=approve-demo-request&id=<?= (int)$request['id'] ?>"
                                class="btn btn-success">

                                Approve Demo

                            </a>

                            <a
                                href="?page=reject-demo-request&id=<?= (int)$request['id'] ?>"
                                class="btn btn-danger">

                                Reject Demo

                            </a>

                        <?php endif; ?>

                        <a
                            href="?page=demo-requests"
                            class="btn btn-secondary">

                            Back to Demo Requests

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>