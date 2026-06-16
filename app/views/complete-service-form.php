<?php

require_once dirname(__DIR__) . '/helpers/auth.php';

requireAdminLogin();

$id = $_GET['id'] ?? 0;

require __DIR__ . '/layouts/header.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Complete Service
        </h2>

        <form
            method="POST"
            action="?page=complete-service&id=<?= $id ?>">

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

<?php require __DIR__ . '/layouts/footer.php'; ?>