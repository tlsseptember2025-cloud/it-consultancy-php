<?php

$pageTitle = 'Consultation Closure Agreement';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
$requestId = (int) ($_GET['request_id'] ?? 0);
if ($requestId <= 0) {
    die('Invalid request.');
}

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        s.title AS service_name
    FROM requests r
    INNER JOIN customers c
        ON c.id = r.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    WHERE r.id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

$errors = [];
$typedName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $typedName = trim(
    $_POST['closure_confirmation_name'] ?? ''
);

    $agreementAccepted = isset($_POST['agreement_accepted']);

    $errors = [];

    if ($typedName === '') {

        $errors[] = 'Please enter the confirmation name.';

    }

    if (!$agreementAccepted) {

        $errors[] = 'Please confirm the agreement before submitting.';

    }

    if (
        $typedName !== '' &&
        $typedName !== strtoupper($request['customer_name'])
    ) {

        $errors[] = 'Please type the customer name exactly as shown.';

    }

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM consultation_closure_agreements
            WHERE request_id = ?
            LIMIT 1
        ");

        $stmt->execute([$request['id']]);

        $existingAgreement = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingAgreement) {

            $errors[] =
                'A closure agreement has already been submitted for this request.';

        }

        if (empty($errors)) {

            $stmt = $pdo->prepare("
                INSERT INTO consultation_closure_agreements
                (
                    request_id,
                    customer_id,
                    typed_name,
                    agreement_accepted,
                    signed_at,
                    ip_address
                )
                VALUES
                (
                    ?, ?, ?, ?, NOW(), ?
                )
            ");

            $stmt->execute([
                $request['id'],
                $request['customer_id'],
                $typedName,
                1,
                $_SERVER['REMOTE_ADDR']
            ]);/*
|--------------------------------------------------------------------------
| Record Customer Closure Agreement Submitted Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $request['id'],
    RequestEventHelper::EVENT_CUSTOMER_CLOSURE_AGREEMENT_SUBMITTED,
    RequestEventHelper::TYPE_CONTACT,
    'Customer Closure Agreement Submitted',
    'The customer submitted and accepted the consultation closure agreement.',
    true
);

            

            $stmt = $pdo->prepare("
                UPDATE requests
                SET workflow_stage = ?
                WHERE id = ?
            ");

            $stmt->execute([
                'Closure Agreement Submitted',
                $request['id']
            ]);

            /*
             * PRG REDIRECT
             *
             * Submission was successful.
             * Redirect the customer to My Requests instead of
             * rendering the agreement page again.
             */
            $_SESSION['success'] =
    'Closure Agreement submitted successfully.';

header(
    'Location: ?page=customer-requests'
);
exit;
        }
    }
}

require VIEW_PATH . '/customer/consultation-closure-agreement.php';