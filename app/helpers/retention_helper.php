<?php

/*
|--------------------------------------------------------------------------
| Calculate Retention Dates
|--------------------------------------------------------------------------
|
| Determines the first retention review date and the maximum
| retention expiry date based on the archive date.
|
*/

function calculateRetentionDates(string $archivedAt): array
{
    $archiveDate = new DateTime($archivedAt);

    $reviewDate = clone $archiveDate;
    $reviewDate->modify('+' . ARCHIVE_MIN_YEARS . ' years');

    $expiryDate = clone $archiveDate;
    $expiryDate->modify('+' . ARCHIVE_MAX_YEARS . ' years');

    return [
        'retention_review_at'  => $reviewDate->format('Y-m-d H:i:s'),
        'retention_expires_at' => $expiryDate->format('Y-m-d H:i:s'),
    ];
}