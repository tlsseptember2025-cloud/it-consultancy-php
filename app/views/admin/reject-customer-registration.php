<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/email.php';


/*
|--------------------------------------------------------------------------
| Get Customer ID
|--------------------------------------------------------------------------
*/

$customerId = (int) ($_GET['id'] ?? 0);

if ($customerId <= 0) {

    $_SESSION['error'] = 'Invalid customer registration.';

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Customer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        registration_status
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $customerId
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$customer) {

    $_SESSION['error'] = 'Customer registration not found.';

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Only Pending Registrations Can Be Rejected
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


$error = '';


/*
|--------------------------------------------------------------------------
| Process Rejection
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rejectionReason = trim(
        $_POST['rejection_reason'] ?? ''
    );


    if ($rejectionReason === '') {

        $error = 'Please provide a reason for rejecting this registration.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Reject Customer
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE customers

            SET
                registration_status = 'Rejected',
                rejection_reason = ?,
                rejected_at = NOW(),
                approved_at = NULL

            WHERE
                id = ?
                AND registration_status = 'Pending Admin Approval'
        ");

        $stmt->execute([
            $rejectionReason,
            $customerId
        ]);


        if ($stmt->rowCount() !== 1) {

            $_SESSION['error'] =
                'Unable to reject the customer registration.';

            header('Location: ?page=customers');
            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Send Rejection Email
        |--------------------------------------------------------------------------
        */

        $subject = 'Customer Registration Update';

        $body = "
            <h2>Registration Update</h2>

            <p>
                Hello " . htmlspecialchars($customer['name']) . ",
            </p>

            <p>
                Thank you for your interest in
                " . COMPANY_NAME . ".
            </p>

            <p>
                After reviewing your registration,
                we are unable to approve your customer account
                at this time.
            </p>

            <hr>

            <p>
                <strong>Reason:</strong>
            </p>

            <div
                style='
                    border-left:4px solid #dc3545;
                    padding:12px;
                    background:#f8f9fa;
                '>
                " . nl2br(
                    htmlspecialchars($rejectionReason)
                ) . "
            </div>

            <hr>

            <p>
                If you believe this decision was made in error,
                or if you would like further clarification,
                please contact our support team.
            </p>

            <p>
                Kind regards,<br>
                <strong>" . COMPANY_NAME . "</strong>
            </p>
        ";


        $emailSent = sendEmail(
            $customer['email'],
            $subject,
            $body
        );


        /*
        |--------------------------------------------------------------------------
        | Admin Result
        |--------------------------------------------------------------------------
        */

        if ($emailSent) {

            $_SESSION['success'] =
                'Customer registration rejected successfully. Rejection email sent.';

        } else {

            $_SESSION['success'] =
                'Customer registration rejected successfully, but the rejection email could not be sent.';
        }


        header('Location: ?page=customers');
        exit;
    }
}


require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2 class="mb-0">
        Reject Customer Registration
    </h2>

    <a
        href="?page=review-customer-registration&id=<?= (int) $customer['id'] ?>"
        class="btn btn-secondary">

        Back

    </a>

</div>


<div class="card shadow-sm">

    <div class="card-header bg-danger text-white">

        <strong>
            Registration Rejection
        </strong>

    </div>


    <div class="card-body">

        <div class="alert alert-warning">

            You are rejecting the registration for:

            <strong>
                <?= htmlspecialchars($customer['name']) ?>
            </strong>

            <br>

            <?= htmlspecialchars($customer['email']) ?>

        </div>


        <?php if ($error): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="mb-4">

                <label
                    for="rejection_reason"
                    class="form-label">

                    <strong>
                        Reason for Rejection
                    </strong>

                </label>

                <textarea
                    id="rejection_reason"
                    name="rejection_reason"
                    class="form-control"
                    rows="5"
                    required
                    placeholder="Please explain why this registration is being rejected..."><?= htmlspecialchars($_POST['rejection_reason'] ?? '') ?></textarea>

                <div class="form-text">

                    This reason will be included in the email sent
                    to the customer.

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-danger"
                onclick="return confirm('Reject this customer registration?');">

                Reject Registration

            </button>


            <a
                href="?page=review-customer-registration&id=<?= (int) $customer['id'] ?>"
                class="btn btn-secondary ms-2">

                Cancel

            </a>

        </form>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>