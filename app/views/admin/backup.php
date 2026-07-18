<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $filename =
        dirname(__DIR__, 2)
        . '/database/backups/consultancy_'
        . date('Y-m-d_H-i-s')
        . '.sql';

    $command =
    '"D:\\xampp\\mysql\\bin\\mysqldump.exe" -u root consultancy > "'
    . $filename
    . '"';

    exec($command);

    $message = 'Database backup created successfully.';
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Database Backup
        </h2>

        <?php if ($message): ?>

            <div class="alert alert-success">

                <?= $message ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <button
                type="submit"
                class="btn btn-primary">

                Create Backup

            </button>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>