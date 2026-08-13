<?php

$pageTitle = 'Review Closure Agreement';

require_once APP_PATH . '/helpers/RequestEventHelper.php';

$agreementId = (int) ($_GET['agreement_id'] ?? 0);

if ($agreementId <= 0) {
    die('Invalid agreement.');
}

$stmt = $pdo->prepare("
    SELECT
        cca.*,
        r.id AS request_number,
        c.name AS customer_name,
        s.title AS service_name
    FROM consultation_closure_agreements AS cca
    INNER JOIN requests AS r
        ON r.id = cca.request_id
    INNER JOIN customers AS c
        ON c.id = cca.customer_id
    INNER JOIN services AS s
        ON s.id = r.service_id
    WHERE cca.id = ?
    LIMIT 1
");

$stmt->execute([$agreementId]);

$agreement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agreement) {
    die('Agreement not found.');
}

$errors = [];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $decision = $_POST['decision'] ?? '';

    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($decision === '') {

        $errors[] = 'Please select a review decision.';

    }

    if (
        $decision === 'Rejected' &&
        $adminNotes === ''
    ) {

        $errors[] = 'Administrator notes are required when rejecting a closure request.';

    }

    if (empty($errors)) {

    $stmt = $pdo->prepare("
        UPDATE consultation_closure_agreements
        SET
            status = ?,
            admin_notes = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $decision,
        $adminNotes,
        $agreementId
    ]);

    $stmt = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = ?
    WHERE id = ?
");

$stmt->execute([
    $decision === 'Approved'
        ? 'Closure Approved'
        : 'Closure Rejected',
    $agreement['request_id']
]);

/*
|--------------------------------------------------------------------------
| Record Closure Agreement Review Event
|--------------------------------------------------------------------------
*/

if ($decision === 'Approved') {

    RequestEventHelper::addCurrentUser(
        $pdo,
        (int) $agreement['request_id'],
        RequestEventHelper::EVENT_CLOSURE_AGREEMENT_APPROVED,
        RequestEventHelper::TYPE_CONSULTATION,
        'Closure Agreement Approved',
        'The administrator approved the customer’s Consultation Closure Agreement.',
        false
    );

} else {

    RequestEventHelper::addCurrentUser(
        $pdo,
        (int) $agreement['request_id'],
        RequestEventHelper::EVENT_CLOSURE_AGREEMENT_REJECTED,
        RequestEventHelper::TYPE_CONSULTATION,
        'Closure Agreement Rejected',
        'The administrator rejected the customer’s Consultation Closure Agreement.',
        false
    );

}

header('Location: index.php?page=closure-agreements&success=review-saved');
exit;

}

}

require VIEW_PATH . '/admin/review-closure-agreement.php';