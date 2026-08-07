<?php
require VIEW_PATH . '/layouts/header-admin.php';
?>

<div class="container mt-4">

    <h2 class="mb-4">Complete Consultation Closure</h2>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">

            Request Information

        </div>

        <div class="card-body">

            <div class="row">

    <div class="col-md-6 mb-3">

        <strong>Request #</strong><br>

        <?= $request['id'] ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Customer</strong><br>

        <?= htmlspecialchars($request['customer_name']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Service</strong><br>

        <?= htmlspecialchars($request['service_name']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Current Workflow Stage</strong><br>

        <?= htmlspecialchars($request['workflow_stage']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Current Job Status</strong><br>

        <?= htmlspecialchars($request['job_status']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Created</strong><br>

        <?= date('d M Y H:i', strtotime($request['created_at'])) ?>

    </div>

</div>

        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">

            Approved Closure Agreement

        </div>

        <div class="card-body">

            <div class="row">

    <div class="col-md-6 mb-3">

        <strong>Status</strong><br>

        <?= htmlspecialchars($agreement['status']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Customer Typed Name</strong><br>

        <?= htmlspecialchars($agreement['typed_name']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Signed At</strong><br>

        <?= date('d M Y H:i', strtotime($agreement['signed_at'])) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Reviewed At</strong><br>

        <?= date('d M Y H:i', strtotime($agreement['reviewed_at'])) ?>

    </div>

    <div class="col-12">

        <strong>Administrator Notes</strong><br>

        <?= nl2br(htmlspecialchars($agreement['admin_notes'] ?: 'None')) ?>

    </div>

</div>

        </div>

    </div>

    <div class="card">

        <div class="card-header bg-primary text-white">

            Final Confirmation

        </div>

        <div class="card-body">

            <div class="alert alert-warning">

    <h5 class="mb-3">

        Final Confirmation

    </h5>

    <p>

        You are about to permanently complete this consultation.

    </p>

    <p>

        The following actions will be performed:

    </p>

    <ul>

        <li>Workflow Stage will change to <strong>Closed</strong>.</li>

        <li>Job Status will change to <strong>Closed</strong>.</li>

        <li>Completion date will be recorded.</li>

        <li>The request will move to the <strong>Closed Requests</strong> queue.</li>

        <li>Customer notification will be created.</li>

        <li>Agent notification will be created.</li>

        <li>Administrator activity will be recorded.</li>

        <li>The consultation can no longer be reopened through the normal workflow.</li>

    </ul>

</div>

<form method="POST">

    <div class="form-check mb-3">

    <input
        class="form-check-input"
        type="checkbox"
        id="confirmClosure"
        name="confirm_closure"
        required>

    <label
        class="form-check-label"
        for="confirmClosure">

        I confirm that this consultation is ready to be permanently closed.

    </label>

</div>


    <a
        href="index.php?page=approved-closures"
        class="btn btn-secondary">

        Cancel

    </a>

    <button
        type="submit"
        name="complete_closure"
        class="btn btn-primary btn-lg">

        Complete Consultation Closure

    </button>

</form>

        </div>

    </div>

</div>

<?php
require VIEW_PATH . '/layouts/footer.php';