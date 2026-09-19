<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

require_once HELPER_PATH . '/auth.php';

if (isset($_SESSION['demo_user'])) {
    require_once CONFIG_PATH . '/demo-database.php';
    $pricingPdo = $demoPdo;
} else {
    require CONFIG_PATH . '/database.php';
    $pricingPdo = $pdo;
}

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

$stmt = $pricingPdo->prepare("
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

$stmt = $pricingPdo->prepare("
    DELETE FROM price_list
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=pricing');
exit;