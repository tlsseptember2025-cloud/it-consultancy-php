<?php

function formatDate($date)
{
    if (empty($date)) {
        return '-';
    }

    return date('d-m-Y', strtotime($date));
}

function formatTime($date)
{
    if (empty($date)) {
        return '-';
    }

    return date('h:i A', strtotime($date));
}

function formatDateTime($date)
{
    if (empty($date)) {
        return '-';
    }

    return date('d-m-Y h:i A', strtotime($date));
}