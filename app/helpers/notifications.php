<?php

function createNotification(
    PDO $pdo,
    string $recipientType,
    ?int $recipientId,
    string $title,
    string $message,
    ?string $link = null
): void {

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