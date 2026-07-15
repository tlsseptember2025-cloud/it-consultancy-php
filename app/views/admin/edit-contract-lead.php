<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM contract_leads
    WHERE id = ?
");

$stmt->execute([$id]);

$lead = $stmt->fetch();

if (!$lead) {
    die('Lead not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE contract_leads
        SET status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['status'],
        $id
    ]);

    header('Location: ?page=contract-leads');
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<h2>Edit Company Lead</h2>

<form method="POST">

    <div class="mb-3">
        <label>Company</label>
        <input
            class="form-control"
            value="<?= htmlspecialchars($lead['company_name']) ?>"
            readonly>
    </div>

    <div class="mb-3">
        <label>Status</label>

        <select
            name="status"
            class="form-select">

            <option <?= $lead['status']=='New'?'selected':'' ?>>
                New
            </option>

            <option <?= $lead['status']=='Contacted'?'selected':'' ?>>
                Contacted
            </option>

            <option <?= $lead['status']=='Converted'?'selected':'' ?>>
                Converted
            </option>

            <option <?= $lead['status']=='Closed'?'selected':'' ?>>
                Closed
            </option>

        </select>

    </div>

    <button
        type="submit"
        class="btn btn-primary">

        Save

    </button>

    <a
        href="?page=contract-leads"
        class="btn btn-secondary">

        Cancel

    </a>

</form>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>