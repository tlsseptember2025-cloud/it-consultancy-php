<?php
// CSRF protection for all state-changing POST requests.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}


require_once HELPER_PATH . '/auth.php';

$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();
    require_once CONFIG_PATH . '/demo-database.php';

    $proposalPdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}
    require_once CONFIG_PATH . '/database.php';

    $proposalPdo = $pdo;

} else {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}

if ($isDemoAdmin) {

    $stmt = $proposalPdo->prepare("
        SELECT
            r.proposal,
            r.quoted_price
        FROM requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE r.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
    ");

    $stmt->execute([
        $requestId,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $proposalPdo->prepare("
        SELECT
            proposal,
            quoted_price
        FROM requests
        WHERE id = ?
    ");

    $stmt->execute([$requestId]);
}

$request = $stmt->fetch();

if (!$request) {
    die('Request not found or access denied.');
}

$isRevision = !empty($request['proposal']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $proposalText = trim($_POST['proposal_text'] ?? '');
    $price = $_POST['proposed_price'] ?? '';

    if ($proposalText === '' || $price === '') {
        die('Proposal details and proposed price are required.');
    }

    if ($isDemoAdmin) {

        $stmt = $proposalPdo->prepare("
            UPDATE requests r
            INNER JOIN customers c
                ON c.id = r.customer_id
            INNER JOIN services s
                ON s.id = r.service_id
            SET
                r.proposal = ?,
                r.quoted_price = ?,
                r.workflow_stage = 'Proposal Draft'
            WHERE r.id = ?
              AND c.demo_tenant_id = ?
              AND c.is_demo_account = 1
              AND s.demo_tenant_id = ?
              ");

        $stmt->execute([
            $proposalText,
            $price,
            $requestId,
            $demoTenantId,
            $demoTenantId
        ]);

    } else {

        $stmt = $proposalPdo->prepare("
            UPDATE requests
            SET
                proposal = ?,
                quoted_price = ?,
                workflow_stage = 'Proposal Draft'
            WHERE id = ?
        ");

        $stmt->execute([
            $proposalText,
            $price,
            $requestId
        ]);
    }

    if (!$stmt) {
        die('Unable to save proposal.');
    }

    header('Location: ?page=requests');
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>
            <?= $isRevision ? 'Revise Proposal' : 'Create Proposal' ?>
        </h2>

        <form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">

                <label class="form-label">
                    Proposed Price
                </label>

                <input
                    type="number"
                    step="0.01"
                    name="proposed_price"
                    value="<?= htmlspecialchars($request['quoted_price'] ?? '') ?>"
                    class="form-control"
                    required>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Proposal Details
                </label>

                <textarea
                    name="proposal_text"
                    class="form-control"
                    rows="8"
                    required><?=
                    htmlspecialchars($request['proposal'] ?? '')?>
                </textarea>

            </div>

            <button
                type="submit"
                class="btn btn-primary">

                <?= $isRevision ? 'Update Proposal' : 'Save Proposal' ?>

            </button>

            <a
                        href="?page=requests"
                        class="btn btn-secondary ms-2">

                        Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>