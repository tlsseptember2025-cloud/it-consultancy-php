<?php

require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../controllers/AgentController.php';

if (!isset($_SESSION['admin'])) {

    header('Location: ?page=login');
    exit;

}

$controller = new AgentController($pdo);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['password'] !== $_POST['confirm_password']) {

        $error = 'Passwords do not match.';

    } else {

        $result = $controller->store([

            'name'     => trim($_POST['name']),
            'email'    => trim($_POST['email']),
            'password' => $_POST['password'],
            'phone'    => trim($_POST['phone']),
            'position' => trim($_POST['position']),
            'status'   => $_POST['status']

        ]);

        if ($result['success']) {

            $_SESSION['success'] = $result['message'];

            header('Location: ?page=agents');
            exit;

        }

        $error = $result['message'];

    }

}

require __DIR__ . '/layouts/header.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">

            Add Agent

        </h2>

        <?php if ($error): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Agent Name

                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        required>

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Email

                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        required>

                </div>

            </div>

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Phone

                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control">

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Position

                    </label>

                    <input
                        type="text"
                        name="position"
                        class="form-control">

                </div>

            </div>

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Password

                    </label>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        required>

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Confirm Password

                    </label>

                    <input
                        type="password"
                        name="confirm_password"
                        class="form-control"
                        required>

                </div>

            </div>

            <div class="mb-4">

                <label class="form-label">

                    Status

                </label>

                <select
                    name="status"
                    class="form-select">

                    <option value="Active">

                        Active

                    </option>

                    <option value="Inactive">

                        Inactive

                    </option>

                </select>

            </div>

            <button
                class="btn btn-success">

                Save Agent

            </button>

            <a
                href="?page=agents"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>