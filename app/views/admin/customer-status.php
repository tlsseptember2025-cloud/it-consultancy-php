<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();


/*
|--------------------------------------------------------------------------
| Determine Environment
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

$customerStatusPdo = $pdo;


/*
|--------------------------------------------------------------------------
| Load Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin || $isDemoSuperAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

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

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id']
        ?? 0
    );

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
| Payment Required should only be available when the customer actually
| has money outstanding on at least one request.
|
| A Paid payment by itself is not enough to make this decision because
| a customer may have multiple requests. We therefore compare each
| request's quoted price against its recorded Paid payments.
|
| If there is no request with an outstanding balance, Payment Required
| must not be offered.
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

$activeSuspensions = $suspensionStmt->fetchAll(PDO::FETCH_ASSOC);

$activeSuspensionReasons = array_column(
    $activeSuspensions,
    'reason'
);


/*
|--------------------------------------------------------------------------
| Determine Available Suspension Reasons
|--------------------------------------------------------------------------
|
| Do not offer a reason that is already active.
| Offer Payment Required only when an outstanding payment exists.
|
*/

$availableSuspensionReasons = array_values(
    array_filter(
        $suspensionReasons,
        function ($reason) use ($hasOutstandingPayment, $activeSuspensionReasons) {

            if (in_array($reason, $activeSuspensionReasons, true)) {
                return false;
            }

            if ($reason === 'Payment Required' && !$hasOutstandingPayment) {
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
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['suspend_customer'])
) {

    $selectedReasons = $_POST['suspension_reasons'] ?? [];

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

        $error = 'Please select at least one suspension reason.';

    } else {

        try {

            $customerStatusPdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Selected Reasons
            |--------------------------------------------------------------------------
            |
            | Do not create duplicate active reasons.
            |
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


            $customerStatusPdo->commit();


            header(
                'Location: ?page=customer-status&id=' . $customerId
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
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['resolve_suspension'])
) {

    $suspensionId = (int) (
        $_POST['suspension_id'] ?? 0
    );


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
        | Check Remaining Active Suspension Reasons
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

        $remainingReasons = (int) $remainingStmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | Do Not Automatically Reactivate
        |--------------------------------------------------------------------------
        |
        | Even when no active suspension reasons remain, the customer stays
        | Suspended until Admin explicitly chooses Reactivate Customer.
        |
        */

        $customerStatusPdo->commit();


        header(
            'Location: ?page=customer-status&id=' . $customerId
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
| Reactivation is always a separate manual Admin action.
| It is only allowed when no active suspension reasons remain.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['reactivate_customer'])
) {

    try {

        $remainingStmt = $customerStatusPdo->prepare("
            SELECT COUNT(*)
            FROM customer_suspensions
            WHERE customer_id = ?
              AND active = 1
        ");

        $remainingStmt->execute([
            $customerId
        ]);

        $remainingReasons = (int) $remainingStmt->fetchColumn();

        if ($remainingReasons > 0) {

            throw new RuntimeException(
                'The customer cannot be reactivated while active suspension reasons remain.'
            );
        }

        $customerStatusPdo->beginTransaction();

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
            'Location: ?page=customer-status&id=' . $customerId
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
| Determine Current Suspension State
|--------------------------------------------------------------------------
*/

$hasActiveSuspensions = !empty($activeSuspensions);



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
                            $customer['name']
                        ) ?>

                    </h4>


                    <div class="border rounded p-3 mb-4">


                        <div class="mb-2">

                            <strong>
                                Email:
                            </strong>

                            <?= htmlspecialchars(
                                $customer['email']
                            ) ?>

                        </div>


                        <div class="mb-2">

                            <strong>
                                Company:
                            </strong>

                            <?= htmlspecialchars(
                                $customer['company'] ?? '-'
                            ) ?>

                        </div>


                        <div class="mb-3">

                            <strong>
                                Current Status:
                            </strong>

                            <?php if ($customer['status'] === 'Suspended'): ?>

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
                                                    $suspension['reason']
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

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>


                    <?php if ($customer['status'] === 'Active'): ?>


                        <div class="alert alert-warning">

                            <strong>Important:</strong>

                            Select all reasons that currently require
                            the customer account to be suspended.

                            You may select more than one reason.

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

                                        There are currently no applicable suspension reasons available for this customer.

                                    </div>

                                <?php else: ?>

                                    <?php foreach ($availableSuspensionReasons as $reason): ?>

                                    <div class="form-check mb-2">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="suspension_reasons[]"
                                            value="<?= htmlspecialchars($reason) ?>"
                                            id="reason_<?= md5($reason) ?>"
                                        >


                                        <label
                                            class="form-check-label"
                                            for="reason_<?= md5($reason) ?>">

                                            <?= htmlspecialchars($reason) ?>

                                        </label>

                                    </div>

                                    <?php endforeach; ?>

                                <?php endif; ?>


                            </div>


                            <button
                                type="submit"
                                name="suspend_customer"
                                class="btn btn-warning">

                                Suspend Customer

                            </button>


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

                            <form method="POST" class="mb-3">

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