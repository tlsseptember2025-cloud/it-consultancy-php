<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Search & Filter
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$status = trim($_GET['status'] ?? '');


$allowedStatuses = [

    'New',
    'Contacted',
    'Converted',
    'Closed',
    'Archived'

];


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM contract_leads
    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            company_name LIKE ?
            OR contact_person LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (
    $status !== ''
    &&
    in_array($status, $allowedStatuses, true)
) {

    $sql .= "
        AND status = ?
    ";

    $params[] = $status;

}


$sql .= "
    ORDER BY created_at DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helper - Status Badge
|--------------------------------------------------------------------------
*/

function leadStatusBadge(string $status): string
{

    return match ($status) {

        'New'
            => 'bg-primary',

        'Contacted'
            => 'bg-info text-dark',

        'Converted'
            => 'bg-success',

        'Closed'
            => 'bg-secondary',

        'Archived'
            => 'bg-dark',

        default
            => 'bg-light text-dark'

    };

}


/*
|--------------------------------------------------------------------------
| Helper - Service Display
|--------------------------------------------------------------------------
*/

function leadServices($json): string
{

    if (empty($json)) {

        return '<span class="text-muted">Not specified</span>';

    }


    $services = json_decode($json, true);


    if (
        !is_array($services)
        ||
        empty($services)
    ) {

        return '<span class="text-muted">Not specified</span>';

    }


    $html = '';

    foreach ($services as $service) {

        $html .=
            '<span class="badge bg-light text-dark border me-1 mb-1">'
            . htmlspecialchars($service)
            . '</span>';

    }


    return $html;

}


require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="mb-1">
            Company Support Leads
        </h2>

        <p class="text-muted mb-0">
            Manage companies interested in monthly or yearly support services.
        </p>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Search & Filter
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mb-4">

    <div class="card-body">

        <form method="GET">

            <input
                type="hidden"
                name="page"
                value="contract-leads">


            <div class="row g-3 align-items-end">


                <!-- Search -->

                <div class="col-md-6">

                    <label class="form-label">

                        Search Leads

                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Company, contact, email or phone"
                        value="<?= htmlspecialchars($search) ?>">

                </div>


                <!-- Status -->

                <div class="col-md-4">

                    <label class="form-label">

                        Status

                    </label>

                    <select
                        name="status"
                        class="form-select">

                        <option value="">
                            All Leads
                        </option>

                        <?php foreach ($allowedStatuses as $leadStatus): ?>

                            <option
                                value="<?= htmlspecialchars($leadStatus) ?>"
                                <?= $status === $leadStatus ? 'selected' : '' ?>>

                                <?= htmlspecialchars($leadStatus) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Search -->

                <div class="col-md-2">

                    <button
                        type="submit"
                        class="btn btn-primary w-100">

                        🔎 Search

                    </button>

                </div>


            </div>


            <?php if ($search !== '' || $status !== ''): ?>

                <div class="mt-3">

                    <a
                        href="?page=contract-leads"
                        class="btn btn-outline-secondary btn-sm">

                        Clear Filters

                    </a>

                </div>

            <?php endif; ?>


        </form>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Lead Count
|--------------------------------------------------------------------------
-->

<div class="mb-3">

    <strong>

        <?= count($leads) ?>

    </strong>

    lead<?= count($leads) === 1 ? '' : 's' ?>

    found.

</div>


<!--
|--------------------------------------------------------------------------
| Leads Table
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm">

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-bordered table-striped table-hover mb-0">


                <thead>

                    <tr>

                        <th>
                            Company
                        </th>

                        <th>
                            Contact
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Phone
                        </th>

                        <th>
                            Services
                        </th>

                        <th>
                            Contract
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (empty($leads)): ?>

                        <tr>

                            <td
                                colspan="9"
                                class="text-center text-muted py-5">

                                No leads found.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($leads as $lead): ?>

                            <tr>


                                <!-- Company -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $lead['company_name']
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- Contact -->

                                <td>

                                    <?= htmlspecialchars(
                                        $lead['contact_person']
                                    ) ?>

                                </td>


                                <!-- Email -->

                                <td>

                                    <a
                                        href="mailto:<?= htmlspecialchars(
                                            $lead['email']
                                        ) ?>">

                                        <?= htmlspecialchars(
                                            $lead['email']
                                        ) ?>

                                    </a>

                                </td>


                                <!-- Phone -->

                                <td>

                                    <?php if (!empty($lead['phone'])): ?>

                                        <a
                                            href="tel:<?= htmlspecialchars(
                                                $lead['phone']
                                            ) ?>">

                                            <?= htmlspecialchars(
                                                $lead['phone']
                                            ) ?>

                                        </a>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Not provided
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Services -->

                                <td>

                                    <?= leadServices(
                                        $lead['support_services']
                                    ) ?>

                                </td>


                                <!-- Contract -->

                                <td>

                                    <?php if (
                                        !empty($lead['contract_term'])
                                    ): ?>

                                        <span class="badge bg-light text-dark border">

                                            <?= htmlspecialchars(
                                                $lead['contract_term']
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Not specified
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Status -->

                                <td>

                                    <span
                                        class="badge <?= leadStatusBadge(
                                            $lead['status']
                                        ) ?>">

                                        <?= htmlspecialchars(
                                            $lead['status']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Date -->

                                <td>

                                    <?= formatDateTime(
                                        $lead['created_at']
                                    ) ?>

                                </td>


                                <!-- Actions -->

                                <td>

                                    <div class="d-flex gap-1">


                                        <!-- Update -->

                                        <a
                                            href="?page=update-contract-lead&id=<?= (int)$lead['id'] ?>"
                                            class="btn btn-warning btn-sm">

                                            Update

                                        </a>


                                        <!-- Archive -->

                                        <?php if (
                                            $lead['status'] !== 'Archived'
                                        ): ?>

                                            <a
                                                href="?page=archive-contract-lead&id=<?= (int)$lead['id'] ?>"
                                                class="btn btn-secondary btn-sm"
                                                onclick="return confirm('Archive this lead? The record will be kept and can be used for future follow-up.');">

                                                Archive

                                            </a>

                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>


            </table>

        </div>

    </div>

</div>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>