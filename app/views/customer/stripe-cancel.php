<?php

require_once HELPER_PATH . '/auth.php';
requireCustomerLogin();

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <h2 class="mb-3">Payment Not Completed</h2>
        <p class="text-muted">
            No payment was completed. Your request remains available for payment.
        </p>
        <a href="?page=customer-requests" class="btn btn-primary">
            Back to My Requests
        </a>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
