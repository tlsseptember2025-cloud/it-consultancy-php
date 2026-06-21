<?php session_start(); ?>

<?php
require_once '../routes.php';
require_once __DIR__ . '/../app/helpers/slot_generator.php';

ensureConsultationSlots($pdo);
ensureServiceSlots($pdo);
?>