<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Service Active',
        status = 'In Progress'
    WHERE id = ?
");

$stmt->execute([$id]);

/*
|--------------------------------------------------------------------------
| Record Service Started Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $id,
    RequestEventHelper::EVENT_SERVICE_STARTED,
    RequestEventHelper::TYPE_SERVICE,
    'Service Started',
    'The service has been started and is now in progress.',
    true
);

header('Location: ?page=requests');
exit;