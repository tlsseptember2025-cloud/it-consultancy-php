<?php

$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}

} else {

    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';

$id = (int) ($_GET['id'] ?? 0);

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Complete Service
        </h2>

        <form
            method="POST"
            action="?page=complete-service&id=<?= $id ?>">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="mb-3">

                <label class="form-label">
                    Completion Notes
                </label>

                <textarea
                    name="completion_notes"
                    class="form-control"
                    rows="6"
                    placeholder="Describe the work performed..."
                    required></textarea>

            </div>

            <button
                type="submit"
                class="btn btn-success">

                Complete Service

            </button>

            <a
                href="?page=requests"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>