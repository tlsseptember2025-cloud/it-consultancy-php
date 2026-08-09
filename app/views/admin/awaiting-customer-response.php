<?php 
require dirname(__DIR__) . '/layouts/header-admin.php'; 
require_once APP_PATH . '/helpers/WorkflowHelper.php';

$hasVerificationRows = false;

foreach ($requests as $request) {

    if (
        ($request['workflow_stage'] ?? '')
        !== 'Closure Agreement Sent'
    ) {
        $hasVerificationRows = true;
        break;
    }
}

?>

<h2 class="mb-4">
    Awaiting Customer Response
</h2>

<div class="card shadow-sm">

    <div class="card-body">

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead class="table-dark">

                    <tr>

                        <th
                            class="text-center"
                            style="width:100px;">

                            Request #

                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Service
                        </th>


                        <?php if ($hasVerificationRows): ?>

                            <th>
                                Email #
                            </th>

                            <th>
                                Response Deadline
                            </th>

                        <?php endif; ?>


                        <th>
                            Status
                        </th>

                        <th width="180">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (empty($requests)): ?>

                        <tr>

                            <td
                                colspan="<?= $hasVerificationRows ? 7 : 5 ?>"
                                class="text-center text-muted py-4">

                                No requests awaiting customer response.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($requests as $request): ?>

                            <?php

                            $isClosureAgreement =
                                (
                                    ($request['workflow_stage'] ?? '')
                                    === 'Closure Agreement Sent'
                                );

                            ?>


                            <tr>


                                <!-- Request -->

                                <td class="text-center">

                                    <strong>
                                        #<?= (int) $request['id'] ?>
                                    </strong>

                                </td>


                                <!-- Customer -->

                                <td>

                                    <?= htmlspecialchars(
                                        $request['customer_name']
                                    ) ?>

                                </td>


                                <!-- Service -->

                                <td>

                                    <?= htmlspecialchars(
                                        $request['service_name']
                                    ) ?>

                                </td>


                                <?php if ($hasVerificationRows): ?>


                                    <!-- Email Number -->

                                    <td>

                                        <?php if ($isClosureAgreement): ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-primary">

                                                <?= (int) (
                                                    $request[
                                                        'verification_email_count'
                                                    ] ?? 0
                                                ) ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Response Deadline -->

                                    <td>

                                        <?php if (
                                            $isClosureAgreement
                                        ): ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php elseif (
                                            !empty(
                                                $request[
                                                    'customer_response_deadline'
                                                ]
                                            )
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $request[
                                                    'customer_response_deadline'
                                                ]
                                            ) ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                <?php endif; ?>


                                <!-- Workflow Status -->

                                <td>

                                    <?= workflowBadge(
                                        $request['workflow_stage']
                                    ) ?>

                                </td>


                                <!-- Action -->

                                <td>

                                    <?php if ($isClosureAgreement): ?>

                                        <a
                                            href="?page=admin-close-request&id=<?= (int) $request['id'] ?>"
                                            class="btn btn-primary btn-sm">

                                            View / Resend

                                        </a>

                                    <?php else: ?>

                                        <a
                                            href="?page=view-awaiting-customer-response&id=<?= (int) $request['id'] ?>"
                                            class="btn btn-primary btn-sm">

                                            View

                                        </a>

                                    <?php endif; ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>