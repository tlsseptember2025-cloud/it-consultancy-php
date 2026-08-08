<?php

/*
|--------------------------------------------------------------------------
| Release Legal Hold
|--------------------------------------------------------------------------
|
| Releases an active Legal Hold from an archived request.
| Releasing a Legal Hold does not delete the request or change
| its archived status.
|
*/

function releaseLegalHold(
    PDO $pdo,
    int $requestId,
    ?int $adminId = null
): bool {

    /*
    |--------------------------------------------------------------------------
    | Load Request
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            workflow_stage,
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
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($request['workflow_stage'] !== WORKFLOW_STAGE_ARCHIVED) {
        return false;
    }

    if ((int) $request['legal_hold'] !== 1) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Release Legal Hold
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            legal_hold = 0,
            legal_hold_released_at = NOW(),
            legal_hold_released_by = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $adminId,
        $requestId
    ]);


    return true;
}