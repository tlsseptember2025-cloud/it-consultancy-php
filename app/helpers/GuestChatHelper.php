<?php

/**
 * Guest Live Chat availability helper.
 *
 * Availability requires BOTH:
 * 1. Current Dubai time is within configured office hours.
 * 2. At least one Main/Dev Admin has a recent heartbeat.
 *
 * Database timestamps are treated as UTC.
 * Office hours are interpreted using Asia/Dubai.
 */

function getGuestChatAvailability(PDO $pdo): array
{
    $dubaiTimezone = new DateTimeZone('Asia/Dubai');
    $utcTimezone = new DateTimeZone('UTC');

    $nowDubai = new DateTimeImmutable('now', $dubaiTimezone);
    $nowUtc = new DateTimeImmutable('now', $utcTimezone);

    /*
     * ISO-8601 day number:
     *
     * 1 = Monday
     * 2 = Tuesday
     * ...
     * 7 = Sunday
     */
    $dayOfWeek = (int) $nowDubai->format('N');

    /*
     * Get today's configured office hours.
     */
    $hoursStmt = $pdo->prepare("
        SELECT
            is_open,
            open_time,
            close_time
        FROM guest_chat_office_hours
        WHERE day_of_week = ?
        LIMIT 1
    ");

    $hoursStmt->execute([$dayOfWeek]);

    $hours = $hoursStmt->fetch(PDO::FETCH_ASSOC);

    $officeOpen = false;
    $currentTime = $nowDubai->format('H:i:s');

    if (
        $hours
        && (int) $hours['is_open'] === 1
        && !empty($hours['open_time'])
        && !empty($hours['close_time'])
    ) {
        /*
         * Normal same-day office hours.
         */
        if ($hours['open_time'] < $hours['close_time']) {
            $officeOpen =
                $currentTime >= $hours['open_time']
                && $currentTime < $hours['close_time'];

        } elseif ($hours['open_time'] > $hours['close_time']) {
            /*
             * Overnight schedule.
             *
             * During the first half of an overnight period,
             * the current day's schedule is authoritative.
             */
            $officeOpen = $currentTime >= $hours['open_time'];
        }

        /*
         * If today's schedule does not currently make the office open,
         * check whether the previous day's overnight schedule continues
         * past midnight into today. This must be checked regardless of
         * whether today's own schedule is normal or overnight.
         */
        if (!$officeOpen) {
            $previousDay = $dayOfWeek === 1 ? 7 : $dayOfWeek - 1;

            $previousHoursStmt = $pdo->prepare("
                SELECT
                    is_open,
                    open_time,
                    close_time
                FROM guest_chat_office_hours
                WHERE day_of_week = ?
                LIMIT 1
            ");

            $previousHoursStmt->execute([$previousDay]);

            $previousHours = $previousHoursStmt->fetch(PDO::FETCH_ASSOC);

            if (
                $previousHours
                && (int) $previousHours['is_open'] === 1
                && !empty($previousHours['open_time'])
                && !empty($previousHours['close_time'])
                && $previousHours['open_time'] > $previousHours['close_time']
            ) {
                $officeOpen =
                    $currentTime < $previousHours['close_time'];
            }
        }
    }

    /*
     * -------------------------------------------------
     * Check Admin presence
     * -------------------------------------------------
     *
     * An Admin is considered online when:
     *
     * - is_online = 1
     * - last_seen is no older than 2 minutes
     *
     * UTC is used because database timestamps are stored
     * consistently in UTC.
     */
    $adminStmt = $pdo->query("
        SELECT COUNT(*)
        FROM admin_presence
        WHERE is_online = 1
          AND last_seen >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE
    ");

    $onlineAdminCount = (int) $adminStmt->fetchColumn();

    $adminOnline = $onlineAdminCount > 0;

    /*
     * Chat is available only when BOTH conditions
     * are satisfied.
     */
    $available = $officeOpen && $adminOnline;

    return [
        'available' => $available,
        'office_open' => $officeOpen,
        'admin_online' => $adminOnline,
        'online_admin_count' => $onlineAdminCount,
        'day_of_week' => $dayOfWeek,
        'current_time_dubai' => $nowDubai->format('Y-m-d H:i:s'),
        'current_time_utc' => $nowUtc->format('Y-m-d H:i:s'),
        'open_time' => $hours['open_time'] ?? null,
        'close_time' => $hours['close_time'] ?? null,
    ];
}