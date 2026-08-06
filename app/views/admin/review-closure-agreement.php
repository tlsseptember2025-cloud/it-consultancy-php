<?php require VIEW_PATH . '/layouts/header-admin.php'; ?>

<div class="container mt-4">

<form method="post">

    <h2>Review Closure Agreement</h2>

    <?php if (!empty($errors)): ?>

    <div class="alert alert-danger">

        <strong>Please correct the following:</strong>

        <ul class="mb-0 mt-2">

            <?php foreach ($errors as $error): ?>

                <li><?= htmlspecialchars($error) ?></li>

            <?php endforeach; ?>

        </ul>

    </div>

<?php endif; ?>

    <div class="card mb-4">

    <div class="card-header bg-primary text-white">

        <strong>Request Information</strong>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-4">

                <strong>Request Number</strong><br>

                #<?= $agreement['request_number'] ?>

            </div>

            <div class="col-md-4">

                <strong>Customer</strong><br>

                <?= htmlspecialchars($agreement['customer_name']) ?>

            </div>

            <div class="col-md-4">

                <strong>Service</strong><br>

                <?= htmlspecialchars($agreement['service_name']) ?>

            </div>

        </div>

    </div>

</div>

<div class="card mb-4">

    <div class="card-header bg-primary text-white">

        <strong>Customer Agreement</strong>

    </div>

    <div class="card-body">

        <p>

            The customer confirmed the following agreement before requesting
            consultation closure.

        </p>

        <ul>

            <li>
                This consultation request will be permanently closed.
            </li>

            <li>
                Any scheduled consultation associated with this request
                will be cancelled.
            </li>

            <li>
                Any future consultation will require a new request.
            </li>

            <li>
                The consultation history will remain available for
                administrative and support purposes.
            </li>

        </ul>

    </div>

</div>

<div class="card mb-4">

    <div class="card-header bg-primary text-white">

        <strong>Agreement Details</strong>

    </div>

    <div class="card-body">

        <div class="row mb-4">

            <div class="col-md-6">

                <strong>Confirmation Name</strong>

                <div class="form-control bg-light mt-2">

                    <?= htmlspecialchars($agreement['typed_name']) ?>

                </div>

            </div>

            <div class="col-md-6">

                <strong>Agreement Accepted</strong>

                <div class="form-control bg-light mt-2">

                    <?= $agreement['agreement_accepted'] ? 'Yes' : 'No' ?>

                </div>

            </div>

        </div>

        <div class="row">

            <div class="col-md-6">

                <strong>Signed At</strong>

                <div class="form-control bg-light mt-2">

                    <?= date('d M Y H:i:s', strtotime($agreement['signed_at'])) ?>

                </div>

            </div>

            <div class="col-md-6">

                <strong>IP Address</strong>

                <div class="form-control bg-light mt-2">

                    <?= htmlspecialchars($agreement['ip_address']) ?>

                </div>

            </div>

        </div>

    </div>

</div>

<div class="card mb-4">

    <div class="card-header bg-primary text-white">

        <strong>Administrator Decision</strong>

    </div>

    <div class="card-body">

        <div class="alert alert-warning">

            <strong>Review Required</strong>

            <br><br>

            Before approving this consultation closure, verify that:

            <ul class="mb-0 mt-2">

                <li>The customer identity has been confirmed.</li>

                <li>The agreement information is complete.</li>

                <li>The confirmation name matches the customer record.</li>

                <li>No outstanding consultation actions remain.</li>

            </ul>

        </div>

        <div class="form-group mb-4">

    <label>

        <strong>Decision</strong>

    </label>

    <div class="form-check mt-2">

        <input
            class="form-check-input"
            type="radio"
            name="decision"
            id="approve"
            value="Approved">

        <label
            class="form-check-label"
            for="approve">

            Approve Consultation Closure

        </label>

    </div>

    <div class="form-check">

        <input
            class="form-check-input"
            type="radio"
            name="decision"
            id="reject"
            value="Rejected">

        <label
            class="form-check-label"
            for="reject">

            Reject Consultation Closure

        </label>

    </div>

</div>

        <div class="form-group">

            <label>

                <strong>Administrator Notes</strong>

            </label>

            <textarea
                class="form-control"
                name="admin_notes"
                rows="5"
                placeholder="Enter internal notes before making a decision..."></textarea>

        </div>

        <div class="mt-4 text-end">

    <button
        type="submit"
        class="btn btn-success">

        <i class="fas fa-check-circle"></i>

        Save Decision

    </button>

</div>

    </div>

</div>

</div>

</form>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>