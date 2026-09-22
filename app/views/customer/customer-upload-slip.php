<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Customer Type
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);
$isMainCustomer = isset($_SESSION['customer']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainCustomer && !$isDemoCustomer) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    if (!isset($demoPdo)) {
        require_once CONFIG_PATH . '/demo-database.php';
    }

    $customerPdo = $demoPdo;

    $customerId = (int) $_SESSION['demo_customer']['id'];

} else {

    require_once CONFIG_PATH . '/database.php';

    $customerPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

if ($requestId <= 0) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Customer Request
|--------------------------------------------------------------------------
|
| Demo customers are restricted to their own Demo tenant.
|
*/

if ($isDemoCustomer) {

    $stmt = $customerPdo->prepare("
        SELECT
            requests.id,
            services.title,
            requests.quoted_price,
            requests.workflow_stage
        FROM requests
        JOIN services
            ON services.id = requests.service_id
        JOIN customers
            ON customers.id = requests.customer_id
        WHERE requests.customer_id = ?
          AND requests.id = ?
          AND customers.demo_tenant_id = ?
          AND customers.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $customerId,
        $requestId,
        (int) $_SESSION['demo_customer']['demo_tenant_id']
    ]);

} else {

    $stmt = $customerPdo->prepare("
        SELECT
            requests.id,
            services.title,
            requests.quoted_price,
            requests.workflow_stage
        FROM requests
        JOIN services
            ON services.id = requests.service_id
        WHERE requests.customer_id = ?
          AND requests.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $customerId,
        $requestId
    ]);
}


$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Check Payment Required Suspension
|--------------------------------------------------------------------------
|
| A suspended customer is allowed to use payment upload only when
| "Payment Required" is an active suspension reason.
|
| Active customers may continue using the normal payment-slip workflow.
|
*/

if (
    ($isDemoCustomer || $isMainCustomer) &&
    (
        ($isDemoCustomer && ($_SESSION['demo_customer']['status'] ?? 'Active') === 'Suspended') ||
        ($isMainCustomer && ($_SESSION['customer']['status'] ?? 'Active') === 'Suspended')
    )
) {

    $suspensionStmt = $customerPdo->prepare("
        SELECT id
        FROM customer_suspensions
        WHERE customer_id = ?
          AND reason = 'Payment Required'
          AND active = 1
        LIMIT 1
    ");

    $suspensionStmt->execute([
        $customerId
    ]);

    if (!$suspensionStmt->fetch()) {

        header('Location: ?page=customer-suspension-chat');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Form Processing
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Verify Submitted Request ID
    |--------------------------------------------------------------------------
    */

    $postedRequestId = (int) ($_POST['request_id'] ?? 0);

    if ($postedRequestId !== $requestId) {

        $error = 'Invalid payment request.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify No Existing Pending Slip
        |--------------------------------------------------------------------------
        */

        $checkStmt = $customerPdo->prepare("
            SELECT id
            FROM payment_slips
            WHERE request_id = ?
              AND status = 'Pending'
            LIMIT 1
        ");

        $checkStmt->execute([
            $requestId
        ]);

        if ($checkStmt->fetch()) {

            $error =
                'A pending payment receipt already exists for this request. Please wait for admin review.';

        } elseif (
            !isset($_FILES['slip']) ||
            $_FILES['slip']['error'] !== UPLOAD_ERR_OK
        ) {

            $error = 'Please select a valid payment receipt file.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | File Validation
            |--------------------------------------------------------------------------
            */

            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'pdf'
            ];

            $allowedMimeTypes = [
                'image/jpeg',
                'image/png',
                'application/pdf'
            ];

            $originalName = $_FILES['slip']['name'] ?? '';

            $fileExtension = strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

            $fileSize = (int) ($_FILES['slip']['size'] ?? 0);

            $maxFileSize = 5 * 1024 * 1024;


            if (!in_array($fileExtension, $allowedExtensions, true)) {

                $error =
                    'Only JPG, JPEG, PNG and PDF files are allowed.';

            } elseif ($fileSize <= 0) {

                $error =
                    'The selected payment receipt is empty.';

            } elseif ($fileSize > $maxFileSize) {

                $error =
                    'The payment receipt must not exceed 5 MB.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Verify Actual MIME Type
                |--------------------------------------------------------------------------
                */

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $actualMimeType = $finfo->file(
                    $_FILES['slip']['tmp_name']
                );

                if (!in_array($actualMimeType, $allowedMimeTypes, true)) {

                    $error =
                        'The uploaded file type is not allowed.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Generate Safe Stored Filename
                    |--------------------------------------------------------------------------
                    */

                    $safeExtension = $fileExtension;

                    $storedFileName =
                        bin2hex(random_bytes(16)) .
                        '_' .
                        time() .
                        '.' .
                        $safeExtension;


                    /*
                    |--------------------------------------------------------------------------
                    | Upload Directory
                    |--------------------------------------------------------------------------
                    */

                    $uploadDirectory =
                        ROOT_PATH .
                        '/public/uploads/slips/';


                    if (!is_dir($uploadDirectory)) {

                        if (!mkdir($uploadDirectory, 0755, true)) {

                            $error =
                                'Unable to create the payment receipt upload directory.';
                        }
                    }


                    if ($error === '') {

                        $targetPath =
                            $uploadDirectory .
                            $storedFileName;


                        /*
                        |--------------------------------------------------------------------------
                        | Move Uploaded File
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !move_uploaded_file(
                                $_FILES['slip']['tmp_name'],
                                $targetPath
                            )
                        ) {

                            $error =
                                'Unable to save the payment receipt. Please try again.';

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Save Payment Slip
                            |--------------------------------------------------------------------------
                            */

                            try {

                                $customerPdo->beginTransaction();


                                /*
                                |--------------------------------------------------------------------------
                                | Insert Payment Slip
                                |--------------------------------------------------------------------------
                                */

                                $insertStmt = $customerPdo->prepare("
                                    INSERT INTO payment_slips
                                    (
                                        customer_id,
                                        request_id,
                                        file_name
                                    )
                                    VALUES (?, ?, ?)
                                ");

                                $insertStmt->execute([
                                    $customerId,
                                    $requestId,
                                    $storedFileName
                                ]);


                                /*
                                |--------------------------------------------------------------------------
                                | Update Request Workflow
                                |--------------------------------------------------------------------------
                                */

                                $updateStmt = $customerPdo->prepare("
                                    UPDATE requests
                                    SET workflow_stage = 'Payment Submitted'
                                    WHERE id = ?
                                      AND customer_id = ?
                                ");

                                $updateStmt->execute([
                                    $requestId,
                                    $customerId
                                ]);


                                /*
                                |--------------------------------------------------------------------------
                                | Record Payment Receipt Event
                                |--------------------------------------------------------------------------
                                */

                                RequestEventHelper::addCurrentUser(
                                    $customerPdo,
                                    $requestId,
                                    'PAYMENT_RECEIPT_UPLOADED',
                                    RequestEventHelper::TYPE_PAYMENT,
                                    'Payment Receipt Uploaded',
                                    'The customer uploaded a payment receipt for review.',
                                    true
                                );


                                /*
                                |--------------------------------------------------------------------------
                                | Commit
                                |--------------------------------------------------------------------------
                                */

                                $customerPdo->commit();


                                /*
                                |--------------------------------------------------------------------------
                                | Success
                                |--------------------------------------------------------------------------
                                */

                                if ($isDemoCustomer) {

                                    $_SESSION['demo_customer']['status'] =
                                        $_SESSION['demo_customer']['status'] ?? 'Active';

                                } else {

                                    $_SESSION['customer']['status'] =
                                        $_SESSION['customer']['status'] ?? 'Active';
                                }


                                $_SESSION['success'] =
                                    'Payment receipt uploaded successfully.';

                                header('Location: ?page=customer-requests');
                                exit;


                            } catch (Throwable $e) {

                                if ($customerPdo->inTransaction()) {
                                    $customerPdo->rollBack();
                                }


                                /*
                                |--------------------------------------------------------------------------
                                | Remove Uploaded File if Database Save Failed
                                |--------------------------------------------------------------------------
                                */

                                if (is_file($targetPath)) {
                                    @unlink($targetPath);
                                }


                                $error =
                                    'Unable to save the payment receipt. Please try again.';
                            }
                        }
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Display Success Message
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['success'])) {

    $success = $_SESSION['success'];

    unset($_SESSION['success']);
}


/*
|--------------------------------------------------------------------------
| Customer Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Upload Payment Receipt
        </h2>


        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($success)): ?>

            <div class="alert alert-success">

                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            enctype="multipart/form-data">


            <div class="mb-3">

                <label class="form-label">
                    Service
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $request['title'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    readonly>

            </div>


            <div class="mb-3">

                <label class="form-label">
                    Amount Due
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="AED <?= number_format(
                        (float) $request['quoted_price'],
                        2
                    ) ?>"
                    readonly>

                <div class="form-text">
                    Please make sure the payment receipt shows the full amount due.
                </div>

            </div>


            <input
                type="hidden"
                name="request_id"
                value="<?= (int) $request['id'] ?>">


            <div class="mb-3">

                <label class="form-label">
                    Payment Receipt
                </label>

                <input
                    type="file"
                    name="slip"
                    class="form-control"
                    accept=".jpg,.jpeg,.png,.pdf"
                    required>

                <div class="form-text">
                    Allowed formats: JPG, JPEG, PNG and PDF. Maximum size: 5 MB.
                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primary">

                Submit Payment

            </button>


            <a
                href="?page=customer-requests"
                class="btn btn-secondary ms-2">

                Cancel

            </a>

        </form>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>