<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require CONFIG_PATH . '/database.php';


/*
|--------------------------------------------------------------------------
| Get Customer ID
|--------------------------------------------------------------------------
*/

$customerId = (int) ($_GET['id'] ?? 0);

if ($customerId <= 0) {

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Customer Registration
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        company,
        notes,
        created_at,
        email_verified,
        registration_status,
        rejection_reason,
        approved_at,
        rejected_at
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $customerId
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$customer) {

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Only Pending Registrations Can Be Reviewed
|--------------------------------------------------------------------------
*/

if (
    $customer['registration_status']
    !== 'Pending Admin Approval'
) {

    $_SESSION['error'] =
        'This customer registration is not awaiting approval.';

    header('Location: ?page=customers');
    exit;
}


require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2 class="mb-0">
        Customer Registration Review
    </h2>

    <a
        href="?page=customers"
        class="btn btn-secondary">

        Back to Customers

    </a>

</div>


<div class="card shadow-sm">

    <div class="card-header bg-dark text-white">

        <strong>
            Registration Details
        </strong>

    </div>


    <div class="card-body">


        <!-- Customer Information -->

        <div class="row mb-4">

            <div class="col-md-6">

                <p>
                    <strong>Name:</strong><br>

                    <?= htmlspecialchars(
                        $customer['name']
                    ) ?>

                </p>

            </div>


            <div class="col-md-6">

                <p>
                    <strong>Email:</strong><br>

                    <?= htmlspecialchars(
                        $customer['email']
                    ) ?>

                </p>

            </div>


            <div class="col-md-6">

                <p>
                    <strong>Phone:</strong><br>

                    <?= htmlspecialchars(
                        $customer['phone']
                    ) ?>

                </p>

            </div>


            <div class="col-md-6">

                <p>
                    <strong>Company:</strong><br>

                    <?= htmlspecialchars(
                        $customer['company'] ?? ''
                    ) ?: '<span class="text-muted">Not provided</span>' ?>

                </p>

            </div>


            <div class="col-md-6">

                <p>
                    <strong>Registration Date:</strong><br>

                    <?= htmlspecialchars(
                        $customer['created_at']
                    ) ?>

                </p>

            </div>


            <div class="col-md-6">

                <p>
                    <strong>Email Verification:</strong><br>

                    <?php if ((int) $customer['email_verified'] === 1): ?>

                        <span class="badge bg-success">
                            Verified
                        </span>

                    <?php else: ?>

                        <span class="badge bg-danger">
                            Not Verified
                        </span>

                    <?php endif; ?>

                </p>

            </div>

        </div>


        <hr>


        <!-- Registration Status -->

        <div class="mb-4">

            <strong>
                Registration Status:
            </strong>

            <span class="badge bg-warning text-dark ms-2">

                <?= htmlspecialchars(
                    $customer['registration_status']
                ) ?>

            </span>

        </div>


        <!-- Notes -->

        <?php if (!empty($customer['notes'])): ?>

            <div class="mb-4">

                <strong>
                    Notes:
                </strong>

                <div class="border rounded p-3 mt-2 bg-light">

                    <?= nl2br(
                        htmlspecialchars(
                            $customer['notes']
                        )
                    ) ?>

                </div>

            </div>

        <?php endif; ?>


        <hr>


        <!-- Admin Actions -->

        <div class="mt-4">

            <h5 class="mb-3">
                Administrator Decision
            </h5>


            <div class="d-flex gap-2">


                <!-- Approve -->

                <a
                    href="?page=approve-customer-registration&id=<?= (int) $customer['id'] ?>"
                    class="btn btn-success"
                    onclick="return confirm('Approve this customer registration?');">

                    Approve Registration

                </a>


                <!-- Reject -->

                <a
                    href="?page=reject-customer-registration&id=<?= (int) $customer['id'] ?>"
                    class="btn btn-danger">

                    Reject Registration

                </a>


                <!-- Back -->

                <a
                    href="?page=customers"
                    class="btn btn-secondary">

                    Cancel

                </a>

            </div>

        </div>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>