<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require CONFIG_PATH . '/database.php';

/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: ?page=pricing');
    exit;
}

/*
|--------------------------------------------------------------------------
| Check Pricing Exists
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM price_list
    WHERE id = ?
");

$stmt->execute([$id]);

if (!$stmt->fetch()) {
    header('Location: ?page=pricing');
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Pricing
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    DELETE FROM price_list
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=pricing');
exit;