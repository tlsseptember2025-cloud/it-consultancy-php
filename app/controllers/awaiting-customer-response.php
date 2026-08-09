<?php


if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}


require_once CONFIG_PATH . '/database.php';


$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.verification_email_count,
        r.customer_response_deadline,
        r.job_status,
        r.workflow_stage,
        c.name AS customer_name,
        s.title AS service_name
    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE
        r.workflow_stage IN (
            'Awaiting Customer Response',
            'Closure Agreement Sent'
        )

    ORDER BY
        r.customer_response_deadline ASC
");


$stmt->execute();


$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


require VIEW_PATH . '/admin/awaiting-customer-response.php';