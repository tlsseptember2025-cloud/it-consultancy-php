<?php

if (!isset($_SESSION['agent'])) {
    header('Location: ?page=login');
    exit;
}

$agent = $_SESSION['agent'];

$requestId = (int) ($_GET['request_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        r.*,

        c.name AS customer_name,
        c.email,
        c.phone,

        s.title AS service_name

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    WHERE
        r.id = ?
        AND cb.agent_id = ?
");

$stmt->execute([
    $requestId,
    $agent['id']
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contactResult = trim($_POST['contact_result'] ?? '');
    $agentNotes    = trim($_POST['agent_notes'] ?? '');

    if ($contactResult === '' || $agentNotes === '') {
        die('Please complete all required fields.');
    }

   $stmt = $pdo->prepare("
    UPDATE requests
    SET job_status = ?
    WHERE id = ?
");

$stmt->execute([
    $contactResult,
    $request['id']
]);

header('Location: ?page=customer-contact-approved&success=contact-saved');
exit;

}

require VIEW_PATH . '/agent/contact-customer.php';