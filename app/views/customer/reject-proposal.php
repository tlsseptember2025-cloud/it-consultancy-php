<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Proposal Rejected'
    WHERE id = ?
");

$stmt->execute([$requestId]);

/*
|--------------------------------------------------------------------------
| Record Proposal Rejected Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $requestId,
    RequestEventHelper::EVENT_PROPOSAL_REJECTED,
    RequestEventHelper::TYPE_PROPOSAL,
    'Proposal Rejected',
    'The customer rejected the proposal.',
    true
);

header('Location: ?page=customer-requests');
exit;