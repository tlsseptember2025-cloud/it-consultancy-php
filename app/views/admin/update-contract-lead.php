<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Get Lead ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($id <= 0) {

    header("Location: ?page=contract-leads");
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Lead
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM contract_leads
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$lead = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$lead) {

    die('Lead not found.');
}


/*
|--------------------------------------------------------------------------
| Check Archived Status
|--------------------------------------------------------------------------
|
| Archived leads are permanently protected from status updates.
|
*/

$isArchived = ($lead['status'] === 'Archived');


/*
|--------------------------------------------------------------------------
| Allowed Statuses
|--------------------------------------------------------------------------
|
| Archived is intentionally NOT included here.
| Archiving must be done through the Archive action.
|
*/

$allowedStatuses = [

    'New',
    'Contacted',
    'Converted',
    'Closed'

];


/*
|--------------------------------------------------------------------------
| Update Status
|--------------------------------------------------------------------------
*/

$error = '';


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_contract_lead'])
) {

    /*
    |--------------------------------------------------------------------------
    | Security / Workflow Check
    |--------------------------------------------------------------------------
    |
    | Check the database-loaded status again.
    | This protects against using the browser Back button
    | or submitting an old form.
    |
    */

    if ($isArchived) {

        $error =
            'This lead has been archived and cannot be modified.';

    }


    /*
    |--------------------------------------------------------------------------
    | Get New Status
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $newStatus =
            trim($_POST['status'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $newStatus,
                $allowedStatuses,
                true
            )
        ) {

            $error =
                'Invalid lead status.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Save Status
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        /*
        |--------------------------------------------------------------------------
        | Extra Database Protection
        |--------------------------------------------------------------------------
        |
        | Only update the record if it is still NOT archived.
        |
        */

        $stmt = $pdo->prepare("
            UPDATE contract_leads
            SET status = ?
            WHERE id = ?
            AND status <> 'Archived'
        ");

        $stmt->execute([

            $newStatus,
            $id

        ]);


        /*
        |--------------------------------------------------------------------------
        | Confirm Update
        |--------------------------------------------------------------------------
        */

        if ($stmt->rowCount() === 0) {

            $error =
                'This lead has been archived and cannot be modified.';

        } else {

            header("Location: ?page=contract-leads");
            exit;

        }

    }

}


/*
|--------------------------------------------------------------------------
| Public Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="mb-1">
            Update Company Lead
        </h2>

        <p class="text-muted mb-0">
            Update the current status of this company lead.
        </p>

    </div>


    <a
        href="?page=contract-leads"
        class="btn btn-outline-secondary">

        ← Back to Leads

    </a>

</div>


<?php if ($error !== ''): ?>

    <div class="alert alert-danger">

        <strong>
            Unable to update lead
        </strong>

        <br>

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<?php if ($isArchived): ?>


    <!--
    |--------------------------------------------------------------------------
    | Archived Lead
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm border-secondary">

        <div class="card-body">


            <div class="alert alert-warning mb-4">

                <strong>
                    This lead is archived.
                </strong>

                <br>

                Archived leads cannot be modified.

            </div>


            <div class="mb-3">

                <label class="form-label">
                    Company
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['company_name']
                    ) ?>"
                    readonly>

            </div>


            <div class="mb-3">

                <label class="form-label">
                    Contact Person
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['contact_person']
                    ) ?>"
                    readonly>

            </div>


            <div class="mb-3">

                <label class="form-label">
                    Email
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['email']
                    ) ?>"
                    readonly>

            </div>


            <div class="mb-4">

                <label class="form-label">
                    Lead Status
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="Archived"
                    readonly>

            </div>


            <a
                href="?page=contract-leads"
                class="btn btn-secondary">

                ← Back to Leads

            </a>


        </div>

    </div>


<?php else: ?>


    <!--
    |--------------------------------------------------------------------------
    | Normal Lead Update Form
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm">

        <div class="card-body">


            <div class="mb-3">

                <label class="form-label">
                    Company
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['company_name']
                    ) ?>"
                    readonly>

            </div>


            <div class="mb-3">

                <label class="form-label">
                    Contact Person
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['contact_person']
                    ) ?>"
                    readonly>

            </div>


            <div class="mb-4">

                <label class="form-label">
                    Email
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $lead['email']
                    ) ?>"
                    readonly>

            </div>


            <form method="POST">


                <div class="mb-4">

                    <label class="form-label">
                        Lead Status
                    </label>


                    <select
                        name="status"
                        class="form-select"
                        required>


                        <?php foreach ($allowedStatuses as $leadStatus): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $leadStatus
                                ) ?>"
                                <?= $lead['status'] === $leadStatus
                                    ? 'selected'
                                    : '' ?>>

                                <?= htmlspecialchars(
                                    $leadStatus
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>


                    <div class="form-text">

                        Update the lead status as you follow up with the company.

                    </div>

                </div>


                <button
                    type="submit"
                    name="update_contract_lead"
                    class="btn btn-warning">

                    Update

                </button>


                <a
                    href="?page=contract-leads"
                    class="btn btn-secondary">

                    Cancel

                </a>


            </form>


        </div>

    </div>


<?php endif; ?>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>