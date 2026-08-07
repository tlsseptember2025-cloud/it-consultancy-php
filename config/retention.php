<?php

/*
|--------------------------------------------------------------------------
| Closed Request Retention
|--------------------------------------------------------------------------
|
| Number of days a closed request remains in the Closed Requests queue
| before being automatically archived.
|
*/

define('CLOSED_RETENTION_DAYS', 90);


/*
|--------------------------------------------------------------------------
| Archive Retention
|--------------------------------------------------------------------------
|
| Minimum and maximum archive retention period.
|
*/

define('ARCHIVE_MIN_YEARS', 5);

define('ARCHIVE_MAX_YEARS', 7);


/*
|--------------------------------------------------------------------------
| Retention Review
|--------------------------------------------------------------------------
|
| Number of years to extend retention after each review.
|
*/

define('RETENTION_EXTENSION_YEARS', 1);

define('RETENTION_REQUIRES_ADMIN_APPROVAL', true);


/*
|--------------------------------------------------------------------------
| Legal Hold
|--------------------------------------------------------------------------
|
| Prevents automatic deletion while Legal Hold is active.
|
*/

define('LEGAL_HOLD_ENABLED', true);


/*
|--------------------------------------------------------------------------
| Automatic Archive
|--------------------------------------------------------------------------
|
| Enables or disables automatic archiving of closed requests.
|
*/

define('AUTO_ARCHIVE_ENABLED', true);


/*
|--------------------------------------------------------------------------
| Archive Workflow Stages
|--------------------------------------------------------------------------
*/

define('WORKFLOW_STAGE_CLOSED', 'Closed');

define('WORKFLOW_STAGE_ARCHIVED', 'Archived');

define('WORKFLOW_STAGE_RETENTION_REVIEW', 'Retention Review');

define('WORKFLOW_STAGE_LEGAL_HOLD', 'Legal Hold');