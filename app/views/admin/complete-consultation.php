<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = $_GET['id'] ?? 0;

$request = $pdo->prepare("
    SELECT
        requests.*,
        customers.name,
        customers.email
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    WHERE requests.id = ?
");

$request->execute([$id]);

$request = $request->fetch();

$stmt = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = 'Consultation Completed'
    WHERE id = ?
");

$stmt->execute([$id]);

/*
|--------------------------------------------------------------------------
| Record Consultation Completed Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    $id,
    RequestEventHelper::EVENT_CONSULTATION_COMPLETED,
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Completed',
    'The consultation has been completed.',
    true
);

sendEmail(
    $request['email'],
    'Consultation Completed',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        Your consultation has been completed.
    </p>

    <p>
        We are now preparing your quotation/proposal.
    </p>

    <p>
        You will receive another email once it is ready.
    </p>

    <p>
        IT Consultancy Team
    </p>
    "
);

header('Location: ?page=requests');
exit;