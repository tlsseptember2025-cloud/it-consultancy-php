<?php

function addContactHistory(
    PDO $pdo,
    int $requestId,
    ?int $agentId,
    ?int $adminId,
    string $contactMethod,
    string $contactResult,
    ?string $notes = null
) {
    $sql = "
        INSERT INTO contact_history (
            request_id,
            agent_id,
            admin_id,
            contact_method,
            contact_result,
            notes
        )
        VALUES (
            :request_id,
            :agent_id,
            :admin_id,
            :contact_method,
            :contact_result,
            :notes
        )
    ";

    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':request_id'     => $requestId,
        ':agent_id'       => $agentId,
        ':admin_id'       => $adminId,
        ':contact_method' => $contactMethod,
        ':contact_result' => $contactResult,
        ':notes'          => $notes
    ]);
}