<?php

/*
|--------------------------------------------------------------------------
| Backward-Compatible Missed Consultation Runner
|--------------------------------------------------------------------------
|
| The application has one canonical missed-consultation workflow:
| detect-missed-consultations.php
|
| This endpoint is retained because an existing IONOS cron entry may still
| call the older check-missed-consultations job. Delegating to the canonical
| script prevents the two jobs from applying conflicting workflow stages.
|
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/cron/detect-missed-consultations.php';
