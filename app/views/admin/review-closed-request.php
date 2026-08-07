<?php
require VIEW_PATH . '/layouts/header-admin.php';
?>

<div class="container mt-4">

    <h2 class="mb-4">Review Closed Request</h2>

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

        <strong>Request Type</strong><br>

        <?= ucfirst(htmlspecialchars($request['review_type'])) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Description</strong><br>

        <?= nl2br(htmlspecialchars($request['description'])) ?>

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

        <strong>Created On</strong><br>

        <?= date('d M Y H:i', strtotime($request['created_at'])) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Completed On</strong><br>

        <?= !empty($request['completed_at'])
            ? date('d M Y H:i', strtotime($request['completed_at']))
            : '-' ?>

    </div>

</div>

        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">

            Customer Information

        </div>

        <div class="card-body">

            <div class="row">

    <div class="col-md-6 mb-3">

        <strong>Customer Name</strong><br>

        <?= htmlspecialchars($request['customer_name']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Email Address</strong><br>

        <?= htmlspecialchars($request['email']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Phone Number</strong><br>

        <?= htmlspecialchars($request['phone']) ?>

    </div>

</div>

        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">

            Assigned Agent

        </div>

        <div class="card-body">

            <div class="row">

    <div class="col-md-6 mb-3">

        <strong>Assigned Agent</strong><br>

        <?= !empty($request['agent_name'])
            ? htmlspecialchars($request['agent_name'])
            : 'Not Assigned' ?>

    </div>

</div>

        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">

            Service Information

        </div>

        <div class="card-body">

            <div class="row">

    <div class="col-md-6 mb-3">

        <strong>Service</strong><br>

        <?= htmlspecialchars($request['service_name']) ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Quoted Price</strong><br>

        <?= !empty($request['quoted_price'])
            ? 'AED ' . number_format($request['quoted_price'], 2)
            : 'Not Quoted' ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Consultation Reschedules</strong><br>

        <?= (int) $request['consultation_reschedules'] ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Service Reschedules</strong><br>

        <?= (int) $request['service_reschedules'] ?>

    </div>

</div>

        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">

            Contact Summary

        </div>

        <div class="card-body">

            <div class="row">

    <div class="col-md-6 mb-3">

        <strong>Contact Attempts</strong><br>

        <?= (int) $request['contact_attempts'] ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Contact Result</strong><br>

        <?= htmlspecialchars($request['contact_result'] ?: 'N/A') ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Verification Emails Sent</strong><br>

        <?= (int) $request['verification_email_count'] ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Customer Response Deadline</strong><br>

        <?= !empty($request['customer_response_deadline'])
            ? date('d M Y H:i', strtotime($request['customer_response_deadline']))
            : '-' ?>

    </div>

    <div class="col-md-6 mb-3">

    <strong>Outcome</strong><br>

    <?= htmlspecialchars($request['job_status']) ?>

</div>

    <div class="col-12 mb-3">

        <strong>Contact Notes</strong><br>

        <?= nl2br(htmlspecialchars($request['contact_notes'] ?: 'No notes available.')) ?>

    </div>

    <div class="col-12">

        <strong>Incomplete Reason</strong><br>

        <?= nl2br(htmlspecialchars($request['incomplete_reason'] ?: 'N/A')) ?>

    </div>

    

</div>

        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">

            Closure Summary

        </div>

        <div class="card-body">

            <div class="row">

    <div class="col-md-6 mb-3">

        <strong>Agreement Status</strong><br>

        <?= htmlspecialchars($agreement['status'] ?? '-') ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Customer Typed Name</strong><br>

        <?= htmlspecialchars($agreement['typed_name'] ?? '-') ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Agreement Signed</strong><br>

        <?= !empty($agreement['signed_at'])
            ? date('d M Y H:i', strtotime($agreement['signed_at']))
            : '-' ?>

    </div>

    <div class="col-md-6 mb-3">

        <strong>Reviewed On</strong><br>

        <?= !empty($agreement['reviewed_at'])
            ? date('d M Y H:i', strtotime($agreement['reviewed_at']))
            : '-' ?>

    </div>

    <div class="col-12">

        <strong>Administrator Notes</strong><br>

        <?= nl2br(htmlspecialchars($agreement['admin_notes'] ?? 'None')) ?>

    </div>

</div>

        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">

            Workflow Timeline

        </div>

        <div class="card-body">

            <div class="timeline">

    <ul class="list-group">

        <li class="list-group-item">

            ✅ <strong>Request Submitted</strong><br>

            <?= date('d M Y H:i', strtotime($request['created_at'])) ?>

        </li>

        <li class="list-group-item">

            ✅ <strong>Customer Contact Process Completed</strong><br>

            Result:
            <?= htmlspecialchars($request['contact_result']) ?>

        </li>

        <li class="list-group-item">

            ✅ <strong>Closure Agreement Signed</strong><br>

            <?= !empty($agreement['signed_at'])
                ? date('d M Y H:i', strtotime($agreement['signed_at']))
                : '-' ?>

        </li>

        <li class="list-group-item">

            ✅ <strong>Closure Agreement Reviewed</strong><br>

            <?= !empty($agreement['reviewed_at'])
                ? date('d M Y H:i', strtotime($agreement['reviewed_at']))
                : '-' ?>

        </li>

        <li class="list-group-item">

            ✅ <strong>Consultation Closed</strong><br>

            <?= date('d M Y H:i', strtotime($request['completed_at'])) ?>

        </li>

    </ul>

</div>

        </div>

    </div>

    <div class="mt-4">

        <a
            href="index.php?page=closed-requests"
            class="btn btn-secondary">

            Back to Closed Requests

        </a>

    </div>

</div>

<?php
require VIEW_PATH . '/layouts/footer.php';