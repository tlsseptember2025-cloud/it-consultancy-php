<?php

function hasBackupToday(): bool
{
    $backupDirectory = dirname(__DIR__, 2) . '/database/backups';

    if (!is_dir($backupDirectory)) {
        return false;
    }

    $today = date('Y-m-d');

    foreach (glob($backupDirectory . '/consultancy_*.sql') as $file) {

        if (strpos(basename($file), $today) !== false) {
            return true;
        }

    }

    return false;
}

function getLastBackupDate(): ?string
{
    $backupDirectory = dirname(__DIR__, 2) . '/database/backups';

    if (!is_dir($backupDirectory)) {
        return null;
    }

    $files = glob($backupDirectory . '/consultancy_*.sql');

    if (empty($files)) {
        return null;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    return date('F j, Y g:i A', filemtime($files[0]));
}