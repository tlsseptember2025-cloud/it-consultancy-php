<?php

require CONFIG_PATH . '/database.php';
require dirname(__DIR__) . '/layouts/header-public.php';

$stmt = $pdo->query("
    SELECT * FROM services
    ORDER BY created_at DESC
");

$services = $stmt->fetchAll();

?>

<h1 class="mb-5">
    Our Services
</h1>

<div class="row">
    <?php foreach ($services as $service): ?>
        <div class="col-md-6">
            <div class="card shadow-sm mb-4 h-100">
                <div class="card-body text-center">

                    <?php if (!empty($service['image'])): ?>
                        <img
                            src="../public/uploads/services/<?= htmlspecialchars($service['image']) ?>"
                            alt="<?= htmlspecialchars($service['title']) ?>"
                            class="img-fluid rounded mb-3"
                            style="width:120px;height:120px;object-fit:cover;">
                    <?php endif; ?>

                    <h4 class="card-title">
                        <?= htmlspecialchars($service['title']) ?>
                    </h4>

                    <?php
                    $description = $service['description'] ?? '';
                    $description = strip_tags(
                        $description,
                        '<p><div><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><span>'
                    );
                    $description = preg_replace('~\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $description);
                    $description = preg_replace('~\s(href|src)\s*=\s*(["\'])\s*(javascript:|vbscript:|data:text/html)[^"\']*\2~i', '', $description);
                    $description = preg_replace('~\b(?:expression|javascript|vbscript)\s*\(~i', '', $description);
                    ?>

                    <div class="card-text service-description">
                        <?= $description ?>
                    </div>

                    <h5 class="mt-3 text-primary">
                        <p>Price will be determined after consultation sessions</p>
                    </h5>

                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.service-description {
    text-align: left;
    overflow-wrap: anywhere;
}
.service-description p,
.service-description div {
    margin-bottom: .5rem;
}
.service-description img {
    max-width: 100%;
    height: auto;
}
.service-description ul,
.service-description ol {
    padding-left: 1.5rem;
}
</style>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
