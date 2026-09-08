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
| Already Archived
|--------------------------------------------------------------------------
*/

if ($lead['status'] === 'Archived') {

    header("Location: ?page=contract-leads");
    exit;
}


/*
|--------------------------------------------------------------------------
| Confirm Archive
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['confirm_archive'])
) {

    $stmt = $pdo->prepare("
        UPDATE contract_leads
        SET status = 'Archived'
        WHERE id = ?
    ");

    $stmt->execute([$id]);


    header("Location: ?page=contract-leads");
    exit;
}


require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<div class="row justify-content-center">

    <div class="col-md-7">


        <div class="card shadow-sm">


            <div class="card-body p-4">


                <h2 class="mb-3">
                    Archive Company Lead
                </h2>


                <p class="text-muted">

                    You are about to archive this company lead.

                </p>


                <div class="border rounded p-3 mb-4">


                    <div class="mb-2">

                        <strong>
                            Company:
                        </strong>

                        <?= htmlspecialchars(
                            $lead['company_name']
                        ) ?>

                    </div>


                    <div class="mb-2">

                        <strong>
                            Contact Person:
                        </strong>

                        <?= htmlspecialchars(
                            $lead['contact_person']
                        ) ?>

                    </div>


                    <div class="mb-2">

                        <strong>
                            Email:
                        </strong>

                        <?= htmlspecialchars(
                            $lead['email']
                        ) ?>

                    </div>


                    <div>

                        <strong>
                            Current Status:
                        </strong>

                        <?= htmlspecialchars(
                            $lead['status']
                        ) ?>

                    </div>


                </div>


                <div class="alert alert-warning">

                    <strong>Important:</strong>

                    Archiving does not delete this lead.

                    The record will remain in the system and can
                    be reviewed or followed up in the future.

                </div>


                <form method="POST">


                    <button
                        type="submit"
                        name="confirm_archive"
                        class="btn btn-secondary">

                        Archive Lead

                    </button>


                    <a
                        href="?page=contract-leads"
                        class="btn btn-outline-secondary">

                        Cancel

                    </a>


                </form>


            </div>

        </div>


    </div>

</div>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>