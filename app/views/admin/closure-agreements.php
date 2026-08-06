<?php require VIEW_PATH . '/layouts/header-admin.php'; ?>

<div class="container mt-4">

    <h2>Pending Closure Agreements</h2>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'review-saved'): ?>

    <div class="alert alert-success" id="successMessage">

        <strong>Success!</strong>

        The closure agreement review has been completed successfully.

    </div>

<?php endif; ?>

    <div class="card">

    <div class="card-header bg-primary text-white">

        <strong>Submitted Closure Agreements</strong>

    </div>

    <div class="card-body">

        <?php if (empty($agreements)): ?>

            <div class="alert alert-info mb-0">

                No closure agreements have been submitted.

            </div>

        <?php else: ?>

            <table class="table table-striped table-hover">

                <thead>

                    <tr>

                        <th>Request</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Signed</th>
                        <th>Status</th>
                        <th>Action</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($agreements as $agreement): ?>

                    <tr>

                        <td>

                            #<?= $agreement['request_id'] ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($agreement['customer_name']) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($agreement['service_name']) ?>

                        </td>

                        <td>

                            <?= date('d M Y H:i', strtotime($agreement['signed_at'])) ?>

                        </td>

                        <td>

                            <span class="badge bg-warning text-dark">

                                Pending Review

                            </span>

                        </td>

                        <td>

                            <a
                                href="?page=review-closure-agreement&agreement_id=<?= $agreement['id'] ?>"
                                class="btn btn-primary btn-sm">

                                Review

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</div>

<script>

setTimeout(function () {

    const message = document.getElementById('successMessage');

    if (message) {

        message.style.transition = 'opacity 0.5s';

        message.style.opacity = '0';

        setTimeout(function () {

            message.remove();

        }, 500);

    }

}, 5000);

</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>