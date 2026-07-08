<?php
session_start();

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../app/helpers/demo_helper.php';
require_once __DIR__ . '/../routes.php';
require_once __DIR__ . '/../app/helpers/slot_generator.php';

if (isDevelopment()) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

ini_set('log_errors', 1);
error_reporting(E_ALL);

ensureConsultationSlots($pdo);
ensureServiceSlots($pdo);