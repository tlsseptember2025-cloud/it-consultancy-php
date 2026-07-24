<?php if (isDemo()): ?>

<div class="bg-warning text-dark text-center py-2 small">

    <strong>Demo Version</strong>

    | This is a demonstration of the software.

    | Data may be reset periodically.

</div>

<?php endif; ?>

<?php if (shouldShowBackupReminder()): ?>

<div class="bg-danger text-white text-center py-2 small">

    <strong>⚠ Development Reminder</strong>

    | Today is <?= date('l') ?>

    | Today's scheduled backup is still pending.

    <?php if (getLastBackupDate()): ?>

        | Last Backup:
        <strong><?= getLastBackupDate(); ?></strong>

    <?php else: ?>

        | <strong>No backups found.</strong>

    <?php endif; ?>

    |

    <a href="?page=backup"
       class="text-white fw-bold text-decoration-underline">

        Create Backup

    </a>

</div>

<?php endif; ?>