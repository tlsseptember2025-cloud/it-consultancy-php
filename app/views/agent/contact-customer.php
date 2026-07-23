<?php
/** @var array $agent */

/** @var array $request */
?>

<?php require VIEW_PATH . '/layouts/header-agent.php'; ?>

<div class="container py-4">

    <h2>Contact Customer</h2>

    <div class="card shadow-sm">

    <div class="card-header">
        <strong>Customer Information</strong>
    </div>

    <div class="card-body">

        <p>
            <strong>Customer:</strong>
            <?= htmlspecialchars($request['customer_name']) ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= htmlspecialchars($request['email']) ?>
        </p>

        <p>
            <strong>Phone:</strong>
            <?= htmlspecialchars($request['phone']) ?>
        </p>

        <p>
            <strong>Service:</strong>
            <?= htmlspecialchars($request['service_name']) ?>
        </p>

    </div>

</div>

<div class="card shadow-sm mt-4">

    <div class="card-header">
        <strong>Administrator Instructions</strong>
    </div>

    <div class="card-body">

        <?= nl2br(htmlspecialchars($request['admin_instruction'])) ?>

    </div>

</div>

<div class="card shadow-sm mt-4">

    <div class="card-header">
        <strong>Customer Contact Outcome</strong>
    </div>

    <div class="card-body">

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Contact Result</label>

                <select name="contact_result" class="form-select" required>
                    <option value="">Select Result</option>
                    <option value="Customer Answered">Customer Answered</option>
                    <option value="No Answer">No Answer</option>
                    <option value="Wrong Number">Wrong Number</option>
                    <option value="Customer Requested Reschedule">Customer Requested Reschedule</option>
                </select>
            </div>

            <div id="customerDecisionSection" class="mb-3" style="display:none;">

    <label class="form-label">Customer Decision</label>

    <select name="customer_decision" id="customerDecision" class="form-select">

        <option value="">Select Decision</option>

        <option value="Continue Consultation">
            Continue Consultation
        </option>

        <option value="Close Request">
            Close Request
        </option>

    </select>

</div>

            <div class="mb-3">
                <label class="form-label">Agent Notes</label>

                <textarea
                    name="agent_notes"
                    class="form-control"
                    rows="5"
                    required></textarea>
            </div>

            <button type="submit" class="btn btn-success">
                Save Contact Result
            </button>

        </form>

    </div>

</div>

</div>

<script>

const contactResult = document.querySelector('select[name="contact_result"]');
const customerDecisionSection = document.getElementById('customerDecisionSection');

function toggleCustomerDecision() {

    if (contactResult.value === 'Customer Answered') {
        customerDecisionSection.style.display = 'block';
    } else {
        customerDecisionSection.style.display = 'none';
    }

}

contactResult.addEventListener('change', toggleCustomerDecision);

// Run once after the page is ready
document.addEventListener('DOMContentLoaded', function () {
    toggleCustomerDecision();
});

</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>