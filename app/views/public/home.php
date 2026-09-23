<?php

require_once HELPER_PATH . '/email.php';
require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/GuestChatHelper.php';

$guestChatAvailability = getGuestChatAvailability($pdo);

/*
|--------------------------------------------------------------------------
| Today's Guest Chat Schedule
|--------------------------------------------------------------------------
*/

$dubaiNow = new DateTime('now', new DateTimeZone('Asia/Dubai'));
$todayDayOfWeek = (int) $dubaiNow->format('N');

$todayScheduleStmt = $pdo->prepare("
    SELECT
        is_open,
        open_time,
        close_time
    FROM guest_chat_office_hours
    WHERE day_of_week = ?
    LIMIT 1
");

$todayScheduleStmt->execute([$todayDayOfWeek]);

$todaySchedule = $todayScheduleStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'is_open' => 0,
    'open_time' => null,
    'close_time' => null
];

$todayIsOpen = (int) ($todaySchedule['is_open'] ?? 0) === 1;
$todayOpenTime = $todaySchedule['open_time'] ?? null;
$todayCloseTime = $todaySchedule['close_time'] ?? null;
$todayName = $dubaiNow->format('l');

$todayTimingText = 'Closed today';

if ($todayIsOpen && $todayOpenTime && $todayCloseTime) {
    $todayTimingText =
        date('g:i A', strtotime($todayOpenTime))
        . ' – '
        . date('g:i A', strtotime($todayCloseTime));
}

/*
|--------------------------------------------------------------------------
| Contract Lead Form
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Anti-Spam
|--------------------------------------------------------------------------
|
| Honeypot field:
| Real users never see or fill this field.
| Basic bots often do.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_contract_lead'])
) {

    /*
    |--------------------------------------------------------------------------
    | Honeypot spam protection
    |--------------------------------------------------------------------------
    */

    if (!empty($_POST['website'])) {

        $error =
            'Unable to submit the form. Please try again.';

    }

    /*
    |--------------------------------------------------------------------------
    | Basic submission-time protection
    |--------------------------------------------------------------------------
    |
    | A normal user should need at least a few seconds
    | to read and complete the form.
    |
    */

    if (
        empty($error)
        &&
        isset($_POST['form_started_at'])
    ) {

        $formStartedAt = (int) $_POST['form_started_at'];

        if (
            $formStartedAt > 0
            &&
            (time() - $formStartedAt) < 3
        ) {

            $error =
                'Unable to submit the form. Please try again.';

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Collect form data
    |--------------------------------------------------------------------------
    */

    if (empty($error)) {

        $companyName =
            trim($_POST['company_name'] ?? '');

        $contactPerson =
            trim($_POST['contact_person'] ?? '');

        $email =
            trim($_POST['email'] ?? '');

        $phone =
            trim($_POST['phone'] ?? '');

        $contractTerm =
            trim($_POST['contract_term'] ?? '');

        $supportCoverage =
            trim($_POST['support_coverage'] ?? '');

        $startTimeframe =
            trim($_POST['start_timeframe'] ?? '');

        $supportServices =
            $_POST['support_services'] ?? [];

        $marketingConsent =
            isset($_POST['marketing_consent'])
            ? 1
            : 0;


        /*
        |--------------------------------------------------------------------------
        | Validate required fields
        |--------------------------------------------------------------------------
        */

        if (
            $companyName === '' ||
            $contactPerson === '' ||
            $email === '' ||
            $phone === ''
        ) {

            $error =
                'Please complete all required fields.';

        }

        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error =
                'Please enter a valid email address.';

        }

        elseif (
            !is_array($supportServices)
            ||
            empty($supportServices)
        ) {

            $error =
                'Please select at least one service you are interested in.';

        }

        elseif (
            !in_array(
                $contractTerm,
                ['Monthly', 'Yearly'],
                true
            )
        ) {

            $error =
                'Please select a contract preference.';

        }

        /*
        |--------------------------------------------------------------------------
        | Save Lead
        |--------------------------------------------------------------------------
        */

        if (empty($error)) {

            /*
            |--------------------------------------------------------------------------
            | Clean service selections
            |--------------------------------------------------------------------------
            */

            $allowedServices = [

                'Remote IT Support',

                'Software Services',

                'E-commerce Website',

                'Corporate Website',

                'Website Maintenance'

            ];

            $supportServices =
                array_values(
                    array_intersect(
                        $allowedServices,
                        $supportServices
                    )
                );


            if (empty($supportServices)) {

                $error =
                    'Please select at least one valid service.';

            }

        }


        if (empty($error)) {

            /*
            |--------------------------------------------------------------------------
            | Store structured service selections as JSON
            |--------------------------------------------------------------------------
            */

            $supportServicesJson =
                json_encode(
                    $supportServices,
                    JSON_UNESCAPED_UNICODE
                );


            /*
            |--------------------------------------------------------------------------
            | Insert Lead
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO contract_leads
                (
                    company_name,
                    contact_person,
                    email,
                    phone,
                    contract_term,
                    support_services,
                    support_coverage,
                    start_timeframe,
                    marketing_consent
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([

                $companyName,

                $contactPerson,

                $email,

                $phone,

                $contractTerm,

                $supportServicesJson,

                $supportCoverage,

                $startTimeframe,

                $marketingConsent

            ]);


            /*
            |--------------------------------------------------------------------------
            | Email notification
            |--------------------------------------------------------------------------
            */

            sendContractLeadNotification(

                $companyName,

                $contactPerson,

                $email,

                $phone,

                null,

                implode(
                    ', ',
                    $supportServices
                )
                .
                "\n\nContract Preference: "
                .
                $contractTerm
                .
                "\n\nSupport Coverage: "
                .
                $supportCoverage
                .
                "\n\nPreferred Start Timeframe: "
                .
                $startTimeframe

            );


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            $success =
                'Thank you for your interest! We will contact you shortly.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| Public Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-public.php';

?>


<?php if (!empty($success)): ?>

    <div class="alert alert-success alert-dismissible fade show" role="alert">

        <?= htmlspecialchars($success) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

<?php endif; ?>


<?php if (!empty($error)): ?>

    <div class="alert alert-danger alert-dismissible fade show" role="alert">

        <?= htmlspecialchars($error) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| Hero Section
|--------------------------------------------------------------------------
-->

<div class="p-5 mb-4 bg-light rounded-3">

    <div class="container-fluid py-5">

        <h1 class="display-5 fw-bold">

            <?= PRODUCT_NAME ?>

        </h1>

        <p class="col-md-8 fs-4">

            Manage customers, services, requests, invoices,
            payments, consultations and more from one platform.

        </p>

        <a
            class="btn btn-primary btn-lg"
            href="?page=demo">

            Explore Demo

        </a>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Features
|--------------------------------------------------------------------------
-->

<div class="mt-5 mb-5">

    <div class="text-center mb-5">

        <h2 class="fw-bold">

            Powerful Features

        </h2>

        <p class="text-muted">

            Everything you need to manage your IT consultancy business from one place.

        </p>

    </div>


    <div class="row g-4">


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">👥</div>

                    <h5>Customer Management</h5>

                    <p class="text-muted">

                        Manage customers, profiles and communication.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">📋</div>

                    <h5>Request Management</h5>

                    <p class="text-muted">

                        Track customer requests from start to completion.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">💳</div>

                    <h5>Payments</h5>

                    <p class="text-muted">

                        Record payments and process refund requests.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">📅</div>

                    <h5>Consultations</h5>

                    <p class="text-muted">

                        Schedule and manage customer consultations.

                    </p>

                </div>

            </div>

        </div>

    </div>


    <div class="row g-4 mt-1">


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">📊</div>

                    <h5>Dashboard</h5>

                    <p class="text-muted">

                        View business statistics and important activities.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">🔔</div>

                    <h5>Notifications</h5>

                    <p class="text-muted">

                        Stay updated with customer and system notifications.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">📄</div>

                    <h5>Reports</h5>

                    <p class="text-muted">

                        Generate reports for business insights.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">⚙️</div>

                    <h5>Administration</h5>

                    <p class="text-muted">

                        Manage users, settings and system configuration.

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Additional Features
|--------------------------------------------------------------------------
-->

<div class="row">


    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>Easy Installation</h4>

                <p>

                    Install the software in minutes using the built-in installation wizard.

                </p>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>Email Notifications</h4>

                <p>

                    Automatically notify administrators and customers about important activities.

                </p>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>Multi-User Access</h4>

                <p>

                                    </p>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Business Contract Application + Live Support Chat
|--------------------------------------------------------------------------
-->

<div class="row mt-5 mb-5">

    <div class="col-12 mb-4">

<div class="card shadow-sm">

    <div class="card-body p-4">


        <h3 class="mb-3">

            🏢 Business IT Support Plans

        </h3>


        <p class="text-muted">

            Looking for reliable monthly or annual IT support,
            software services or website services for your company?

            Tell us what you are interested in and we'll contact you
            with a suitable solution.

        </p>


        <form method="POST">


            <!--
            |--------------------------------------------------------------------------
            | Anti-spam honeypot
            |--------------------------------------------------------------------------
            -->

            <div
                style="
                    position:absolute;
                    left:-9999px;
                    width:1px;
                    height:1px;
                    overflow:hidden;
                "
                aria-hidden="true">

                <label>
                    Website
                </label>

                <input
                    type="text"
                    name="website"
                    tabindex="-1"
                    autocomplete="off">

            </div>


            <input
                type="hidden"
                name="form_started_at"
                value="<?= time() ?>">


            <!--
            |--------------------------------------------------------------------------
            | Company Information
            |--------------------------------------------------------------------------
            -->

            <div class="row">


                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Company Name

                    </label>

                    <input
                        type="text"
                        name="company_name"
                        class="form-control"
                        required>

                </div>


                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Contact Person

                    </label>

                    <input
                        type="text"
                        name="contact_person"
                        class="form-control"
                        required>

                </div>


            </div>


            <div class="row">


                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Email Address

                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        required>

                </div>


                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Phone Number

                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        required>

                </div>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Service Interest
            |--------------------------------------------------------------------------
            -->

            <div class="card border mb-4">

                <div class="card-body">


                    <h5 class="mb-3">

                        What services are you interested in?

                    </h5>


                    <p class="text-muted small">

                        Select all that apply.

                    </p>


                    <!-- IT Support / Software -->

                    <div class="mb-4">


                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="support_services[]"
                                value="Remote IT Support"
                                id="remoteIT">

                            <label
                                class="form-check-label"
                                for="remoteIT">

                                <strong>
                                    IT Support / Software Services
                                </strong>
                                <br>

                                <span class="text-muted">

                                    Remote IT Support

                                </span>

                            </label>

                        </div>


                    </div>


                    <!-- Website Services -->

                    <div>


                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="support_services[]"
                                value="E-commerce Website"
                                id="ecommerceWebsite">

                            <label
                                class="form-check-label"
                                for="ecommerceWebsite">

                                <strong>
                                    Website Services
                                </strong>
                                <br>

                                <span class="text-muted">

                                    E-commerce Website

                                </span>

                            </label>

                        </div>


                    </div>


                </div>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Support Coverage
            |--------------------------------------------------------------------------
            -->

            <div class="mb-3">


                <label class="form-label">

                    Preferred Support Coverage

                </label>


                <select
                    name="support_coverage"
                    class="form-select"
                    required>


                    <option value="">

                        Select support coverage

                    </option>


                    <option value="Remote">

                        Remote

                    </option>


                    <option value="On-site">

                        On-site

                    </option>


                    <option value="Hybrid">

                        Hybrid

                    </option>


                </select>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Contract Preference
            |--------------------------------------------------------------------------
            -->

            <div class="mb-3">


                <label class="form-label">

                    Contract Preference

                </label>


                <select
                    name="contract_term"
                    class="form-select"
                    required>


                    <option value="">

                        Select contract preference

                    </option>


                    <option value="Monthly">

                        Monthly

                    </option>


                    <option value="Yearly">

                        Yearly

                    </option>


                </select>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Start Timeframe
            |--------------------------------------------------------------------------
            -->

            <div class="mb-4">


                <label class="form-label">

                    When would you like to start?

                </label>


                <select
                    name="start_timeframe"
                    class="form-select"
                    required>


                    <option value="">

                        Select timeframe

                    </option>


                    <option value="Immediately">

                        Immediately

                    </option>


                    <option value="Within 1 Month">

                        Within 1 Month

                    </option>


                    <option value="1-3 Months">

                        1–3 Months

                    </option>


                    <option value="Just Exploring">

                        Just Exploring

                    </option>


                </select>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Marketing Consent
            |--------------------------------------------------------------------------
            -->

            <div class="form-check mb-4">


                <input
                    class="form-check-input"
                    type="checkbox"
                    name="marketing_consent"
                    value="1"
                    id="marketingConsent">


                <label
                    class="form-check-label"
                    for="marketingConsent">

                    I would like to receive occasional updates and special offers.

                </label>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Submit
            |--------------------------------------------------------------------------
            -->

            <button
                type="submit"
                name="submit_contract_lead"
                class="btn btn-success btn-lg">


                I'm Interested


            </button>


        </form>


    </div>

</div>

    </div>

    <div class="col-12">

<!--
|--------------------------------------------------------------------------
| Live Chat
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm">

    <div class="card-body p-4">

        <div class="text-center">

            <h3 class="mb-3">
                💬 Live Support Chat
            </h3>

            <?php if ($guestChatAvailability['available']): ?>

                <p class="text-muted mb-4">
                    Need help or have a question?
                    Chat directly with our support team.
                </p>

                <a
                    href="?page=guest-chat"
                    class="btn btn-primary btn-lg px-4">

                    Start Live Chat

                </a>

                <div class="mt-3 text-muted small">
                    Available during office hours when at least one Admin is online.
                </div>

            <?php else: ?>

                <div
                    class="border rounded p-4 text-start"
                    style="background:#fff8e1;">

                    <div class="d-flex align-items-center mb-3">

                        <span
                            class="badge bg-danger fs-6 me-2 px-3 py-2">

                            Unavailable

                        </span>

                        <h4 class="mb-0">

                            Live Chat is currently unavailable

                        </h4>

                    </div>


                    <div class="alert alert-warning mb-4">

                        <strong>Why can't I start a chat?</strong>

                        <div class="mt-1">

                            <?php if (!$guestChatAvailability['office_open']): ?>

                                Our office is currently outside its configured working hours.

                            <?php elseif (!$guestChatAvailability['admin_online']): ?>

                                Our office is currently open, but no Admin is online.

                            <?php endif; ?>

                        </div>

                    </div>


                    <h5 class="mb-3">
                        Live Chat is available when BOTH conditions are met:
                    </h5>


                    <div class="row g-3 mb-4">

                        <div class="col-md-6">

                            <div class="border rounded p-3 h-100 bg-white">

                                <div class="d-flex justify-content-between align-items-start gap-3">

                                    <div>

                                        <h6 class="fw-bold mb-2">
                                            🕐 Today's Office Hours
                                        </h6>

                                        <div class="small">

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $todayName,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </strong>

                                            <span class="ms-1">
                                                <?= htmlspecialchars(
                                                    $todayTimingText,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>

                                        </div>

                                        <div class="mt-2">

                                            <?php if ($guestChatAvailability['office_open']): ?>

                                                <span class="badge bg-success">
                                                    Office currently open
                                                </span>

                                            <?php elseif ($todayIsOpen): ?>

                                                <span class="badge bg-danger">
                                                    Office currently closed
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-secondary">
                                                    Closed today
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                    <div
                                        class="text-center border rounded px-3 py-2"
                                        style="min-width:190px; background:#f8f9fa;">

                                        <div class="small text-muted mb-1">
                                            Current time
                                        </div>

                                        <div
                                            id="guestChatLiveClock"
                                            class="fw-bold fs-4"
                                            style="font-variant-numeric:tabular-nums;">
                                            --:--:--
                                        </div>

                                        <?php if ($todayIsOpen && $todayOpenTime && $todayCloseTime): ?>

                                            <div
                                                id="guestChatCountdown"
                                                class="small fw-semibold mt-1">
                                                Loading...
                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="border rounded p-3 h-100 bg-white">

                                <h6 class="fw-bold mb-2">

                                    👤 Admin Availability

                                </h6>

                                <div class="small">

                                    At least one Admin must be logged in
                                    and currently active.

                                </div>

                                <div class="mt-2">

                                    <?php if ($guestChatAvailability['admin_online']): ?>

                                        <span class="badge bg-success">
                                            Admin currently online
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">
                                            No Admin currently online
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="text-center">

                        <button
                            type="button"
                            class="btn btn-secondary btn-lg px-4"
                            disabled>

                            Live Chat Unavailable

                        </button>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php if ($todayIsOpen && $todayOpenTime && $todayCloseTime): ?>

<script>
(function () {

    const clockElement =
        document.getElementById('guestChatLiveClock');

    const countdownElement =
        document.getElementById('guestChatCountdown');

    if (!clockElement || !countdownElement) {
        return;
    }

    const openTime = <?= json_encode($todayOpenTime) ?>;
    const closeTime = <?= json_encode($todayCloseTime) ?>;

    function getDubaiNow() {

        const now = new Date();

        const parts = new Intl.DateTimeFormat(
            'en-CA',
            {
                timeZone: 'Asia/Dubai',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hourCycle: 'h23'
            }
        ).formatToParts(now);

        const values = {};

        parts.forEach(function (part) {
            if (part.type !== 'literal') {
                values[part.type] = part.value;
            }
        });

        return new Date(
            Number(values.year),
            Number(values.month) - 1,
            Number(values.day),
            Number(values.hour),
            Number(values.minute),
            Number(values.second)
        );
    }

    function timeToSeconds(value) {

        const parts = String(value).split(':');

        return (
            (Number(parts[0]) * 3600) +
            (Number(parts[1]) * 60) +
            Number(parts[2] || 0)
        );

    }

    function formatDuration(totalSeconds) {

        totalSeconds = Math.max(0, Math.floor(totalSeconds));

        const hours =
            Math.floor(totalSeconds / 3600);

        const minutes =
            Math.floor((totalSeconds % 3600) / 60);

        const seconds =
            totalSeconds % 60;

        return (
            String(hours).padStart(2, '0') +
            ':' +
            String(minutes).padStart(2, '0') +
            ':' +
            String(seconds).padStart(2, '0')
        );

    }

    function updateClock() {

        const now = getDubaiNow();

        clockElement.textContent =
            new Intl.DateTimeFormat(
                'en-US',
                {
                    timeZone: 'Asia/Dubai',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                }
            ).format(new Date());

        const currentSeconds =
            (now.getHours() * 3600) +
            (now.getMinutes() * 60) +
            now.getSeconds();

        const openingSeconds =
            timeToSeconds(openTime);

        const closingSeconds =
            timeToSeconds(closeTime);

        if (
            currentSeconds >= openingSeconds &&
            currentSeconds < closingSeconds
        ) {

            countdownElement.textContent =
                'Closes in ' +
                formatDuration(
                    closingSeconds - currentSeconds
                );

            countdownElement.className =
                'small fw-semibold mt-1 text-success';

        } else if (currentSeconds < openingSeconds) {

            countdownElement.textContent =
                'Opens in ' +
                formatDuration(
                    openingSeconds - currentSeconds
                );

            countdownElement.className =
                'small fw-semibold mt-1 text-primary';

        } else {

            countdownElement.textContent =
                'Office hours ended';

            countdownElement.className =
                'small fw-semibold mt-1 text-danger';

        }

    }

    updateClock();
    setInterval(updateClock, 1000);

})();
</script>

<?php endif; ?>

<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>
