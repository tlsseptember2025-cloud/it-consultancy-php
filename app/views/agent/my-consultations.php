<?php

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=agent-login');
    exit;
}

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>

            My Consultations

        </h2>

    </div>

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>Date</th>

                            <th>Time</th>

                            <th>Customer</th>

                            <th>Service</th>

                            <th>Status</th>

                            <th width="120">Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td colspan="6" class="text-center text-muted py-4">

                                No consultations assigned.

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>