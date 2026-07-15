<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once HELPER_PATH . '/proposal.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    WHERE r.id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {

    $_SESSION['error'] = 'Request not found.';

    header('Location: ?page=requests');
    exit;
}

if (empty(trim($request['proposal']))) {

    $_SESSION['error'] = 'Please create the proposal before sending it.';

    header('Location: ?page=create-proposal&id=' . $id);
    exit;
}

if (empty($request['quoted_price'])) {

    $_SESSION['error'] = 'Please enter the quoted price before sending the proposal.';

    header('Location: ?page=create-proposal&id=' . $id);
    exit;
}


// =======================================
// Generate Proposal PDF
// =======================================

$pdfPath = dirname(__DIR__, 2)
    . '/storage/proposals/proposal_' . $id . '.pdf';

generateProposalPdf(
    $request,
    $pdfPath
);


$emailSent = sendEmail(
    $request['email'],
    'Proposal Ready',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        Your proposal is now ready for review.
    </p>

    <p>
        <strong>Service:</strong>
        {$request['service_title']}
    </p>

    <p>
        <strong>Proposal:</strong>
    </p>

    <p>
        " . nl2br($request['proposal']) . "
    </p>

    <p>
        <strong>Proposed Price:</strong>
        AED " . number_format($request['quoted_price'], 2) . "
    </p>

    <p>
        <strong>Important:</strong>
        Before proceeding, please review our
        <a href='https://ramiphp.com/rules-and-regulations' target='_blank'>
            IT Consultancy Rules & Regulations
        </a>.
    </p>

    <p>
        By accepting the proposal and continuing with the service,
        you acknowledge that you have read and agreed to these terms.
    </p>

    <p>
        <a
            href='http://ramiphp.com/?page=customer-login'
            style='
                background:#0d6efd;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '
        >
            Login Now
        </a>
    </p>

    <p>
        IT Consultancy Team
    </p>
    ",
    [$pdfPath]
);

if (!$emailSent) {

    $_SESSION['error'] = 'Failed to send the proposal email.';

    header('Location: ?page=admin-view-proposal&id=' . $id);
    exit;
}

createNotification(
    $pdo,
    'customer',
    $request['customer_id'],
    '📄 Proposal Ready',
    'Your proposal is ready for review.',
    '?page=view-proposal&id=' . $id
);

$update = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Proposal Sent',
        proposal_sent = 1
    WHERE id = ?
");

$update->execute([$id]);

$_SESSION['success'] = 'Proposal sent successfully.';

header('Location: ?page=admin-view-proposal&id=' . $id);
exit;