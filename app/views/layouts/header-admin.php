<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="<?= COMPANY_TAGLINE ?>">

    <meta name="author" content="<?= COMPANY_NAME ?>">

    <title><?= PRODUCT_NAME ?></title>

    <link
        rel="icon"
        type="image/png"
        href="/uploads/assets/favicon.png">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

</head>

<body>

<?php require __DIR__ . '/partials/demo-banner.php'; ?>
<?php require __DIR__ . '/partials/navbar-admin.php'; ?>

<div class="container py-4">

<?php require __DIR__ . '/partials/flash-messages.php'; ?>