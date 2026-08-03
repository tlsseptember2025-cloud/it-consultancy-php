<?php

/*
|--------------------------------------------------------------------------
| Request Event Display
|--------------------------------------------------------------------------
|
| Used by the Request Timeline.
| One place controls:
|   - Title
|   - Icon
|   - Badge Color
|
*/

$requestEventDisplay = [

    /*
    |--------------------------------------------------------------------------
    | Customer Contact
    |--------------------------------------------------------------------------
    */

    EVENT_CONTACT_ATTEMPT_APPROVED => [
        'title' => 'Administrator Approved Customer Contact',
        'icon'  => '☎',
        'badge' => 'warning'
    ],

    EVENT_CONTACT_NO_ANSWER => [
        'title' => 'Customer Could Not Be Reached',
        'icon'  => '📵',
        'badge' => 'warning'
    ],

    EVENT_CONTACT_WRONG_NUMBER => [
        'title' => 'Wrong Phone Number Reported',
        'icon'  => '📞',
        'badge' => 'danger'
    ],

    EVENT_CONTACT_EMAIL_SENT => [
        'title' => 'Contact Verification Email Sent',
        'icon'  => '✉',
        'badge' => 'info'
    ],

    EVENT_CONTACT_PHONE_UPDATED => [
        'title' => 'Customer Phone Number Updated',
        'icon'  => '📱',
        'badge' => 'success'
    ],

    /*
    |--------------------------------------------------------------------------
    | Consultation
    |--------------------------------------------------------------------------
    */

    EVENT_CONSULTATION_SCHEDULED => [
        'title' => 'Consultation Scheduled',
        'icon'  => '📅',
        'badge' => 'primary'
    ],

    EVENT_CONSULTATION_RESCHEDULED => [
        'title' => 'Consultation Rescheduled',
        'icon'  => '🔄',
        'badge' => 'primary'
    ],

    EVENT_CONSULTATION_COMPLETED => [
        'title' => 'Consultation Completed',
        'icon'  => '✅',
        'badge' => 'success'
    ],

    /*
    |--------------------------------------------------------------------------
    | Proposal
    |--------------------------------------------------------------------------
    */

    EVENT_PROPOSAL_CREATED => [
        'title' => 'Proposal Created',
        'icon'  => '📄',
        'badge' => 'secondary'
    ],

    EVENT_PROPOSAL_SENT => [
        'title' => 'Proposal Sent',
        'icon'  => '📤',
        'badge' => 'secondary'
    ],

    EVENT_PROPOSAL_ACCEPTED => [
        'title' => 'Proposal Accepted',
        'icon'  => '✔',
        'badge' => 'success'
    ],

    EVENT_PROPOSAL_REJECTED => [
        'title' => 'Proposal Rejected',
        'icon'  => '✖',
        'badge' => 'danger'
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment
    |--------------------------------------------------------------------------
    */

    EVENT_PAYMENT_RECEIPT_UPLOADED => [
        'title' => 'Payment Receipt Uploaded',
        'icon'  => '💳',
        'badge' => 'info'
    ],

    EVENT_PAYMENT_APPROVED => [
        'title' => 'Payment Approved',
        'icon'  => '💰',
        'badge' => 'success'
    ],

    EVENT_PAYMENT_REJECTED => [
        'title' => 'Payment Rejected',
        'icon'  => '❌',
        'badge' => 'danger'
    ],

    /*
    |--------------------------------------------------------------------------
    | Service
    |--------------------------------------------------------------------------
    */

    EVENT_SERVICE_STARTED => [
        'title' => 'Service Started',
        'icon'  => '🛠',
        'badge' => 'primary'
    ],

    EVENT_SERVICE_COMPLETED => [
        'title' => 'Service Completed',
        'icon'  => '🎉',
        'badge' => 'success'
    ],

    /*
    |--------------------------------------------------------------------------
    | Archive
    |--------------------------------------------------------------------------
    */

    EVENT_REQUEST_ARCHIVED => [
        'title' => 'Request Archived',
        'icon'  => '📦',
        'badge' => 'dark'
    ],

];