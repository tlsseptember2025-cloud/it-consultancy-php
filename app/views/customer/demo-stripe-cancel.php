<?php

require_once HELPER_PATH . '/auth.php';
requireCustomerLogin();
require_once CONFIG_PATH . '/demo-database.php';

if (!isset($_SESSION['demo_customer'])) {
    http_response_code(403);
    exit('Demo customer access required.');
}

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <h2 class="mb-3">Demo Payment Not Completed</h2>
        <p class="text-muted">
            No Demo payment was completed. Your request remains available for payment.
        </p>
        <a href="?page=customer-requests" class="btn btn-primary">
            Back to My Requests
        </a>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
