<?php

function shouldShowMeetingLink(
    string $slotDate,
    string $slotTime
): bool {

    $consultationStart = strtotime(
        $slotDate . ' ' . $slotTime
    );

    $visibleFrom = $consultationStart - (10 * 60);

    $visibleUntil = $visibleFrom + (60 * 60);

    return
        time() >= $visibleFrom
        &&
        time() < $visibleUntil;

}