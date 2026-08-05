<?php require VIEW_PATH . '/layouts/header-admin.php'; ?>

<div class="container py-4">

   <h2>

        Review Cancellation Request

        <small class="text-muted">
            Request #<?= (int)$consultation['id']; ?>
        </small>

    </h2>

    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">

            Customer Information

        </div>

        <div class="card-body">

            <p>
                <strong>Customer:</strong>
                <?= htmlspecialchars($consultation['customer_name']) ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= htmlspecialchars($consultation['email']) ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?= htmlspecialchars($consultation['phone']) ?>
            </p>

            <hr>

            <p>
                <strong>Service:</strong>
                <?= htmlspecialchars($consultation['service_name']) ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?= htmlspecialchars($consultation['slot_date']) ?>
            </p>

            <p>
                <strong>Time:</strong>
                <?= htmlspecialchars($consultation['slot_time']) ?>
            </p>

            <p>
                <strong>Method:</strong>
                <?= htmlspecialchars($consultation['consultation_method']) ?>
            </p>

        </div>

    </div>

</div>

<div class="card shadow-sm mt-4">

    <div class="card-header bg-success text-white">

        Cancellation Review

    </div>

    <div class="card-body">

        <form method="POST">

            <div class="mb-3">

                 <div class="alert alert-warning">

                    <strong>Customer Cancellation Request</strong>

                    <hr>

                    The customer has requested to cancel the consultation.

                    Please review the request and contact the customer if additional clarification is required.

                    If the cancellation request is approved, the customer will receive an official cancellation form to complete before the consultation is cancelled.

                </div>

                <label class="form-label">

                    Administrator Notes

                </label>

                <textarea
                    name="response_notes"
                    class="form-control"
                    rows="5"
                    required></textarea>

            </div>

           <button
                type="submit"
                name="continue_consultation"
                class="btn btn-success">

                Continue Consultation

            </button>

            <button
                type="submit"
                name="approve_cancellation_request"
                class="btn btn-danger">

                Approve Cancellation Request

            </button>

        </form>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>