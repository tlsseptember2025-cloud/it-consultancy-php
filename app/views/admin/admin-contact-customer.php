<?php

/** @var array $consultation */

require VIEW_PATH . '/layouts/header-admin.php';
require_once CONFIG_PATH . '/database.php';

?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-success text-white">

        <h4 class="mb-0">
            Approve Customer Contact
        </h4>

    </div>

    <div class="card-body">

        <p class="mb-0">
            Review the consultation details below. If appropriate, approve the assigned
            agent to contact the customer and provide any instructions required.
        </p>

    </div>

</div>

<!-- Customer + Service -->

<div class="row mb-4">

    <div class="col-md-6">

        <div class="card shadow-sm h-100">

            <div class="card-header">
                Customer Information
            </div>

            <div class="card-body">

                <p>
                    <strong>Name:</strong>
                    <?= htmlspecialchars($consultation['customer_name']) ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?= htmlspecialchars($consultation['email']) ?>
                </p>

                <p class="mb-0">
                    <strong>Phone:</strong>
                    <?= htmlspecialchars($consultation['phone']) ?>
                </p>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card shadow-sm h-100">

            <div class="card-header">
                Service Information
            </div>

            <div class="card-body">

                <p>
                    <strong>Service:</strong>
                    <?= htmlspecialchars($consultation['service_name']) ?>
                </p>

                <p class="mb-0">
                    <strong>Quoted Price:</strong>
                    AED <?= number_format($consultation['quoted_price'], 2) ?>
                </p>

            </div>

        </div>

    </div>

</div>

<!-- Current Appointment -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-secondary text-white">
        Current Appointment
    </div>

    <div class="card-body bg-light">

        <div class="row">

            <div class="col-md-3">

                <strong>Date</strong><br>

                <?= date('d M Y', strtotime($consultation['slot_date'])) ?>

            </div>

            <div class="col-md-3">

                <strong>Time</strong><br>

                <?= date('h:i A', strtotime($consultation['slot_time'])) ?>

            </div>

            <div class="col-md-3">

                <strong>Assigned Agent</strong><br>

                <?= htmlspecialchars($consultation['agent_name']) ?>

            </div>

            <div class="col-md-3">

                <strong>Meeting Method</strong><br>

                <?= !empty($consultation['consultation_method'])
                    ? htmlspecialchars($consultation['consultation_method'])
                    : 'Not Assigned'; ?>

            </div>

        </div>

    </div>

</div>

<!-- Review Details -->

<div class="row mb-4">

    <div class="col-md-6">

        <div class="card shadow-sm h-100">

            <div class="card-header bg-warning">
                Reason for Review
            </div>

            <div class="card-body">

                <?= !empty($consultation['incomplete_reason'])
                    ? nl2br(htmlspecialchars($consultation['incomplete_reason']))
                    : '<span class="text-muted">No reason provided.</span>'; ?>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card shadow-sm h-100">

            <div class="card-header bg-info text-white">
                Agent Notes
            </div>

            <div class="card-body">

                <?= !empty($consultation['completion_notes'])
                    ? nl2br(htmlspecialchars($consultation['completion_notes']))
                    : '<span class="text-muted">No notes available.</span>'; ?>

            </div>

        </div>

    </div>


    <form method="post">
        <div class="card shadow-sm mb-4">

            <div class="card-header bg-success text-white">
                Administrator Instructions
            </div>

            <div class="card-body">

                <div class="mb-3">

                    <label class="form-label">
                        Administrator Instructions to Assigned Agent
                    </label>

                    <textarea
                        name="admin_instruction"
                        class="form-control"
                        rows="5"
                        required
                        placeholder="Enter instructions for the assigned agent..."></textarea>

                </div>

                <div class="text-end">

                    <a href="?page=needs-admin-review"
                    class="btn btn-secondary">
                        Cancel
                    </a>

                    <button
                        type="submit"
                        name="approve_contact"
                        class="btn btn-success">
                        Approve Customer Contact
                    </button>

                </div>

            </div>

        </div>
    </form>

</div>

<?php require VIEW_PATH.'/layouts/footer.php'; ?>