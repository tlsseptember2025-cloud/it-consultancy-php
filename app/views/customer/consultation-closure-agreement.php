<?php
require VIEW_PATH . '/layouts/header-customer.php';
?>

<div class="container mt-4">

    <form method="post"
      action="index.php?page=consultation-closure-agreement&request_id=<?= $request['id'] ?>">

        <h2 class="mb-4">
            Consultation Closure Agreement
        </h2>

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

                        #<?= $request['id'] ?>

                    </div>

                    <div class="col-md-4">

                        <strong>Customer</strong><br>

                        <?= htmlspecialchars($request['customer_name']) ?>

                    </div>

                    <div class="col-md-4">

                        <strong>Service</strong><br>

                        <?= htmlspecialchars($request['service_name']) ?>

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

                    I confirm that I have requested the closure of
                    Consultation Request
                    <strong>#<?= $request['id'] ?></strong>.

                </p>

                <p>

                    I understand that:

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
                        My consultation history will remain available for
                        administrative and support purposes.
                    </li>

                </ul>

            </div>

        </div>


        <div class="card mb-4">

            <div class="card-header bg-primary text-white">

                <strong>Customer Confirmation</strong>

            </div>

            <div class="card-body">

                
                <div class="form-group">

                <div class="alert alert-info mt-2 mb-3">

                            <strong>Confirmation Required</strong>

                            <br><br>

                            To confirm this request, type the customer name exactly as shown below.

                            <br><br>

                            <div class="bg-white border rounded p-3 mt-3 text-center">

                                <strong style="font-size:24px; letter-spacing:1px;">

                                    <?= strtoupper($request['customer_name']) ?>

                                </strong>

                            </div>

                        </div>


                    <label for="typed_name">

                        <strong>Confirmation Name</strong>

                    </label>

                   <input
                        type="text"
                        class="form-control"
                        id="typed_name"
                        name="typed_name"
                        value=""
                        placeholder="Type the customer name exactly as shown"
                        onpaste="return false"
                        oncopy="return false"
                        oncut="return false"
                        autocomplete="off"
                        ondrop="return false">

                    
                    <div class="alert alert-warning mt-4">

                        <p class="text-danger mb-3">

                            <strong>
                                ⚠ This action cannot be undone.
                            </strong>

                        </p>

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="agreement_accepted"
                                name="agreement_accepted">

                            <label
                                class="form-check-label"
                                for="agreement_accepted">

                                <strong>
                                    I confirm that I am the customer associated with Request #<?= $request['id'] ?>
                                </strong>

                                <br>

                                I understand that submitting this agreement will permanently
                                close Consultation Request #<?= $request['id'] ?> and any
                                scheduled consultation associated with it.

                            </label>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        
        <hr>

        <div class="d-flex justify-content-end">

            <a
                href="?page=customer-dashboard"
                class="btn btn-secondary me-2">

                Cancel

            </a>

            <button
                type="submit"
                id="submitAgreement"
                class="btn btn-success btn-lg"
                disabled>

                <i class="fas fa-check-circle"></i>

                Submit Closure Agreement

            </button>

        </div>

    </form>

    </div>

<script>

const fullName = document.getElementById('typed_name');
const agreement = document.getElementById('agreement_accepted');
const submitBtn = document.getElementById('submitAgreement');

function checkForm() {

    const hasName = fullName.value.trim() !== '';
    const hasAgreement = agreement.checked;

    submitBtn.disabled = !(hasName && hasAgreement);

}

fullName.addEventListener('input', checkForm);
agreement.addEventListener('change', checkForm);

checkForm();

fullName.addEventListener('keydown', function (e) {

    if (e.ctrlKey || e.metaKey) {

        const key = e.key.toLowerCase();

        if (key === 'c' || key === 'v' || key === 'x') {

            e.preventDefault();

        }

    }

});

</script>

<?php
require VIEW_PATH . '/layouts/footer.php';
?>