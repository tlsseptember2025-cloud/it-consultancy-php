<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

if (isset($_SESSION['demo_user'])) {
    require_once CONFIG_PATH . '/demo-database.php';
    $servicesPdo = $demoPdo;
} else {
    require CONFIG_PATH . '/database.php';
    $servicesPdo = $pdo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');

    // Allow rich-text HTML while removing executable content.
    $description = strip_tags(
        $description,
        '<p><div><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><span>'
    );
    $description = preg_replace('~\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $description);
    $description = preg_replace('~\s(href|src)\s*=\s*(["\'])\s*(javascript:|vbscript:|data:text/html)[^"\']*\2~i', '', $description);
    $description = preg_replace('~\b(?:expression|javascript|vbscript)\s*\(~i', '', $description);
    $image = null;

    if (!empty($_FILES['image']['name'])) {

        $image = time() . '_' . basename($_FILES['image']['name']);

        move_uploaded_file(
            $_FILES['image']['tmp_name'],
            ROOT_PATH . '/public/uploads/services/' . $image
        );
    }

    $stmt = $servicesPdo->prepare("
    INSERT INTO services (title, description, image)
    VALUES (?, ?, ?)
");

    $stmt->execute([
        $title,
        $description,
        $image
    ]);

    header('Location: ?page=services-admin');
    exit;
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Add Service
                </h2>

                <form method="POST" enctype="multipart/form-data">

                    <div class="mb-3">

                        <label class="form-label">
                            Service Title
                        </label>

                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Description
                        </label>

                        <input type="hidden" name="description" id="serviceDescription">

                        
<div class="rich-editor-toolbar" aria-label="Description formatting tools">
    <button type="button" data-editor-command="bold" title="Bold"><strong>B</strong></button>
    <button type="button" data-editor-command="italic" title="Italic"><em>I</em></button>
    <button type="button" data-editor-command="underline" title="Underline"><u>U</u></button>

    <button type="button" data-editor-command="justifyLeft" title="Align left">☰</button>
    <button type="button" data-editor-command="justifyCenter" title="Align center">≡</button>
    <button type="button" data-editor-command="justifyRight" title="Align right">☷</button>
    <button type="button" data-editor-command="justifyFull" title="Justify">☰</button>

    <button type="button" data-editor-command="insertUnorderedList" title="Bulleted list">• List</button>
    <button type="button" data-editor-command="insertOrderedList" title="Numbered list">1. List</button>
    <button type="button" data-editor-command="formatBlock" data-editor-value="blockquote" title="Quote">❝</button>
    <button type="button" data-editor-command="createLink" title="Insert link">Link</button>
    <button type="button" data-editor-command="removeFormat" title="Remove formatting">Tx</button>
</div>


                        <div
                            id="serviceDescriptionEditor"
                            class="rich-editor"
                            contenteditable="true"
                            role="textbox"
                            aria-multiline="true"
                            data-placeholder="Enter the service description..."
                            required></div>

                    </div>

                    <div class="mb-3">

                            <div class="mb-3">

                                <label class="form-label">
                                    Service Image
                                </label>

                                <input
                                    type="file"
                                    name="image"
                                    class="form-control"
                                    accept="image/*">

                            </div>

                    </div>

                    <button class="btn btn-primary">

                        Save Service

                    </button>

                    <a
                        href="?page=services-admin"
                        class="btn btn-secondary ms-2">

                        Cancel

                    </a>

                </form>

            </div>

        </div>

    </div>

</div>


<style>
    .rich-editor-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        padding: 8px;
        border: 1px solid #ced4da;
        border-bottom: 0;
        border-radius: .375rem .375rem 0 0;
        background: #f8f9fa;
    }

    .rich-editor-toolbar button {
        min-width: 36px;
        height: 34px;
        padding: 4px 9px;
        border: 1px solid #ced4da;
        border-radius: .25rem;
        background: #fff;
        cursor: pointer;
        font-size: 14px;
    }

    .rich-editor-toolbar button:hover {
        background: #e9ecef;
    }

    .rich-editor-toolbar button.is-active {
        background: #dee2e6;
        border-color: #adb5bd;
    }

    .rich-editor:empty::before {
        content: attr(data-placeholder);
        color: #6c757d;
        pointer-events: none;
    }

    .rich-editor {
        min-height: 220px;
        padding: 12px;
        border: 1px solid #ced4da;
        border-radius: 0 0 .375rem .375rem;
        background: #fff;
        outline: none;
        overflow-wrap: anywhere;
    }

    .rich-editor:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .25rem rgba(13,110,253,.15);
    }

    .rich-editor img {
        max-width: 100%;
        height: auto;
    }
</style>


<script>
(function () {
    const editor = document.getElementById('serviceDescriptionEditor');
    const hiddenInput = document.getElementById('serviceDescription');

    if (!editor || !hiddenInput) {
        return;
    }

    document.querySelectorAll('[data-editor-command]').forEach(function (button) {
        button.addEventListener('mousedown', function (event) {
            event.preventDefault();

            editor.focus();

            const command = button.getAttribute('data-editor-command');
            const value = button.getAttribute('data-editor-value') || null;

            if (command === 'createLink') {
                const url = window.prompt('Enter the link URL:');
                if (url) {
                    document.execCommand(command, false, url);
                }
            } else {
                document.execCommand(command, false, value);
            }

            syncEditor();
        });
    });

    editor.addEventListener('input', syncEditor);

    editor.closest('form').addEventListener('submit', function (event) {
        syncEditor();

        if (!editor.innerText.trim()) {
            event.preventDefault();
            alert('Please enter a service description.');
            editor.focus();
        }
    });

    function syncEditor() {
        hiddenInput.value = editor.innerHTML;
    }
})();
</script>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>