<?php

$pageTitle = 'Closure Agreements';

$stmt = $pdo->query("
    SELECT
        cca.*,
        c.name AS customer_name,
        s.title AS service_name
    FROM consultation_closure_agreements cca
    INNER JOIN requests r
        ON r.id = cca.request_id
    INNER JOIN customers c
        ON c.id = cca.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    WHERE cca.status = 'Pending'
    ORDER BY cca.signed_at DESC
");

$agreements = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/admin/closure-agreements.php';