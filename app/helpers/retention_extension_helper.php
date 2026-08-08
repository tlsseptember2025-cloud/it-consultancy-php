<?php

require_once CONFIG_PATH . '/retention.php';


/*
|--------------------------------------------------------------------------
| Extend Retention
|--------------------------------------------------------------------------
|
| Allows exactly one additional year at the 5-year or 6-year
| retention review, while never exceeding the 7-year maximum.
|
*/

function extendRetention(PDO $pdo, int $requestId): bool
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            archived_at,
            retention_review_at,
            retention_expires_at,
            retention_extension_years,
            legal_hold,
            workflow_stage
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
    | Basic validation
    |--------------------------------------------------------------------------
    */

    if ($request['workflow_stage'] !== WORKFLOW_STAGE_ARCHIVED) {
        return false;
    }

    if ((int) $request['legal_hold'] === 1) {
        return false;
    }

    if (empty($request['archived_at'])) {
        return false;
    }

    /*
|--------------------------------------------------------------------------
| Determine Retention Review
|--------------------------------------------------------------------------
*/

$archiveDate = new DateTime($request['archived_at']);
$now = new DateTime();

$currentReviewDate = !empty($request['retention_review_at'])
    ? new DateTime($request['retention_review_at'])
    : null;

$sixYearDate = clone $archiveDate;
$sixYearDate->modify('+6 years');

$sevenYearDate = clone $archiveDate;
$sevenYearDate->modify('+7 years');


/*
|--------------------------------------------------------------------------
| Current Extension Count
|--------------------------------------------------------------------------
*/

$extensionYears = (int) $request['retention_extension_years'];

if ($extensionYears >= 2) {
    return false;
}


/*
|--------------------------------------------------------------------------
| 5-Year Review → Extend to 6 Years
|--------------------------------------------------------------------------
*/

if (
    $extensionYears === 0
    && $currentReviewDate
    && $now >= $currentReviewDate
    && $now < $sixYearDate
) {

    $newExtensionYears = 1;

    $newReviewDate = clone $sixYearDate;
    $newExpiryDate = clone $sevenYearDate;
}


/*
|--------------------------------------------------------------------------
| 6-Year Review → Extend to 7 Years
|--------------------------------------------------------------------------
*/

elseif (
    $extensionYears === 1
    && $currentReviewDate
    && $now >= $currentReviewDate
    && $now < $sevenYearDate
) {

    $newExtensionYears = 2;

    $newReviewDate = clone $sevenYearDate;
    $newExpiryDate = clone $sevenYearDate;
}


/*
|--------------------------------------------------------------------------
| Not Eligible
|--------------------------------------------------------------------------
*/

else {
    return false;
}

    /*
    |--------------------------------------------------------------------------
    | Save extension
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            retention_review_at = ?,
            retention_expires_at = ?,
            retention_extension_years = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $newReviewDate->format('Y-m-d H:i:s'),
        $newExpiryDate->format('Y-m-d H:i:s'),
        $newExtensionYears,
        $requestId
    ]);

    return true;
}