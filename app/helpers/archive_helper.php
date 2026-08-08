<?php

require_once CONFIG_PATH . '/retention.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

/*
|--------------------------------------------------------------------------
| Archive Helper
|--------------------------------------------------------------------------
|
| Handles automatic request archiving and retention processing.
|
*/

function archiveEligibleRequests(PDO $pdo): int
{

    if (!AUTO_ARCHIVE_ENABLED) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            customer_id,
            agent_id,
            completed_at
        FROM requests
        WHERE workflow_stage = ?
          AND completed_at IS NOT NULL
          AND completed_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");

    $stmt->execute([
        WORKFLOW_STAGE_CLOSED,
        CLOSED_RETENTION_DAYS
    ]);

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $archivedCount = 0;

    foreach ($requests as $request) {

        $update = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = ?,
                archived_at = NOW()
            WHERE id = ?
        ");

        $update->execute([
            WORKFLOW_STAGE_ARCHIVED,
            $request['id']
        ]);

        RequestEventHelper::add(
            $pdo,
            $request['id'],
            'REQUEST_ARCHIVED',
            RequestEventHelper::TYPE_SYSTEM,
            'Request Automatically Archived',
            'Request was automatically archived after '
                . CLOSED_RETENTION_DAYS
                . ' days in Closed status.',
            RequestEventHelper::SOURCE_SYSTEM,
            null
        );

        $archivedCount++;
    }

    return $archivedCount;
}