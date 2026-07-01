<?php

require dirname(__DIR__, 2) . '/config/database.php';

$stmt = $pdo->query("
    SELECT * FROM services
    ORDER BY created_at DESC
");

$services = $stmt->fetchAll();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<h1 class="mb-5">
    Our Services
</h1>

<div class="row">

    <?php foreach ($services as $service): ?>

        <div class="col-md-4">

            <div class="card shadow-sm mb-4 h-100">

                <div class="card-body text-center">

                    <?php if (!empty($service['image'])): ?>

                        <img
                            src="../public/uploads/<?= htmlspecialchars($service['image']) ?>"
                            alt="<?= htmlspecialchars($service['title']) ?>"
                            class="img-fluid rounded mb-3"
                            style="
                                width:120px;
                                height:120px;
                                object-fit:cover;
                            ">

                    <?php endif; ?>

                    <h4 class="card-title">

                        <?= htmlspecialchars($service['title']) ?>

                    </h4>

                    <p class="card-text">

                        <?= htmlspecialchars($service['description']) ?>

                    </p>

                    <h5 class="mt-3 text-primary">

                        <p>Price will be determined after consultation sessions</p>

                    </h5>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>