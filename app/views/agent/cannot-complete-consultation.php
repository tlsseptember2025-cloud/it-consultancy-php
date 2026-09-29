<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Agent Authentication
|--------------------------------------------------------------------------
*/

$isDemoAgent = isset($_SESSION['demo_agent']);

if (!$isDemoAgent && !isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

if ($isDemoAgent) {

    require_once CONFIG_PATH . '/demo-database.php';

    $db = $demoPdo;

    $agentId = (int) $_SESSION['demo_agent']['id'];
    $demoTenantId = (int) ($_SESSION['demo_agent']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    require_once CONFIG_PATH . '/database.php';

    $db = $pdo;

    $agentId = (int) $_SESSION['agent']['id'];
}


$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {

    die('Invalid consultation.');
}


/*
|--------------------------------------------------------------------------
| Load Consultation
|--------------------------------------------------------------------------
|
| The request must belong to a consultation assigned to the logged-in
| Agent.
|
| Demo Agent:
|   - Assigned Agent must belong to the current Demo tenant.
|   - Customer must belong to the current Demo tenant.
|   - Service must belong to the current Demo tenant.
|
| Normal Agent:
|   - Consultation booking must belong to the logged-in Agent.
|
*/

if ($isDemoAgent) {

    $stmt = $db->prepare("
        SELECT
            r.*,
            c.name AS customer_name,
            s.title AS service_name

        FROM requests r

        INNER JOIN consultation_bookings cb
            ON cb.request_id = r.id

        INNER JOIN customers c
            ON c.id = r.customer_id

        INNER JOIN services s
            ON s.id = r.service_id

        INNER JOIN agents a
            ON a.id = cb.agent_id

        WHERE
            r.id = ?
            AND cb.agent_id = ?
            AND a.demo_tenant_id = ?
            AND a.is_demo_account = 1
            AND c.demo_tenant_id = ?
            AND c.is_demo_account = 1
            AND s.demo_tenant_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $agentId,
        $demoTenantId,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            r.*,
            c.name AS customer_name,
            s.title AS service_name

        FROM requests r

        INNER JOIN consultation_bookings cb
            ON cb.request_id = r.id

        INNER JOIN customers c
            ON c.id = r.customer_id

        INNER JOIN services s
            ON s.id = r.service_id

        WHERE
            r.id = ?
            AND cb.agent_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $agentId
    ]);
}


$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {

    die('Consultation not found.');
}


/*
|--------------------------------------------------------------------------
| Submit Incomplete Consultation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reason = trim(
        $_POST['reason'] ?? ''
    );

    $notes = trim(
        $_POST['notes'] ?? ''
    );


    if ($reason === '') {

        die('Please select a reason.');
    }


    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    |
    | Do not use Demo tenant columns on requests.
    | Ownership is verified through the assigned consultation booking,
    | Agent, Customer and Service.
    |
    */

    if ($isDemoAgent) {

        $update = $db->prepare("
            UPDATE requests r

            SET
                r.job_status = 'Could Not Complete',
                r.workflow_stage = 'Needs Admin Review',
                r.incomplete_reason = ?,
                r.completion_notes = ?

            WHERE
                r.id = ?

                AND EXISTS (
                    SELECT 1
                    FROM consultation_bookings cb

                    INNER JOIN agents a
                        ON a.id = cb.agent_id

                    INNER JOIN customers c
                        ON c.id = r.customer_id

                    INNER JOIN services s
                        ON s.id = r.service_id

                    WHERE
                        cb.request_id = r.id
                        AND cb.agent_id = ?
                        AND a.demo_tenant_id = ?
                        AND a.is_demo_account = 1
                        AND c.demo_tenant_id = ?
                        AND c.is_demo_account = 1
                        AND s.demo_tenant_id = ?
                )
        ");

        $update->execute([
            $reason,
            $notes,
            $requestId,
            $agentId,
            $demoTenantId,
            $demoTenantId,
            $demoTenantId
        ]);

    } else {

        $update = $db->prepare("
            UPDATE requests r

            SET
                r.job_status = 'Could Not Complete',
                r.workflow_stage = 'Needs Admin Review',
                r.incomplete_reason = ?,
                r.completion_notes = ?

            WHERE
                r.id = ?

                AND EXISTS (
                    SELECT 1
                    FROM consultation_bookings cb

                    WHERE
                        cb.request_id = r.id
                        AND cb.agent_id = ?
                )
        ");

        $update->execute([
            $reason,
            $notes,
            $requestId,
            $agentId
        ]);
    }


    if ($update->rowCount() !== 1) {

        die(
            'This consultation could not be updated.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Record Consultation Incomplete Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::addCurrentUser(
        $db,
        (int) $requestId,
        RequestEventHelper::EVENT_CONSULTATION_INCOMPLETE,
        RequestEventHelper::TYPE_CONSULTATION,
        'Consultation Could Not Be Completed',
        'The assigned agent could not complete the consultation. Reason: ' . $reason,
        false
    );


    header(
        "Location: ?page=view-consultation&id=" . $requestId
    );

    exit;
}


require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <h2 class="mb-4">

        Report Incomplete Consultation

    </h2>


    <div class="card shadow-sm">

        <div class="card-body">

            <p>

                <strong>Customer:</strong>

                <?= htmlspecialchars(
                    $request['customer_name']
                ) ?>

            </p>


            <p>

                <strong>Service:</strong>

                <?= htmlspecialchars(
                    $request['service_name']
                ) ?>

            </p>


            <p>

                <strong>Current Status:</strong>

                <span class="badge bg-primary">

                    <?= htmlspecialchars(
                        $request['job_status']
                    ) ?>

                </span>

            </p>


            <div class="alert alert-warning">

                <strong>Important</strong>

                <ul class="mb-0 mt-2">

                    <li>
                        This consultation will be marked as
                        <strong>Could Not Complete</strong>.
                    </li>

                    <li>
                        The Administrator will review your reason.
                    </li>

                    <li>
                        The Administrator may reschedule or reassign
                        the consultation.
                    </li>

                </ul>

            </div>


            <form method="POST">

                <div class="mb-3">

                    <label class="form-label">

                        Reason

                    </label>

                    <select
                        name="reason"
                        class="form-select"
                        required>

                        <option value="">
                            Select a reason...
                        </option>

                        <option value="Customer unavailable">
                            Customer unavailable
                        </option>

                        <option value="Customer requested reschedule">
                            Customer requested reschedule
                        </option>

                        <option value="Missing documents">
                            Missing documents
                        </option>

                        <option value="Technical issue">
                            Technical issue
                        </option>

                        <option value="Customer declined consultation">
                            Customer declined consultation
                        </option>

                        <option value="Other">
                            Other
                        </option>

                    </select>

                </div>


                <div class="mb-3">

                    <label class="form-label">

                        Additional Notes

                    </label>

                    <textarea
                        name="notes"
                        rows="6"
                        class="form-control"></textarea>

                </div>


                <div class="d-flex justify-content-between mt-4">

                    <a
                        href="?page=view-consultation&id=<?= $requestId ?>"
                        class="btn btn-secondary">

                        ← Return to Consultation

                    </a>


                    <button
                        type="submit"
                        class="btn btn-danger">

                        Mark as Could Not Complete

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>