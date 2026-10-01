<?php

require_once HELPER_PATH . '/auth.php';
requireAdminLogin();

require_once CONFIG_PATH . '/database.php';


/*
|--------------------------------------------------------------------------
| Admin Context / Database
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}

$servicePdo = $pdo;
$demoTenantId = 0;

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';

    $servicePdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Service Request
|--------------------------------------------------------------------------
*/

$where = "
    WHERE r.id = ?
";

$params = [$id];

if ($isDemoAdmin) {
    $where .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
        AND s.demo_tenant_id = ?
        AND s.is_demo_account = 1
    ";

    $params[] = $demoTenantId;
    $params[] = $demoTenantId;
}

$stmt = $servicePdo->prepare("
    SELECT
        r.*,
        c.name,
        s.title AS service_title
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    {$where}
    LIMIT 1
");

$stmt->execute($params);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}


/*
|--------------------------------------------------------------------------
| Handle Rejection
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (
        $submittedToken === ''
        || !hash_equals($csrfToken, $submittedToken)
    ) {
        http_response_code(403);
        die('Invalid security token.');
    }

    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($reason === '') {
        die('Rejection reason is required.');
    }

    if (mb_strlen($reason) > 2000) {
        die('Rejection reason is too long.');
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Rejection
    |--------------------------------------------------------------------------
    */

    if (($request['workflow_stage'] ?? '') === 'Service Rejected') {
        die('This service has already been rejected.');
    }

    /*
    |--------------------------------------------------------------------------
    | Administrator Identity
    |--------------------------------------------------------------------------
    */

    if ($isDemoAdmin) {
        $adminEmail = trim((string) (
            $_SESSION['demo_user']['email']
            ?? ''
        ));

        if ($adminEmail === '') {
            $adminId = (int) (
                $_SESSION['demo_user']['id'] ?? 0
            );

            if ($adminId <= 0) {
                die('Demo administrator not found.');
            }

            $adminStmt = $servicePdo->prepare("
                SELECT email
                FROM users
                WHERE id = ?
                  AND demo_tenant_id = ?
                  AND is_demo_account = 1
                  AND is_super_admin = 0
                LIMIT 1
            ");

            $adminStmt->execute([
                $adminId,
                $demoTenantId
            ]);

            $adminEmail = (string) $adminStmt->fetchColumn();
        }
    } else {
        $adminEmail = trim((string) ($_SESSION['user'] ?? ''));
    }

    if ($adminEmail === '') {
        die('Admin not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    */

    $updateWhere = "
        WHERE id = ?
    ";

    $updateParams = [
        $reason,
        $adminEmail,
        $id
    ];

    if ($isDemoAdmin) {
        $updateWhere .= "
            AND customer_id IN (
                SELECT id
                FROM customers
                WHERE demo_tenant_id = ?
                  AND is_demo_account = 1
            )
        ";

        $updateParams[] = $demoTenantId;
    }

    $updateStmt = $servicePdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Service Rejected',
            service_rejection_reason = ?,
            service_rejected_at = NOW(),
            service_rejected_by = ?,
            service_reschedules = 0
        {$updateWhere}
    ");

    $updateStmt->execute($updateParams);

    if ($updateStmt->rowCount() !== 1) {
        die('The service rejection could not be completed.');
    }

    header('Location: ?page=requests');
    exit;
}

?>


<div class="container mt-4">

    <h2>Reject Service</h2>

    <hr>

    <p>
        <strong>Customer:</strong>
        <?= htmlspecialchars(
            (string) $request['name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        <strong>Service:</strong>
        <?= htmlspecialchars(
            (string) $request['service_title'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <form method="post">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <div class="mb-3">

            <label
                for="reason"
                class="form-label"
            >
                Rejection Reason
            </label>

            <textarea
                id="reason"
                name="reason"
                class="form-control"
                rows="5"
                maxlength="2000"
                required
            ></textarea>

        </div>

        <button
            type="submit"
            class="btn btn-danger"
        >
            Reject Service
        </button>

        <a
            href="?page=review-service&id=<?= (int) $id ?>"
            class="btn btn-secondary"
        >
            Cancel
        </a>

    </form>

</div>
