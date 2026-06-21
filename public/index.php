<?php
session_start();

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../routes.php';
require_once __DIR__ . '/../app/helpers/slot_generator.php';

ensureConsultationSlots($pdo);
ensureServiceSlots($pdo);