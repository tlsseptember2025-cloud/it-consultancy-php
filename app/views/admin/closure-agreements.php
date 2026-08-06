<?php require VIEW_PATH . '/layouts/header-admin.php'; ?>

<div class="container mt-4">

    <h2>Closure Agreements</h2>

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

<?php require VIEW_PATH . '/layouts/footer.php'; ?>