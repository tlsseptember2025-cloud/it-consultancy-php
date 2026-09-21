<?php

/*
|--------------------------------------------------------------------------
| Create Demo Tenant
|--------------------------------------------------------------------------
| Step 1.9
|
| Creates:
| - Demo tenant
|
| Does NOT create:
| - Company Demo Admin
| - Demo Customer
| - Demo Agent 1
| - Demo Agent 2
|
| Those are created by the following Demo provisioning steps.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Administrator Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Database Connections
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/demo-database.php';


/*
|--------------------------------------------------------------------------
| Get Demo Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int)($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid Demo request ID.');
}


/*
|--------------------------------------------------------------------------
| Load Demo Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        email,
        company_name,
        company_domain,
        status,
        customer_confirmed_at,
        demo_created_at
    FROM demo_requests
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Demo request not found.');
}


/*
|--------------------------------------------------------------------------
| Verify Request Status
|--------------------------------------------------------------------------
|
| Step 1.9 can only begin after the customer has confirmed the request.
|
*/

if ($request['status'] !== 'Customer Confirmed') {
    die(
        'This Demo request cannot be provisioned. '
        . 'Current status: '
        . htmlspecialchars($request['status'])
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Re-Provisioning
|--------------------------------------------------------------------------
*/

if (!empty($request['demo_created_at'])) {
    die('This Demo request has already been provisioned.');
}


/*
|--------------------------------------------------------------------------
| Validate Request Information
|--------------------------------------------------------------------------
*/

$companyDomain = trim($request['company_domain'] ?? '');
$companyName = trim($request['company_name'] ?? '');
$registeredEmail = trim($request['email'] ?? '');

if ($companyDomain === '') {
    die('The Demo request does not contain a company domain.');
}

if ($companyName === '') {
    $companyName = 'Demo Company';
}

if ($registeredEmail === '') {
    die('The Demo request does not contain a registered email address.');
}


/*
|--------------------------------------------------------------------------
| Check Existing Tenant
|--------------------------------------------------------------------------
|
| This protects against accidentally creating a second tenant for the
| same Demo request or company domain.
|
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        company_name,
        company_domain,
        registered_email,
        demo_request_id,
        started_at,
        expires_at,
        status
    FROM demo_tenants
    WHERE demo_request_id = ?
       OR company_domain = ?
    LIMIT 1
");

$stmt->execute([
    $requestId,
    $companyDomain
]);

$existingTenant = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingTenant) {
    die(
        'A Demo tenant already exists. '
        . 'Tenant ID: '
        . (int)$existingTenant['id']
    );
}


/*
|--------------------------------------------------------------------------
| Create Demo Tenant
|--------------------------------------------------------------------------
|
| The Demo period is NOT started here.
|
| started_at  = NULL
| expires_at  = NULL
|
| The tenant remains Active until the Demo activation/start logic
| determines when the actual Demo period begins.
|
*/

try {

    $demoPdo->beginTransaction();

    $stmt = $demoPdo->prepare("
        INSERT INTO demo_tenants
        (
            company_name,
            company_domain,
            registered_email,
            demo_request_id,
            started_at,
            expires_at,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            NULL,
            NULL,
            'Active'
        )
    ");

    $stmt->execute([
        $companyName,
        $companyDomain,
        $registeredEmail,
        $requestId
    ]);

    $tenantId = (int)$demoPdo->lastInsertId();

    $demoPdo->commit();

} catch (PDOException $e) {

    if ($demoPdo->inTransaction()) {
        $demoPdo->rollBack();
    }

    error_log(
        'Demo tenant creation failed for request #'
        . $requestId
        . ': '
        . $e->getMessage()
    );

    die(
        'The Demo tenant could not be created. '
        . 'Please check the Demo database.'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Demo Tenant Created</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>


<body class="bg-light">


<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">
                        Demo Tenant Created
                    </h4>

                </div>


                <div class="card-body">

                    <div class="alert alert-success">

                        <strong>
                            Demo tenant was created successfully.
                        </strong>

                    </div>


                    <table class="table table-bordered">

                        <tr>

                            <th width="40%">
                                Tenant ID
                            </th>

                            <td>
                                <?= $tenantId ?>
                            </td>

                        </tr>


                        <tr>

                            <th>
                                Demo Request ID
                            </th>

                            <td>
                                <?= (int)$request['id'] ?>
                            </td>

                        </tr>


                        <tr>

                            <th>
                                Company
                            </th>

                            <td>
                                <?= htmlspecialchars($companyName) ?>
                            </td>

                        </tr>


                        <tr>

                            <th>
                                Domain
                            </th>

                            <td>
                                <?= htmlspecialchars($companyDomain) ?>
                            </td>

                        </tr>


                        <tr>

                            <th>
                                Registered Email
                            </th>

                            <td>
                                <?= htmlspecialchars($registeredEmail) ?>
                            </td>

                        </tr>


                        <tr>

                            <th>
                                Status
                            </th>

                            <td>

                                <span class="badge bg-success">
                                    Active
                                </span>

                            </td>

                        </tr>


                        <tr>

                            <th>
                                Demo Started
                            </th>

                            <td>
                                <span class="text-muted">
                                    Not started
                                </span>
                            </td>

                        </tr>


                        <tr>

                            <th>
                                Demo Expires
                            </th>

                            <td>
                                <span class="text-muted">
                                    Not started
                                </span>
                            </td>

                        </tr>

                    </table>


                    <div class="alert alert-info">

                        <strong>
                            Step 1.9 complete.
                        </strong>

                        <br>

                        The Demo tenant has been created.

                        <br>

                        The Company Demo Admin can now be created
                        by Step 1.10.

                    </div>


                    <div class="d-flex justify-content-center gap-2 mt-4">

                        <a
                            href="?page=create-demo-admin&id=<?= (int)$request['id'] ?>"
                            class="btn btn-success"
                            onclick="return confirm('Create the Company Demo Admin now?');"
                        >
                            Continue to Step 1.10
                        </a>

                        <a
                            href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                            class="btn btn-secondary"
                        >
                            Back to Demo Request
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


</body>

</html>
