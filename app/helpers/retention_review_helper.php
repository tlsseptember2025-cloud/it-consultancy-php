<?php

require_once CONFIG_PATH . '/retention.php';


/*
|--------------------------------------------------------------------------
| Get Requests Due for Retention Review
|--------------------------------------------------------------------------
|
| Finds archived requests whose 5-year retention review date
| has arrived, while excluding requests currently under Legal Hold.
|
*/

function getRetentionReviewRequests(PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            c.name AS customer_name,
            c.email,
            s.title AS service_title,
            a.name AS agent_name
        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id

        INNER JOIN services s
            ON s.id = r.service_id

        LEFT JOIN agents a
            ON a.id = r.agent_id

        WHERE r.workflow_stage = ?
          AND r.retention_review_at IS NOT NULL
          AND r.retention_review_at <= NOW()
          AND r.legal_hold = 0
          AND (
              r.retention_expires_at IS NULL
              OR r.retention_expires_at > NOW()
          )

        ORDER BY r.retention_review_at ASC
    ");

    $stmt->execute([
        WORKFLOW_STAGE_ARCHIVED
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}