<?php

/**
 * Create a notification.
 *
 * Main-admin notifications may remain broadcast notifications by using
 * recipient_type="admin" and a NULL recipient_id.
 *
 * Demo-admin notifications must never be broadcast across the shared Demo
 * database. When an admin notification has no explicit recipient ID, the
 * helper resolves the current Demo tenant from the active Demo session.
 * For webhook/system calls without a session, callers can pass $demoTenantId.
 */
function createNotification(
    PDO $pdo,
    string $recipientType,
    ?int $recipientId,
    string $title,
    string $message,
    ?string $link = null,
    ?int $demoTenantId = null
): void {
    if ($recipientType === 'admin' && $recipientId === null) {
        if ($demoTenantId === null) {
            if (isset($_SESSION['demo_user'])) {
                $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);
            } elseif (isset($_SESSION['demo_customer'])) {
                $demoTenantId = (int) ($_SESSION['demo_customer']['demo_tenant_id'] ?? 0);
            } elseif (isset($_SESSION['demo_agent'])) {
                $demoTenantId = (int) ($_SESSION['demo_agent']['demo_tenant_id'] ?? 0);
            }
        }

        if ($demoTenantId !== null && $demoTenantId > 0) {
            $adminStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE demo_tenant_id = ?
                  AND is_demo_account = 1
                  AND is_super_admin = 0
                ORDER BY id ASC
            ");
            $adminStmt->execute([$demoTenantId]);

            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($adminIds)) {
                throw new RuntimeException(
                    'No Company Demo Admin exists for the notification tenant.'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO notifications
                (
                    recipient_type,
                    recipient_id,
                    title,
                    message,
                    link
                )
                VALUES
                (
                    ?, ?, ?, ?, ?
                )
            ");

            foreach ($adminIds as $adminId) {
                $stmt->execute([
                    $recipientType,
                    (int) $adminId,
                    $title,
                    $message,
                    $link
                ]);
            }

            return;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO notifications
        (
            recipient_type,
            recipient_id,
            title,
            message,
            link
        )
        VALUES
        (
            ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $recipientType,
        $recipientId,
        $title,
        $message,
        $link
    ]);
}
