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

function getRetentionReviewRequests(
    PDO $pdo,
    string $search = '',
    int $limit = 10,
    int $offset = 0
): array
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
  AND (
      ? = ''
      OR r.id LIKE ?
      OR r.description LIKE ?
      OR c.name LIKE ?
      OR c.email LIKE ?
      OR s.title LIKE ?
      OR a.name LIKE ?
  )

ORDER BY r.retention_review_at ASC
LIMIT {$limit} OFFSET {$offset}
    ");

    $searchValue = '%' . $search . '%';

$stmt->execute([
    WORKFLOW_STAGE_ARCHIVED,
    $search,
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue
]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}