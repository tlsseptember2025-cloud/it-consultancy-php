<?php

/**
 * Application Date/Time Helper
 *
 * All application date/time formatting uses UAE time.
 */

function getAppTimezone(): DateTimeZone
{
    return new DateTimeZone('Asia/Dubai');
}

function formatDate($date)
{
    if (empty($date)) {
        return '-';
    }

    try {
        $dateTime = new DateTimeImmutable($date, getAppTimezone());

        return $dateTime->setTimezone(getAppTimezone())->format('d-m-Y');
    } catch (Exception $e) {
        return '-';
    }
}

function formatTime($date)
{
    if (empty($date)) {
        return '-';
    }

    try {
        $dateTime = new DateTimeImmutable($date, getAppTimezone());

        return $dateTime->setTimezone(getAppTimezone())->format('h:i A');
    } catch (Exception $e) {
        return '-';
    }
}

function formatDateTime($date)
{
    if (empty($date)) {
        return '-';
    }

    try {
        $dateTime = new DateTimeImmutable($date, getAppTimezone());

        return $dateTime->setTimezone(getAppTimezone())->format('d-m-Y h:i A');
    } catch (Exception $e) {
        return '-';
    }
}