<?php
session_start();

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../routes.php';
require_once __DIR__ . '/../app/helpers/slot_generator.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ensureConsultationSlots($pdo);
ensureServiceSlots($pdo);