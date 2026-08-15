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

    EVENT_CUSTOMER_REQUESTED_NEW_CONSULTATION => [
    'title' => 'Customer Requested New Consultation',
    'icon' => '🔄',
    'class' => 'bg-warning text-dark',
],

EVENT_CUSTOMER_REQUESTED_CLOSURE => [
    'title' => 'Customer Requested Closure',
    'icon' => '⚠️',
    'class' => 'bg-warning text-dark',
],

EVENT_CUSTOMER_REQUESTED_RESCHEDULE => [
    'title' => 'Customer Requested Reschedule',
    'icon' => '🔄',
    'class' => 'bg-warning text-dark',
],

EVENT_CUSTOMER_REQUESTED_CANCELLATION => [
    'title' => 'Customer Requested Cancellation',
    'icon' => '⚠️',
    'class' => 'bg-warning text-dark',
],

EVENT_CUSTOMER_CONTINUED_CONSULTATION => [
    'title' => 'Customer Continued Consultation',
    'icon' => '▶️',
    'class' => 'bg-success text-white',
],

EVENT_CONSULTATION_INCOMPLETE => [
    'title' => 'Consultation Incomplete',
    'icon' => '⚠️',
    'class' => 'bg-warning text-dark',
],

EVENT_NO_ANSWER => [
    'title' => 'No Answer',
    'icon' => '📞',
    'class' => 'bg-warning text-dark',
],

EVENT_WRONG_NUMBER => [
    'title' => 'Wrong Number',
    'icon' => '📞',
    'class' => 'bg-warning text-dark',
],

EVENT_CONSULTATION_CLOSED => [
    'title' => 'Consultation Closed',
    'icon' => '🔒',
    'class' => 'bg-secondary text-white',
],

EVENT_CONSULTATION_RESCHEDULE_APPROVED => [
    'title' => 'Consultation Reschedule Approved',
    'icon' => '🔄',
    'class' => 'bg-info text-white',
],

EVENT_CUSTOMER_RESPONSE_RECORDED => [
    'title' => 'Customer Response Recorded',
    'icon' => '📝',
    'class' => 'bg-info text-white',
],

EVENT_CONSULTATION_CONFIRMED => [
    'title' => 'Consultation Confirmed',
    'icon' => '✅',
    'class' => 'bg-success text-white',
],

EVENT_CONTACT_ATTEMPT => [
    'title' => 'Customer Contact Attempt',
    'icon' => '📞',
    'class' => 'bg-info text-white',
],

EVENT_REQUEST_CREATED => [
    'title' => 'Request Created',
    'icon' => '📝',
    'class' => 'bg-primary text-white',
],

EVENT_AGENT_ASSIGNED => [
    'title' => 'Agent Assigned',
    'icon' => '👤',
    'class' => 'bg-info text-white',
],

EVENT_AGENT_REASSIGNED => [
    'title' => 'Agent Reassigned',
    'icon' => '🔄',
    'class' => 'bg-info text-white',
],

EVENT_PAYMENT_RECEIVED => [
    'title' => 'Payment Received',
    'icon' => '💰',
    'class' => 'bg-success text-white',
],

EVENT_CUSTOMER_ANSWERED => [
    'title' => 'Customer Answered',
    'icon' => '💬',
    'class' => 'bg-success text-white',
],

EVENT_CUSTOMER_CLOSURE_AGREEMENT_SUBMITTED => [
    'title' => 'Closure Agreement Submitted',
    'icon' => '📄',
    'class' => 'bg-info text-white',
],

EVENT_CLOSURE_REQUEST_CONFIRMED => [
    'title' => 'Closure Request Confirmed',
    'icon' => '🔒',
    'class' => 'bg-secondary text-white',
],

EVENT_MISSED_CONSULTATION_EXPLAINED => [
    'title' => 'Missed Consultation Explained',
    'icon' => '📝',
    'class' => 'bg-warning text-dark',
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
    | Retention
    |--------------------------------------------------------------------------
    */


    'RETENTION_EXTENDED' => [
        'title' => 'Retention Extension',
        'icon'  => '📅',
        'badge' => 'success'
    ],


    'LEGAL_HOLD_PLACED' => [
        'title' => 'Legal Hold Placed',
        'icon'  => '⚖️',
        'badge' => 'danger'
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