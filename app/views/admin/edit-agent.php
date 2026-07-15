<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require CONFIG_PATH . '/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM agents
    WHERE id = ?
");

$stmt->execute([$id]);

$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {

    die('Agent not found.');

}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check duplicate email
    $stmt = $pdo->prepare("
        SELECT id
        FROM agents
        WHERE email = ?
        AND id != ?
    ");

    $stmt->execute([
        trim($_POST['email']),
        $id
    ]);

    if ($stmt->fetch()) {

        $error = 'An agent with this email already exists.';

    } else {

        $stmt = $pdo->prepare("
            UPDATE agents
            SET
                name = ?,
                email = ?,
                phone = ?,
                position = ?,
                status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['position']),
            $_POST['status'],
            $id
        ]);

        header("Location: ?page=agents");
        exit;

    }

}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">

                    Edit Agent

                </h2>

                <?php if (!empty($error)): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">

                            Name

                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="<?= htmlspecialchars($agent['name']) ?>"
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
                            value="<?= htmlspecialchars($agent['email']) ?>"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Phone

                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?= htmlspecialchars($agent['phone']) ?>">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Position

                        </label>

                        <input
                            type="text"
                            name="position"
                            class="form-control"
                            value="<?= htmlspecialchars($agent['position']) ?>">

                    </div>

                    <div class="mb-4">

                        <label class="form-label">

                            Status

                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <option
                                value="Active"
                                <?= $agent['status'] === 'Active' ? 'selected' : '' ?>>

                                Active

                            </option>

                            <option
                                value="Inactive"
                                <?= $agent['status'] === 'Inactive' ? 'selected' : '' ?>>

                                Inactive

                            </option>

                        </select>

                    </div>

                    <button
                        class="btn btn-warning">

                        Update Agent

                    </button>

                    <a
                        href="?page=agents"
                        class="btn btn-secondary ms-2">

                        Cancel

                    </a>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>