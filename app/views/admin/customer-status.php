<?php

require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Environment
|--------------------------------------------------------------------------
*/

$isMainAdmin = isset($_SESSION['user']);
$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainAdmin && !$isDemoAdmin && !$isDemoSuperAdmin) {

    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

$customerStatusPdo = $pdo;

if ($isDemoAdmin || $isDemoSuperAdmin) {

    if (!isset($demoPdo)) {
        require_once CONFIG_PATH . '/demo-database.php';
    }

    $customerStatusPdo = $demoPdo;
}


/*
|--------------------------------------------------------------------------
| Get Customer ID
|--------------------------------------------------------------------------
*/

$customerId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($customerId <= 0) {

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Demo Tenant
|--------------------------------------------------------------------------
*/

$demoTenantId = null;

if ($isDemoAdmin) {

    $demoTenantId =
        (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {

        die('Invalid Demo tenant.');
    }
}


/*
|--------------------------------------------------------------------------
| Load Customer
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    /*
    |--------------------------------------------------------------------------
    | Demo Admin - Tenant Restricted
    |--------------------------------------------------------------------------
    */

    $stmt = $customerStatusPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $customerId,
        $demoTenantId
    ]);

} else {

    /*
    |--------------------------------------------------------------------------
    | Main Admin OR Demo Super Admin
    |--------------------------------------------------------------------------
    */

    $stmt = $customerStatusPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $customerId
    ]);
}


$customer = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$customer) {

    die('Customer not found.');
}


/*
|--------------------------------------------------------------------------
| Suspension Reasons
|--------------------------------------------------------------------------
*/

$suspensionReasons = [
    'Payment Required',
    'Customer Information Required',
    'Administrative Review',
    'Other Account Issue'
];


/*
|--------------------------------------------------------------------------
| Determine Whether Customer Has an Outstanding Payment
|--------------------------------------------------------------------------
|
| Payment Required is available only when the customer actually has
| an unpaid balance on at least one request.
|
*/

$outstandingPaymentStmt = $customerStatusPdo->prepare("
    SELECT COUNT(*)
    FROM requests r
    WHERE r.customer_id = ?
      AND COALESCE(r.quoted_price, 0) > (
          SELECT COALESCE(
              SUM(
                  CASE
                      WHEN p.status = 'Paid' THEN p.amount
                      ELSE 0
                  END
              ),
              0
          )
          FROM payments p
          WHERE p.request_id = r.id
      )
");


$outstandingPaymentStmt->execute([
    $customerId
]);


$hasOutstandingPayment =
    ((int) $outstandingPaymentStmt->fetchColumn()) > 0;


/*
|--------------------------------------------------------------------------
| Load Active Suspension Reasons
|--------------------------------------------------------------------------
*/

$suspensionStmt = $customerStatusPdo->prepare("
    SELECT
        id,
        reason,
        created_at,
        resolved_at
    FROM customer_suspensions
    WHERE customer_id = ?
      AND active = 1
    ORDER BY created_at ASC
");


$suspensionStmt->execute([
    $customerId
]);


$activeSuspensions =
    $suspensionStmt->fetchAll(PDO::FETCH_ASSOC);


$activeSuspensionReasons =
    array_column(
        $activeSuspensions,
        'reason'
    );


/*
|--------------------------------------------------------------------------
| Determine Available Suspension Reasons
|--------------------------------------------------------------------------
*/

$availableSuspensionReasons = array_values(
    array_filter(
        $suspensionReasons,
        function ($reason) use (
            $hasOutstandingPayment,
            $activeSuspensionReasons
        ) {

            /*
            |--------------------------------------------------------------------------
            | Do not duplicate an active reason
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $reason,
                    $activeSuspensionReasons,
                    true
                )
            ) {

                return false;
            }


            /*
            |--------------------------------------------------------------------------
            | Payment Required only when money is outstanding
            |--------------------------------------------------------------------------
            */

            if (
                $reason === 'Payment Required' &&
                !$hasOutstandingPayment
            ) {

                return false;
            }


            return true;
        }
    )
);


/*
|--------------------------------------------------------------------------
| Process New Suspension
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['suspend_customer'])
) {

    $selectedReasons =
        $_POST['suspension_reasons'] ?? [];


    if (!is_array($selectedReasons)) {

        $selectedReasons = [];
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Selected Reasons
    |--------------------------------------------------------------------------
    */

    $selectedReasons = array_values(
        array_intersect(
            $availableSuspensionReasons,
            $selectedReasons
        )
    );


    if (empty($selectedReasons)) {

        $error =
            'Please select at least one suspension reason.';

    } else {

        try {

            $customerStatusPdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Selected Reasons
            |--------------------------------------------------------------------------
            */

            foreach ($selectedReasons as $reason) {

                $checkStmt = $customerStatusPdo->prepare("
                    SELECT id
                    FROM customer_suspensions
                    WHERE customer_id = ?
                      AND reason = ?
                      AND active = 1
                    LIMIT 1
                ");

                $checkStmt->execute([
                    $customerId,
                    $reason
                ]);


                if (!$checkStmt->fetch()) {

                    $insertStmt = $customerStatusPdo->prepare("
                        INSERT INTO customer_suspensions
                        (
                            customer_id,
                            reason,
                            active
                        )
                        VALUES (?, ?, 1)
                    ");

                    $insertStmt->execute([
                        $customerId,
                        $reason
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Set Customer Suspended
            |--------------------------------------------------------------------------
            */

            if ($isDemoAdmin) {

                $updateStmt = $customerStatusPdo->prepare("
                    UPDATE customers
                    SET status = 'Suspended'
                    WHERE id = ?
                      AND demo_tenant_id = ?
                      AND is_demo_account = 1
                ");

                $updateStmt->execute([
                    $customerId,
                    $demoTenantId
                ]);

            } else {

                $updateStmt = $customerStatusPdo->prepare("
                    UPDATE customers
                    SET status = 'Suspended'
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $customerId
                ]);
            }


            if ($updateStmt->rowCount() !== 1) {

                throw new RuntimeException(
                    'The customer could not be suspended.'
                );
            }


            $customerStatusPdo->commit();


            header(
                'Location: ?page=customer-status&id=' .
                $customerId
            );

            exit;


        } catch (Throwable $e) {

            if ($customerStatusPdo->inTransaction()) {

                $customerStatusPdo->rollBack();
            }

            die($e->getMessage());
        }
    }
}


/*
|--------------------------------------------------------------------------
| Resolve One Suspension Reason
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['resolve_suspension'])
) {

    $suspensionId =
        (int) ($_POST['suspension_id'] ?? 0);


    if ($suspensionId <= 0) {

        die('Invalid suspension reason.');
    }


    try {

        $customerStatusPdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Resolve Selected Reason
        |--------------------------------------------------------------------------
        */

        if ($isDemoAdmin) {

            $resolveStmt = $customerStatusPdo->prepare("
                UPDATE customer_suspensions cs
                INNER JOIN customers c
                    ON c.id = cs.customer_id
                SET
                    cs.active = 0,
                    cs.resolved_at = NOW()
                WHERE cs.id = ?
                  AND cs.customer_id = ?
                  AND c.demo_tenant_id = ?
                  AND c.is_demo_account = 1
                  AND cs.active = 1
            ");

            $resolveStmt->execute([
                $suspensionId,
                $customerId,
                $demoTenantId
            ]);

        } else {

            $resolveStmt = $customerStatusPdo->prepare("
                UPDATE customer_suspensions
                SET
                    active = 0,
                    resolved_at = NOW()
                WHERE id = ?
                  AND customer_id = ?
                  AND active = 1
            ");

            $resolveStmt->execute([
                $suspensionId,
                $customerId
            ]);
        }


        if ($resolveStmt->rowCount() !== 1) {

            throw new RuntimeException(
                'The suspension reason could not be resolved.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Do NOT Automatically Reactivate
        |--------------------------------------------------------------------------
        */

        $customerStatusPdo->commit();


        header(
            'Location: ?page=customer-status&id=' .
            $customerId
        );

        exit;


    } catch (Throwable $e) {

        if ($customerStatusPdo->inTransaction()) {

            $customerStatusPdo->rollBack();
        }

        die($e->getMessage());
    }
}


/*
|--------------------------------------------------------------------------
| Explicit Admin Reactivation
|--------------------------------------------------------------------------
|
| Customer may only be reactivated when there are no active
| suspension reasons remaining.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['reactivate_customer'])
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Check Remaining Active Reasons
        |--------------------------------------------------------------------------
        */

        $remainingStmt = $customerStatusPdo->prepare("
            SELECT COUNT(*)
            FROM customer_suspensions
            WHERE customer_id = ?
              AND active = 1
        ");

        $remainingStmt->execute([
            $customerId
        ]);


        $remainingReasons =
            (int) $remainingStmt->fetchColumn();


        if ($remainingReasons > 0) {

            throw new RuntimeException(
                'The customer cannot be reactivated while active suspension reasons remain.'
            );
        }


        $customerStatusPdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Reactivate Customer
        |--------------------------------------------------------------------------
        */

        if ($isDemoAdmin) {

            $updateStmt = $customerStatusPdo->prepare("
                UPDATE customers
                SET status = 'Active'
                WHERE id = ?
                  AND demo_tenant_id = ?
                  AND is_demo_account = 1
                  AND status = 'Suspended'
            ");

            $updateStmt->execute([
                $customerId,
                $demoTenantId
            ]);

        } else {

            $updateStmt = $customerStatusPdo->prepare("
                UPDATE customers
                SET status = 'Active'
                WHERE id = ?
                  AND status = 'Suspended'
            ");

            $updateStmt->execute([
                $customerId
            ]);
        }


        if ($updateStmt->rowCount() !== 1) {

            throw new RuntimeException(
                'The customer could not be reactivated.'
            );
        }


        $customerStatusPdo->commit();


        header(
            'Location: ?page=customer-status&id=' .
            $customerId
        );

        exit;


    } catch (Throwable $e) {

        if ($customerStatusPdo->inTransaction()) {

            $customerStatusPdo->rollBack();
        }

        die($e->getMessage());
    }
}


/*
|--------------------------------------------------------------------------
| Refresh Customer and Suspension State
|--------------------------------------------------------------------------
|
| This ensures the page displays the current database state after
| an action.
|
*/

if ($isDemoAdmin) {

    $stmt = $customerStatusPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $customerStatusPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $customerId
    ]);
}


$customer = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$customer) {

    die('Customer not found.');
}


/*
|--------------------------------------------------------------------------
| Refresh Active Suspension Reasons
|--------------------------------------------------------------------------
*/

$suspensionStmt = $customerStatusPdo->prepare("
    SELECT
        id,
        reason,
        created_at,
        resolved_at
    FROM customer_suspensions
    WHERE customer_id = ?
      AND active = 1
    ORDER BY created_at ASC
");


$suspensionStmt->execute([
    $customerId
]);


$activeSuspensions =
    $suspensionStmt->fetchAll(PDO::FETCH_ASSOC);


$hasActiveSuspensions =
    !empty($activeSuspensions);


/*
|--------------------------------------------------------------------------
| Admin Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container mt-4">

    <div class="row justify-content-center">

        <div class="col-md-8">

            <div class="card shadow-sm">

                <div class="card-header bg-primary text-white">

                    <strong>
                        Customer Status
                    </strong>

                </div>


                <div class="card-body">


                    <h4 class="mb-4">

                        <?= htmlspecialchars(
                            $customer['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h4>


                    <div class="border rounded p-3 mb-4">


                        <div class="mb-2">

                            <strong>
                                Email:
                            </strong>

                            <?= htmlspecialchars(
                                $customer['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>


                        <div class="mb-2">

                            <strong>
                                Company:
                            </strong>

                            <?= htmlspecialchars(
                                $customer['company'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>


                        <div class="mb-3">

                            <strong>
                                Current Status:
                            </strong>

                            <?php if (($customer['status'] ?? 'Active') === 'Suspended'): ?>

                                <span class="badge bg-danger">
                                    Suspended
                                </span>

                            <?php else: ?>

                                <span class="badge bg-success">
                                    Active
                                </span>

                            <?php endif; ?>

                        </div>


                        <?php if (!empty($activeSuspensions)): ?>

                            <div>

                                <strong>
                                    Active Suspension Reasons:
                                </strong>


                                <div class="mt-2">

                                    <?php foreach ($activeSuspensions as $suspension): ?>

                                        <div
                                            class="border rounded p-2 mb-2 d-flex justify-content-between align-items-center">

                                            <span>

                                                <?= htmlspecialchars(
                                                    $suspension['reason'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>


                                            <form
                                                method="POST"
                                                class="m-0">

                                                <input
                                                    type="hidden"
                                                    name="suspension_id"
                                                    value="<?= (int) $suspension['id'] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    name="resolve_suspension"
                                                    class="btn btn-sm btn-success"
                                                    onclick="return confirm('Resolve this suspension reason?');">

                                                    Resolve

                                                </button>

                                            </form>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        <?php endif; ?>


                    </div>


                    <?php if (!empty($error)): ?>

                        <div class="alert alert-danger">

                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (($customer['status'] ?? 'Active') === 'Active'): ?>


                        <div class="alert alert-warning">

                            <strong>Important:</strong>

                            Select all reasons that currently require
                            the customer account to be suspended.

                            <br>

                            You may select more than one reason.

                            <br>

                            The customer record and data will not be deleted.

                        </div>


                        <form method="POST">


                            <div class="mb-4">

                                <label class="form-label">

                                    <strong>
                                        Suspension Reasons
                                    </strong>

                                </label>


                                <?php if (empty($availableSuspensionReasons)): ?>

                                    <div class="alert alert-info mb-0">

                                        There are currently no applicable
                                        suspension reasons available for
                                        this customer.

                                    </div>

                                <?php else: ?>

                                    <?php foreach ($availableSuspensionReasons as $reason): ?>

                                        <div class="form-check mb-2">

                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="suspension_reasons[]"
                                                value="<?= htmlspecialchars(
                                                    $reason,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                id="reason_<?= md5($reason) ?>"
                                            >


                                            <label
                                                class="form-check-label"
                                                for="reason_<?= md5($reason) ?>">

                                                <?= htmlspecialchars(
                                                    $reason,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </label>

                                        </div>

                                    <?php endforeach; ?>

                                <?php endif; ?>


                            </div>


                            <?php if (!empty($availableSuspensionReasons)): ?>

                                <button
                                    type="submit"
                                    name="suspend_customer"
                                    class="btn btn-warning">

                                    Suspend Customer

                                </button>

                            <?php endif; ?>


                            <a
                                href="?page=customers"
                                class="btn btn-outline-secondary">

                                Cancel

                            </a>


                        </form>


                    <?php else: ?>


                        <div class="alert alert-info">

                            This customer is currently suspended.

                            <br><br>

                            The customer can only access the
                            suspension communication area until all
                            active suspension reasons are resolved.

                        </div>


                        <?php if (empty($activeSuspensions)): ?>

                            <div class="alert alert-warning">

                                <strong>Admin Action Required:</strong>

                                All suspension reasons have been resolved,
                                but the customer is still Suspended.

                                <br><br>

                                Click <strong>Reactivate Customer</strong>
                                only when you are satisfied that the account
                                should be restored.

                            </div>


                            <form
                                method="POST"
                                class="mb-3">

                                <button
                                    type="submit"
                                    name="reactivate_customer"
                                    class="btn btn-success"
                                    onclick="return confirm('Reactivate this customer account?');">

                                    Reactivate Customer

                                </button>

                            </form>


                        <?php else: ?>

                            <p class="text-muted mb-0">

                                Resolve each active suspension reason above.

                                The customer remains Suspended until all
                                reasons are resolved and Admin explicitly
                                reactivates the account.

                            </p>

                        <?php endif; ?>


                        <div class="mt-3">

                            <a
                                href="?page=customers"
                                class="btn btn-outline-secondary">

                                Back to Customers

                            </a>

                        </div>


                    <?php endif; ?>


                </div>

            </div>

        </div>

    </div>

</div>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>