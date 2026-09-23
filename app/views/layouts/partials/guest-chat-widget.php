<?php

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/GuestChatHelper.php';

$guestChatAvailability = getGuestChatAvailability($pdo);

$dubaiNow = new DateTime('now', new DateTimeZone('Asia/Dubai'));
$todayDayOfWeek = (int) $dubaiNow->format('N');

$todayScheduleStmt = $pdo->prepare("
    SELECT is_open, open_time, close_time
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

?>
<!--
|--------------------------------------------------------------------------
| Floating Live Support Chat
|--------------------------------------------------------------------------
-->

<div
    id="guestChatFloating"
    style="
        position:fixed;
        right:20px;
        bottom:20px;
        z-index:1050;
    ">

    <button
        type="button"
        id="guestChatFloatingButton"
        class="btn btn-primary rounded-pill shadow-lg px-4 py-3"
        aria-expanded="false"
        aria-controls="guestChatFloatingPanel">

        💬 Live Support

    </button>


    <div
        id="guestChatFloatingPanel"
        class="card shadow-lg mt-2"
        style="
            display:none;
            width:340px;
            max-width:calc(100vw - 40px);
        ">

        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-start mb-3">

                <div>

                    <h5 class="mb-1">
                        💬 Live Support
                    </h5>

                    <div class="small text-muted">
                        Chat directly with our support team.
                    </div>

                </div>

                <button
                    type="button"
                    id="guestChatFloatingClose"
                    class="btn-close"
                    aria-label="Close">
                </button>

            </div>


            <?php if ($guestChatAvailability['available']): ?>

                <div class="alert alert-success py-2 mb-3">

                    <strong>🟢 Chat Available</strong>

                    <div class="small mt-1">
                        An Admin is currently online.
                    </div>

                </div>

                <a
                    href="?page=guest-chat"
                    class="btn btn-primary w-100 mb-3">

                    Start Live Chat

                </a>

            <?php elseif (!$guestChatAvailability['office_open']): ?>

                <div class="alert alert-warning py-2 mb-3">

                    <strong>🔴 Chat Currently Unavailable</strong>

                    <div class="small mt-1">
                        The office is currently closed.
                    </div>

                </div>

            <?php else: ?>

                <div class="alert alert-warning py-2 mb-3">

                    <strong>🟡 Admin Currently Offline</strong>

                    <div class="small mt-1">
                        The office is open, but no Admin is currently online.
                    </div>

                </div>

            <?php endif; ?>


            <div class="border rounded p-3 bg-light">

                <div class="fw-bold mb-2">
                    🕐 Today's Office Hours
                </div>

                <div class="small mb-2">

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


                <div class="mb-2">

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


                <div class="small text-muted">
                    Current Dubai time
                </div>

                <div
                    id="guestChatFloatingClock"
                    class="fw-bold fs-5"
                    style="font-variant-numeric:tabular-nums;">
                    --:--:--
                </div>


                <?php if ($todayIsOpen && $todayOpenTime && $todayCloseTime): ?>

                    <div
                        id="guestChatFloatingCountdown"
                        class="small fw-semibold mt-1">
                        Loading...
                    </div>

                <?php endif; ?>

            </div>


            <div class="small text-muted mt-3">

                Live Chat is available when both office hours are open
                and at least one Admin is online.

            </div>

        </div>

    </div>

</div>


<script>
(function () {

    const button =
        document.getElementById('guestChatFloatingButton');

    const panel =
        document.getElementById('guestChatFloatingPanel');

    const closeButton =
        document.getElementById('guestChatFloatingClose');

    const clockElement =
        document.getElementById('guestChatFloatingClock');

    const countdownElement =
        document.getElementById('guestChatFloatingCountdown');

    if (!button || !panel) {
        return;
    }


    function setPanel(open) {

        panel.style.display =
            open ? 'block' : 'none';

        button.setAttribute(
            'aria-expanded',
            open ? 'true' : 'false'
        );

    }


    button.addEventListener('click', function () {

        setPanel(
            panel.style.display !== 'block'
        );

    });


    closeButton.addEventListener('click', function () {

        setPanel(false);

    });


    document.addEventListener('click', function (event) {

        const container =
            document.getElementById('guestChatFloating');

        if (
            panel.style.display === 'block'
            &&
            container
            &&
            !container.contains(event.target)
        ) {
            setPanel(false);
        }

    });


    if (!clockElement) {
        return;
    }


    const openTime =
        <?= json_encode($todayOpenTime) ?>;

    const closeTime =
        <?= json_encode($todayCloseTime) ?>;


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

        if (!value) {
            return null;
        }

        const parts =
            String(value).split(':');

        return (
            (Number(parts[0]) * 3600) +
            (Number(parts[1]) * 60) +
            Number(parts[2] || 0)
        );

    }


    function formatDuration(totalSeconds) {

        totalSeconds =
            Math.max(
                0,
                Math.floor(totalSeconds)
            );

        const hours =
            Math.floor(totalSeconds / 3600);

        const minutes =
            Math.floor(
                (totalSeconds % 3600) / 60
            );

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

        const now =
            getDubaiNow();


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


        if (!countdownElement) {
            return;
        }


        const currentSeconds =
            (now.getHours() * 3600) +
            (now.getMinutes() * 60) +
            now.getSeconds();


        const openingSeconds =
            timeToSeconds(openTime);

        const closingSeconds =
            timeToSeconds(closeTime);


        if (
            openingSeconds !== null
            &&
            closingSeconds !== null
            &&
            currentSeconds >= openingSeconds
            &&
            currentSeconds < closingSeconds
        ) {

            countdownElement.textContent =
                'Closes in ' +
                formatDuration(
                    closingSeconds - currentSeconds
                );

            countdownElement.className =
                'small fw-semibold mt-1 text-success';

        } else if (
            openingSeconds !== null
            &&
            currentSeconds < openingSeconds
        ) {

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
