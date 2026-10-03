<?php

if (
    !isset($_SESSION['demo_super_admin']) ||
    !is_array($_SESSION['demo_super_admin'])
) {
    header('Location: ?page=demo-super-admin-login');
    exit;
}

require_once CONFIG_PATH . '/demo-database.php';

$csrfToken = $_SESSION['csrf_token'] ?? '';

if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Handle Create Session
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (
        $submittedToken === ''
        || !hash_equals($csrfToken, $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $demoTenantId = filter_input(
        INPUT_POST,
        'demo_tenant_id',
        FILTER_VALIDATE_INT
    );

    $title = trim((string) ($_POST['title'] ?? ''));

    $scheduledAt = trim(
        (string) ($_POST['scheduled_at'] ?? '')
    );

    $zoomMeetingLink = trim(
        (string) ($_POST['zoom_meeting_link'] ?? '')
    );

    $durationMinutes = filter_input(
        INPUT_POST,
        'duration_minutes',
        FILTER_VALIDATE_INT
    );

    $notes = trim((string) ($_POST['notes'] ?? ''));

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (!$demoTenantId || $demoTenantId <= 0) {

        $error = 'Please select a Demo company.';

    } elseif ($title === '') {

        $error = 'Session title is required.';

    } elseif (mb_strlen($title) > 255) {

        $error = 'Session title is too long.';

    } elseif ($scheduledAt === '') {

        $error = 'Date and time are required.';

    } elseif (
        $durationMinutes !== false
        && $durationMinutes !== null
        && $durationMinutes < 1
    ) {

        $error = 'Duration must be at least 1 minute.';

    } elseif ($zoomMeetingLink !== '') {

        if (
            !filter_var(
                $zoomMeetingLink,
                FILTER_VALIDATE_URL
            )
        ) {
            $error = 'Please enter a valid Zoom meeting link.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Demo Company
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $tenantStmt = $demoPdo->prepare("
            SELECT
                id,
                company_name,
                company_domain,
                expires_at,
                status
            FROM demo_tenants
            WHERE id = ?
              AND status = 'Active'
              AND expires_at >= NOW()
            LIMIT 1
        ");

        $tenantStmt->execute([
            $demoTenantId
        ]);

        $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenant) {
            $error = 'The selected Demo company is not active.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Session
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $createdBy = (int) (
            $_SESSION['demo_super_admin']['id'] ?? 0
        );

        if ($createdBy <= 0) {
            $error = 'Unable to determine the Super Admin account.';
        }
    }

    if ($error === '') {

        try {

            $insertStmt = $demoPdo->prepare("
                INSERT INTO demo_support_sessions (
                    demo_tenant_id,
                    title,
                    scheduled_at,
                    zoom_meeting_link,
                    duration_minutes,
                    status,
                    created_by,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, 'Scheduled', ?, ?)
            ");

            $insertStmt->execute([
                $demoTenantId,
                $title,
                $scheduledAt . ':00',
                $zoomMeetingLink !== ''
                    ? $zoomMeetingLink
                    : null,
                (
                    $durationMinutes !== false
                    && $durationMinutes !== null
                )
                    ? $durationMinutes
                    : null,
                $createdBy,
                $notes !== ''
                    ? $notes
                    : null
            ]);

            $message = 'Support & Training session created successfully.';

        } catch (Throwable $e) {

            error_log(
                'Demo support session creation failed: '
                . $e->getMessage()
            );

            $error = 'Unable to create the support session.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load Active Demo Companies
|--------------------------------------------------------------------------
*/

$companies = [];

try {

    $companyStmt = $demoPdo->query("
        SELECT
            id,
            company_name,
            company_domain,
            expires_at
        FROM demo_tenants
        WHERE status = 'Active'
          AND expires_at >= NOW()
        ORDER BY company_name ASC
    ");

    $companies = $companyStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        'Demo support company lookup failed: '
        . $e->getMessage()
    );

    $error = 'Unable to load Demo companies.';
}

/*
|--------------------------------------------------------------------------
| Load Support Sessions
|--------------------------------------------------------------------------
*/

$sessions = [];

try {

    $sessionStmt = $demoPdo->query("
        SELECT
            s.id,
            s.demo_tenant_id,
            s.title,
            s.scheduled_at,
            s.zoom_meeting_link,
            s.duration_minutes,
            s.status,
            s.notes,
            s.created_at,
            t.company_name
        FROM demo_support_sessions s
        INNER JOIN demo_tenants t
            ON t.id = s.demo_tenant_id
        ORDER BY
            s.scheduled_at DESC,
            s.id DESC
    ");

    $sessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        'Demo support session lookup failed: '
        . $e->getMessage()
    );

    $error = 'Unable to load Support & Training sessions.';
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">

        <div>
            <h1 class="mb-1">
                Support &amp; Training
            </h1>

            <p class="text-muted mb-0">
                Schedule and manage live support and training sessions for Demo companies.
            </p>
        </div>

    </div>


    <?php if ($message !== ''): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <div class="row g-4">

        <!-- Create Session -->

        <div class="col-lg-5">

            <div class="card shadow-sm">

                <div class="card-header">

                    <h5 class="mb-0">
                        <i class="bi bi-camera-video me-1"></i>
                        Create Support Session
                    </h5>

                </div>

                <div class="card-body">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                $csrfToken,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

                        <div class="mb-3">

                            <label
                                for="demo_tenant_id"
                                class="form-label">
                                Demo Company
                            </label>

                            <select
                                id="demo_tenant_id"
                                name="demo_tenant_id"
                                class="form-select"
                                required>

                                <option value="">
                                    Select Demo company
                                </option>

                                <?php foreach ($companies as $company): ?>

                                    <option
                                        value="<?= (int) $company['id'] ?>">

                                        <?= htmlspecialchars(
                                            $company['company_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="mb-3">

                            <label
                                for="title"
                                class="form-label">
                                Session Title
                            </label>

                            <input
                                type="text"
                                id="title"
                                name="title"
                                class="form-control"
                                maxlength="255"
                                placeholder="e.g. Customer Support Training"
                                required>

                        </div>


                        <div class="mb-3">

                            <label
                                for="scheduled_at"
                                class="form-label">
                                Date &amp; Time
                            </label>

                            <input
                                type="datetime-local"
                                id="scheduled_at"
                                name="scheduled_at"
                                class="form-control"
                                required>

                            <div class="form-text">
                                Use UAE local time.
                            </div>

                        </div>


                        <div class="mb-3">

                            <label
                                for="zoom_meeting_link"
                                class="form-label">
                                Zoom Meeting Link
                            </label>

                            <input
                                type="url"
                                id="zoom_meeting_link"
                                name="zoom_meeting_link"
                                class="form-control"
                                placeholder="https://zoom.us/..."
                            >

                        </div>


                        <div class="mb-3">

                            <label
                                for="duration_minutes"
                                class="form-label">
                                Duration
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    id="duration_minutes"
                                    name="duration_minutes"
                                    class="form-control"
                                    min="1"
                                    placeholder="40">

                                <span class="input-group-text">
                                    minutes
                                </span>

                            </div>

                        </div>


                        <div class="mb-3">

                            <label
                                for="notes"
                                class="form-label">
                                Notes
                            </label>

                            <textarea
                                id="notes"
                                name="notes"
                                class="form-control"
                                rows="5"
                                placeholder="Training topics, preparation notes, follow-up items..."></textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="bi bi-plus-circle me-1"></i>
                            Create Session

                        </button>

                    </form>

                </div>

            </div>

        </div>


        <!-- Existing Sessions -->

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header">

                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event me-1"></i>
                        Support Sessions
                    </h5>

                </div>

                <div class="card-body p-0">

                    <?php if (empty($sessions)): ?>

                        <div class="p-4">

                            <div class="alert alert-secondary mb-0">

                                No Support &amp; Training sessions have been created yet.

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th>
                                            Company
                                        </th>

                                        <th>
                                            Session
                                        </th>

                                        <th>
                                            Date / Time
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($sessions as $session): ?>

                                        <tr>

                                            <td>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $session['company_name'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </strong>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $session['title'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                                <?php if (!empty($session['zoom_meeting_link'])): ?>

                                                    <div class="small mt-1">

                                                        <a
                                                            href="<?= htmlspecialchars(
                                                                $session['zoom_meeting_link'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                            target="_blank"
                                                            rel="noopener noreferrer">

                                                            <i class="bi bi-camera-video me-1"></i>
                                                            Zoom Meeting

                                                        </a>

                                                    </div>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    date(
                                                        'd/m/Y h:i A',
                                                        strtotime(
                                                            $session['scheduled_at']
                                                        )
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                                <?php if (!empty($session['duration_minutes'])): ?>

                                                    <div class="small text-muted">

                                                        <?= (int) $session['duration_minutes'] ?>
                                                        minutes

                                                    </div>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <?php

                                                $status = $session['status'] ?? 'Scheduled';

                                                $statusClass = match ($status) {
                                                    'Scheduled' => 'bg-primary',
                                                    'Completed' => 'bg-success',
                                                    'Cancelled' => 'bg-secondary',
                                                    default => 'bg-secondary'
                                                };

                                                ?>

                                                <span
                                                    class="badge <?= $statusClass ?>">

                                                    <?= htmlspecialchars(
                                                        $status,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>