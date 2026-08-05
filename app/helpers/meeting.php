<?php

/**
 * Get the correct meeting link based on
 * the customer's selected meeting method
 * and the consultation time.
 */

function getMeetingLink(string $method, string $slotTime): string
{
    $minute = date('i', strtotime($slotTime));

    $isHalfHour = ($minute === '30');

    switch ($method) {

        case 'Google Meet':

            return $isHalfHour
                ? GOOGLE_MEET_HALF_HOUR_LINK
                : GOOGLE_MEET_HOUR_LINK;

        case 'Zoom':

            return $isHalfHour
                ? ZOOM_HALF_HOUR_LINK
                : ZOOM_HOUR_LINK;

        default:

            return '';

    }
}

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