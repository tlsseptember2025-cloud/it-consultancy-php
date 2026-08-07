<?php

require_once CONFIG_PATH . '/retention.php';

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
        SELECT id
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

    return count($requests);

}