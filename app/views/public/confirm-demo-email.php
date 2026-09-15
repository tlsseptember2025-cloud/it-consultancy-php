<?php

require_once CONFIG_PATH . '/database.php';

$token = trim($_GET['token'] ?? '');

$message = '';
$error = '';

if ($token === '') {

    $error = 'Invalid Demo confirmation link.';

} else {

    $stmt = $pdo->prepare("
        SELECT *
        FROM demo_requests
        WHERE confirmation_token = ?
        LIMIT 1
    ");

    $stmt->execute([$token]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {

        $error = 'This Demo confirmation link is invalid.';

    } elseif (
        $request['status'] !== 'Pending Email Confirmation'
    ) {

        if ($request['status'] === 'Confirmed') {

            $message =
                'Your Demo request has already been confirmed.';

        } else {

            $error =
                'This Demo confirmation link is no longer available.';
        }

    } elseif (
        empty($request['confirmation_expires_at']) ||
        strtotime($request['confirmation_expires_at']) < time()
    ) {

        $stmt = $pdo->prepare("
            UPDATE demo_requests
            SET
                status = 'Expired',
                confirmation_token = NULL
            WHERE id = ?
        ");

        $stmt->execute([$request['id']]);

        $error =
            'This Demo confirmation link has expired. '
            . 'Please submit a new Demo request.';

    } else {

        $stmt = $pdo->prepare("
            UPDATE demo_requests
            SET
                email_confirmed_at = NOW(),
                confirmation_token = NULL,
                confirmation_expires_at = NULL,
                status = 'Confirmed'
            WHERE id = ?
            AND status = 'Pending Email Confirmation'
        ");

        $stmt->execute([$request['id']]);

        if ($stmt->rowCount() === 1) {

            $message =
                'Your company email has been confirmed successfully. '
                . 'Your Demo request is now awaiting administrator review.';

        } else {

            $error =
                'We could not confirm this Demo request. '
                . 'Please submit a new request.';
        }
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">

    <div class="col-lg-6 col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4 text-center">

                <?php if ($message): ?>

                    <div class="alert alert-success">

                        <?= htmlspecialchars($message) ?>

                    </div>

                <?php endif; ?>

                <?php if ($error): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>

                <a
                    href="?page=home"
                    class="btn btn-primary">

                    Return to Website

                </a>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>