<?php

require_once __DIR__ . '/../helpers/auth.php';

requireAdminLogin();

require dirname(__DIR__, 2) . '/config/database.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['password'] !== $_POST['confirm_password']) {

        $error = "Passwords do not match.";

    } else {

        $stmt = $pdo->prepare("
            SELECT id
            FROM agents
            WHERE email = ?
        ");

        $stmt->execute([
            trim($_POST['email'])
        ]);

        if ($stmt->fetch()) {

            $error = "An agent with this email already exists.";

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO agents
                (
                    name,
                    email,
                    password,
                    phone,
                    position,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                trim($_POST['name']),
                trim($_POST['email']),
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                trim($_POST['phone']),
                trim($_POST['position']),
                $_POST['status']
            ]);

            header("Location: ?page=agents");
            exit;

        }
    }
}

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Add Agent
                </h2>

                <?php if (!empty($error)): ?>

<div class="alert alert-danger">

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

                <form method="POST" autocomplete="off">

                    <div class="mb-3">

                        <label class="form-label">
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            autocomplete="off">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Password

                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            autocomplete="new-password"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Confirm Password

                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            class="form-control"
                            autocomplete="new-password"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Position

                        </label>

                        <input
                            type="text"
                            name="position"
                            class="form-control">

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
                        class="btn btn-primary">

                        Save Agent

                    </button>

                    <a
                        href="?page=customers"
                        class="btn btn-secondary ms-2">

                        Cancel

                    </a>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>