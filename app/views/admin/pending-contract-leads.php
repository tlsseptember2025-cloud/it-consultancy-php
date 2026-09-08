<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once APP_PATH . '/helpers/DateHelper.php';


/*
|--------------------------------------------------------------------------
| Load Pending Leads
|--------------------------------------------------------------------------
|
| Only submissions waiting for administrator approval are shown here.
|
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM contract_leads
    WHERE approval_status = 'Pending'
    ORDER BY created_at DESC
");

$stmt->execute();

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);


require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<!--
|--------------------------------------------------------------------------
| Page Header
|--------------------------------------------------------------------------
-->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="mb-1">
            Pending Company Leads
        </h2>

        <p class="text-muted mb-0">
            Review company submissions before adding them to the active leads list.
        </p>

    </div>


    <a
        href="?page=contract-leads"
        class="btn btn-outline-secondary">

        ← Active Leads

    </a>

</div>


<!--
|--------------------------------------------------------------------------
| Information
|--------------------------------------------------------------------------
-->

<div class="alert alert-warning">

    <strong>
        ⚠️ Review Required
    </strong>

    <br>

    These submissions have not yet been approved.
    Review the information before approving them as company leads.

</div>


<!--
|--------------------------------------------------------------------------
| Pending Lead Count
|--------------------------------------------------------------------------
-->

<div class="mb-3">

    <strong>
        <?= count($leads) ?>
    </strong>

    pending lead<?= count($leads) === 1 ? '' : 's' ?>

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
                            Date
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (empty($leads)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="text-center text-muted py-5">

                                No pending leads.

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

                                    <?= htmlspecialchars(
                                        $lead['email']
                                    ) ?>

                                </td>


                                <!-- Phone -->

                                <td>

                                    <?= !empty($lead['phone'])
                                        ? htmlspecialchars($lead['phone'])
                                        : '<span class="text-muted">Not provided</span>'
                                    ?>

                                </td>


                                <!-- Services -->

                                <td>

                                    <?php

                                    $services = [];

                                    if (
                                        !empty(
                                            $lead['support_services']
                                        )
                                    ) {

                                        $decodedServices =
                                            json_decode(
                                                $lead['support_services'],
                                                true
                                            );

                                        if (
                                            is_array(
                                                $decodedServices
                                            )
                                        ) {

                                            $services =
                                                $decodedServices;

                                        }

                                    }

                                    ?>


                                    <?php if (!empty($services)): ?>

                                        <?php foreach ($services as $service): ?>

                                            <span
                                                class="badge bg-light text-dark border me-1 mb-1">

                                                <?= htmlspecialchars(
                                                    $service
                                                ) ?>

                                            </span>

                                        <?php endforeach; ?>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Not specified
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Date -->

                                <td>

                                    <?= formatDateTime(
                                        $lead['created_at']
                                    ) ?>

                                </td>


                                <!-- Action -->

                                <td>

                                    <a
                                        href="?page=view-contract-lead&id=<?= (int)$lead['id'] ?>"
                                        class="btn btn-primary btn-sm">

                                        View

                                    </a>

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