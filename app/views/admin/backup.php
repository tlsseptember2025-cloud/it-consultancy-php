<?php

require_once HELPER_PATH . '/email.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $backupDirectory =
    dirname(__DIR__, 3) . '/database/backups';

    if (!is_dir($backupDirectory)) {

        mkdir($backupDirectory, 0777, true);

    }

    $filename =
        $backupDirectory
        . '/consultancy_'
        . date('Y-m-d_H-i-s')
        . '.sql';

    $command =
        '"D:\\xampp\\mysql\\bin\\mysqldump.exe" '
        . '--user=root '
        . 'consultancy '
        . '> "'
        . $filename
        . '" 2>&1';

    $output = [];
    $returnCode = 0;

    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($filename) && filesize($filename) > 0) {

        $emailSent = sendEmail(

    'ramiwahdan2023@gmail.com',

    'WAHBIB Consultancy - Database Backup',

    '
        <h2>Database Backup</h2>

        <p>
            A new database backup has been created successfully.
        </p>

        <p>

            <strong>Date:</strong><br>'
            . date('F j, Y g:i A') .
        '</p>

        <p>

            The SQL backup is attached to this email.

        </p>
    ',

    [$filename]

);

$messageType = 'success';

$message =
    '<strong>Database backup created successfully.</strong><br><br>';

if ($emailSent) {

    $message .=
        '<span class="text-success">'
        . 'Backup emailed successfully.'
        . '</span><br><br>';

} else {

    $message .=
        '<span class="text-warning">'
        . 'Backup created successfully, but the email could not be sent.'
        . '</span><br><br>';

}

$message .=
    '<strong>Backup Location:</strong><br>'
    . 'database/backups/<br><br>'

    . '<strong>File:</strong><br>'
    . htmlspecialchars(basename($filename));

    } else {

        $messageType = 'danger';

        $message =
            '<strong>Backup failed.</strong><br><br>'
            . '<strong>Return Code:</strong> '
            . $returnCode
            . '<br><br>'
            . '<strong>Command:</strong><br>'
            . htmlspecialchars($command)
            . '<br><br>'
            . '<strong>Output:</strong><br>'
            . nl2br(htmlspecialchars(implode("\n", $output)));

    }

}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">

            Database Backup

        </h2>

        <?php if ($message): ?>

            <div class="alert alert-<?= $messageType ?>">

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