<?php
session_start();

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('VIEW_PATH', APP_PATH . '/views');
define('HELPER_PATH', APP_PATH . '/helpers');

require_once CONFIG_PATH . '/settings.php';
require_once HELPER_PATH . '/demo_helper.php';
require_once ROOT_PATH . '/routes.php';
require_once HELPER_PATH . '/slot_generator.php';

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