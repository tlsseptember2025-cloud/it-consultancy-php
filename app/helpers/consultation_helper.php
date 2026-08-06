<?php

function getConsultationStatusBadge(string $slotDate, string $slotTime): string
{
    $consultation = strtotime($slotDate . ' ' . $slotTime);
    $now = time();

    if ($consultation < $now) {
        return '<span class="badge bg-danger">Passed</span>';
    }

    if (date('Y-m-d', $consultation) === date('Y-m-d')) {
        return '<span class="badge bg-primary">Today</span>';
    }

    return '<span class="badge bg-success">Upcoming</span>';
}