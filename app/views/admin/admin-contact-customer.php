<?php

/** @var array $consultation */
/** @var string $action */

require VIEW_PATH . '/layouts/header-admin.php';
require_once CONFIG_PATH . '/database.php';

?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-success text-white">

        <h4 class="mb-0">

            <?php

            switch ($action) {

                case 'wrong-number':
                    echo 'Incorrect Customer Phone Number';
                    break;

                case 'no-answer':
                    echo 'Customer Contact Required';
                    break;

                case 'new-consultation':
                    echo 'Customer Requested New Consultation';
                    break;

                case 'close-request':
                    echo 'Customer Requested Closure';
                    break;

                default:
                    echo 'Approve Customer Contact';

            }

            ?>

        </h4>

    </div>

    <div class="card-body">

        <?php if ($action === 'wrong-number'): ?>

<p class="mb-0">
    Review the request details below. The assigned agent reported that the customer's phone number is incorrect. Verify the customer's contact information and update the request as appropriate.
</p>

<?php elseif ($action === 'no-answer'): ?>

<?php if ($canRetryContact): ?>

<div class="alert alert-warning mb-0">

    <h5 class="mb-3">
        ⚠ Customer Could Not Be Reached
    </h5>

    <div class="row">

        <div class="col-md-4">

            <strong>Current Attempts</strong><br>

            <?= $contactAttempts ?> / <?= MAX_CONTACT_ATTEMPTS ?>

        </div>

        <div class="col-md-4">

            <strong>Remaining Attempts</strong><br>

            <?= MAX_CONTACT_ATTEMPTS - $contactAttempts ?>

        </div>

    </div>

    <hr>

    <p class="mb-0">

        If approved, the request will return to the
        <strong>Pending Contact</strong> queue so the assigned
        agent can attempt to contact the customer again.

    </p>

</div>

<?php else: ?>

<div class="alert alert-danger mb-0">

    <h5 class="mb-3">
        🚫 Maximum Contact Attempts Reached
    </h5>

    <div class="row">

        <div class="col-md-4">

            <strong>Current Attempts</strong><br>

            <?= $contactAttempts ?> / <?= MAX_CONTACT_ATTEMPTS ?>

        </div>

        <div class="col-md-4">

            <strong>Remaining Attempts</strong><br>

            0

        </div>

    </div>

    <hr>

    <p class="mb-0">

        The customer could not be reached after the maximum
        number of contact attempts.

        The next step is to send a
        <strong>Contact Verification Email</strong>.

    </p>

</div>

<?php endif; ?>

<p class="mb-0">
    Review the consultation details below. If appropriate, approve the assigned agent to contact the customer and provide any instructions required.
</p>

<?php endif; ?>

    </div>

</div>

<?php if ($action === 'no-answer'): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-warning">

        Customer Contact Attempts

    </div>

    <div class="card-body">

        <p>

            <strong>Current Attempts:</strong>

            <?= $contactAttempts ?>

            of

            <?= MAX_CONTACT_ATTEMPTS ?>

        </p>

        <?php if ($canRetryContact): ?>

            <button
                type="button"
                class="btn btn-primary">

                Approve Contact Attempt

            </button>

        <?php else: ?>

            <button
                type="button"
                class="btn btn-danger">

                Send Contact Verification Email

            </button>

        <?php endif; ?>

    </div>

</div>

<?php endif; ?>

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

<?php if ($action === ''): ?>
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

<?php endif ?>

<?php if ($action === 'wrong-number'): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-warning">
        Incorrect Customer Phone Number
    </div>

    <div class="card-body">

        <p>
            The assigned agent reported that the customer's phone number is incorrect.
        </p>

        <p class="mb-4">
            Choose one of the following actions:
        </p>

        

           <div id="wrong-number-actions" class="row g-3">

                <div class="col-md-6">
                    <button
                        type="button"
                        class="btn btn-outline-success w-100"
                        id="show-phone-update">
                        Update Customer Phone Number
                    </button>
                </div>

                <div class="col-md-6">
                    <button
                        type="button"
                        class="btn btn-outline-primary w-100"
                        id="request-phone-update">
                        Request Updated Phone Number
                    </button>
                </div>

            </div>
    

        <div id="phone-update-card" class="card border-success mt-4" style="display:none;">

    <div class="card-header bg-success text-white">
        Update Customer Phone Number
    </div>

    <div class="card-body">

        <div class="mb-3">
            <label class="form-label">Current Phone Number</label>

            <input
                type="text"
                class="form-control"
                value="<?= htmlspecialchars($consultation['phone']) ?>"
                readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">New Phone Number</label>

            <input
                type="text"
                class="form-control"
                name="new_phone">
        </div>

        <div class="mb-3">
            <label class="form-label">Administrator Notes</label>

            <textarea
                class="form-control"
                name="update_notes"
                rows="4"></textarea>
        </div>

        <div class="text-end">

            <button
                type="button"
                class="btn btn-secondary"
                id="hide-phone-update">
                Cancel
            </button>

            <button
                type="submit"
                class="btn btn-success">
                Save & Return to Customer Contact
            </button>

        </div>

    </div>

</div>

    </div>

</div>

<?php endif; ?>

</div>

<script>


document
    .getElementById('show-phone-update')
    .addEventListener('click', function () {

        document
            .getElementById('wrong-number-actions')
            .classList.add('d-none');

        document
            .getElementById('phone-update-card')
            .classList.remove('d-none');

    });

document
    .getElementById('hide-phone-update')
    .addEventListener('click', function () {

        document
            .getElementById('phone-update-card')
            .classList.add('d-none');

        document
            .getElementById('wrong-number-actions')
            .classList.remove('d-none');

    });
    
</script>

<?php require VIEW_PATH.'/layouts/footer.php'; ?>