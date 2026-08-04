<?php require VIEW_PATH . '/layouts/header-admin.php'; ?>

<div class="container py-4">

    <h2 class="mb-4">
        Awaiting Customer Response
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

        Customer Response

    </div>

    <div class="card-body">

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">

                    Response Method

                </label>

                <select
                    name="response_method"
                    class="form-select"
                    required>

                    <option value="">Select...</option>

                    <option value="Email">Email</option>
                    <option value="Phone">Phone</option>
                    <option value="WhatsApp">WhatsApp</option>
                    <option value="Other">Other</option>

                </select>

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Customer Decision

                </label>

                <select
                    name="customer_decision"
                    class="form-select"
                    required>

                    <option value="">Select...</option>

                    <option value="continue">
                        Continue Consultation
                    </option>

                    <option value="reschedule">
                        Reschedule Consultation
                    </option>

                    <option value="cancel">
                        Cancel Consultation
                    </option>

                </select>

            </div>

            <div class="mb-3">

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
                name="save_customer_response"
                class="btn btn-success">

                Save Response

            </button>

        </form>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>