<?php

/*
|--------------------------------------------------------------------------
| Place Legal Hold
|--------------------------------------------------------------------------
|
| Places an archived request under Legal Hold after the normal
| 7-year retention period has reached its final review point.
|
*/

function placeLegalHold(
    PDO $pdo,
    int $requestId,
    string $reason,
    ?int $adminId = null
): bool {

    $reason = trim($reason);

    if ($reason === '') {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Load Request
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            workflow_stage,
            retention_extension_years,
            retention_review_at,
            retention_expires_at,
            legal_hold
        FROM requests
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$requestId]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($request['workflow_stage'] !== WORKFLOW_STAGE_ARCHIVED) {
        return false;
    }

    if ((int) $request['legal_hold'] === 1) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Must Have Reached 7-Year Final Review
    |--------------------------------------------------------------------------
    */

    if ((int) $request['retention_extension_years'] !== 2) {
        return false;
    }

    if (empty($request['retention_review_at'])) {
        return false;
    }

    $now = new DateTime();

    $reviewDate = new DateTime(
        $request['retention_review_at']
    );

    if ($now < $reviewDate) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Place Legal Hold
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            legal_hold = 1,
            legal_hold_at = NOW(),
            legal_hold_reason = ?,
            legal_hold_released_at = NULL,
            legal_hold_released_by = NULL
        WHERE id = ?
    ");

    $stmt->execute([
        $reason,
        $requestId
    ]);


    return true;
}