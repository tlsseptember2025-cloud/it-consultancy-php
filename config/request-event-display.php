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
    | Customer Request
    |--------------------------------------------------------------------------
    */

    EVENT_REQUEST_CREATED => [
        'title' => 'Request Created',
        'icon'  => '📝',
        'badge' => 'primary'
    ],

    EVENT_REQUEST_UPDATED => [
        'title' => 'Request Updated',
        'icon'  => '✏️',
        'badge' => 'info'
    ],

    EVENT_REQUEST_ARCHIVED => [
        'title' => 'Request Archived',
        'icon'  => '📦',
        'badge' => 'dark'
    ],


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

    EVENT_CONTACT_RESUMED => [
        'title' => 'Customer Contact Resumed',
        'icon'  => '▶',
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

    EVENT_CONSULTATION_CONFIRMED => [
        'title' => 'Consultation Confirmed',
        'icon'  => '✅',
        'badge' => 'success'
    ],

    EVENT_CONSULTATION_COMPLETED => [
        'title' => 'Consultation Completed',
        'icon'  => '✅',
        'badge' => 'success'
    ],

    EVENT_CONSULTATION_CANCELLED => [
        'title' => 'Consultation Cancelled',
        'icon'  => '❌',
        'badge' => 'danger'
    ],

    EVENT_CUSTOMER_REQUESTED_NEW_CONSULTATION => [
        'title' => 'Customer Requested New Consultation',
        'icon'  => '🔄',
        'badge' => 'warning'
    ],

    EVENT_CUSTOMER_REQUESTED_CLOSURE => [
        'title' => 'Customer Requested Closure',
        'icon'  => '⚠️',
        'badge' => 'warning'
    ],

    EVENT_CUSTOMER_REQUESTED_RESCHEDULE => [
        'title' => 'Customer Requested Reschedule',
        'icon'  => '🔄',
        'badge' => 'warning'
    ],

    EVENT_CUSTOMER_REQUESTED_CANCELLATION => [
        'title' => 'Customer Requested Cancellation',
        'icon'  => '⚠️',
        'badge' => 'warning'
    ],

    EVENT_CUSTOMER_CONTINUED_CONSULTATION => [
        'title' => 'Customer Continued Consultation',
        'icon'  => '▶️',
        'badge' => 'success'
    ],

    EVENT_CONSULTATION_INCOMPLETE => [
        'title' => 'Consultation Incomplete',
        'icon'  => '⚠️',
        'badge' => 'warning'
    ],

    EVENT_NO_ANSWER => [
        'title' => 'No Answer',
        'icon'  => '📞',
        'badge' => 'warning'
    ],

    EVENT_WRONG_NUMBER => [
        'title' => 'Wrong Number',
        'icon'  => '📞',
        'badge' => 'warning'
    ],

    EVENT_CONSULTATION_CLOSED => [
        'title' => 'Consultation Closed',
        'icon'  => '🔒',
        'badge' => 'secondary'
    ],

    EVENT_CONSULTATION_RESCHEDULE_APPROVED => [
        'title' => 'Consultation Reschedule Approved',
        'icon'  => '🔄',
        'badge' => 'info'
    ],

    EVENT_CUSTOMER_RESPONSE_RECORDED => [
        'title' => 'Customer Response Recorded',
        'icon'  => '📝',
        'badge' => 'info'
    ],

    EVENT_CONTACT_ATTEMPT => [
        'title' => 'Customer Contact Attempt',
        'icon'  => '📞',
        'badge' => 'info'
    ],

    EVENT_AGENT_ASSIGNED => [
        'title' => 'Agent Assigned',
        'icon'  => '👤',
        'badge' => 'info'
    ],

    EVENT_AGENT_REASSIGNED => [
        'title' => 'Agent Reassigned',
        'icon'  => '🔄',
        'badge' => 'info'
    ],

    EVENT_PAYMENT_RECEIVED => [
        'title' => 'Payment Received',
        'icon'  => '💰',
        'badge' => 'success'
    ],

    EVENT_CUSTOMER_ANSWERED => [
        'title' => 'Customer Answered',
        'icon'  => '💬',
        'badge' => 'success'
    ],

    EVENT_CUSTOMER_CLOSURE_AGREEMENT_SUBMITTED => [
        'title' => 'Closure Agreement Submitted',
        'icon'  => '📄',
        'badge' => 'info'
    ],

    EVENT_CLOSURE_REQUEST_CONFIRMED => [
        'title' => 'Closure Request Confirmed',
        'icon'  => '🔒',
        'badge' => 'secondary'
    ],

    EVENT_MISSED_CONSULTATION_EXPLAINED => [
        'title' => 'Missed Consultation Explained',
        'icon'  => '📝',
        'badge' => 'warning'
    ],

    AWAITING_CUSTOMER_CONFIRMATION => [

    'title' => 'Awaiting Customer Confirmation',
    'icon'  => '⏳',
    'badge' => 'warning'
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

    EVENT_PROPOSAL_REVISED => [
        'title' => 'Proposal Revised',
        'icon'  => '✏️',
        'badge' => 'info'
    ],


    /*
    |--------------------------------------------------------------------------
    | Payment
    |--------------------------------------------------------------------------
    */

    EVENT_PAYMENT_REQUEST_SENT => [
        'title' => 'Payment Request Sent',
        'icon'  => '💳',
        'badge' => 'info'
    ],

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

    EVENT_SERVICE_SCHEDULED => [
        'title' => 'Service Scheduled',
        'icon'  => '📅',
        'badge' => 'primary'
    ],

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

    EVENT_SERVICE_REJECTED => [
        'title' => 'Service Rejected',
        'icon'  => '❌',
        'badge' => 'danger'
    ],

    EVENT_SERVICE_RESCHEDULE_REQUESTED => [
        'title' => 'Service Reschedule Requested',
        'icon'  => '🔄',
        'badge' => 'warning'
    ],

    EVENT_SERVICE_RESCHEDULE_REJECTED => [
        'title' => 'Service Reschedule Rejected',
        'icon'  => '❌',
        'badge' => 'danger'
    ],

    EVENT_SERVICE_RESCHEDULE_APPROVED => [
        'title' => 'Service Reschedule Approved',
        'icon'  => '✅',
        'badge' => 'success'
    ],

    EVENT_SERVICE_RESCHEDULED => [
        'title' => 'Service Rescheduled',
        'icon'  => '🔄',
        'badge' => 'info'
    ],

    /*
|--------------------------------------------------------------------------
| Service Review / Missed / Overdue
|--------------------------------------------------------------------------
*/

EVENT_SERVICE_MISSED => [
    'title' => 'Service Missed',
    'icon'  => '⚠️',
    'badge' => 'danger'
],

EVENT_SERVICE_OVERDUE => [
    'title' => 'Service Overdue',
    'icon'  => '⏰',
    'badge' => 'danger'
],

EVENT_SERVICE_MISSED_EXPLANATION => [
    'title' => 'Agent Explanation Submitted',
    'icon'  => '📝',
    'badge' => 'info'
],

EVENT_SERVICE_EXPLANATION_REJECTED => [
    'title' => 'Agent Explanation Rejected',
    'icon'  => '❌',
    'badge' => 'danger'
],

EVENT_SERVICE_EXPLANATION_RESUBMITTED => [
    'title' => 'Agent Explanation Resubmitted',
    'icon'  => '🔄',
    'badge' => 'warning'
],

EVENT_SERVICE_REVIEW_ACCEPTED => [
    'title' => 'Service Review Accepted',
    'icon'  => '✅',
    'badge' => 'success'
],

EVENT_SERVICE_RESCHEDULE_REQUIRED => [
    'title' => 'Service Reschedule Required',
    'icon'  => '🔄',
    'badge' => 'warning'
],


/*
|--------------------------------------------------------------------------
| Additional Consultation Events
|--------------------------------------------------------------------------
*/

EVENT_CONSULTATION_APPROVED => [
    'title' => 'Consultation Approved',
    'icon'  => '✅',
    'badge' => 'success'
],


/*
|--------------------------------------------------------------------------
| Verification Email
|--------------------------------------------------------------------------
*/

EVENT_VERIFICATION_EMAIL_2_SENT => [
    'title' => 'Second Verification Email Sent',
    'icon'  => '✉️',
    'badge' => 'info'
],


    /*
    |--------------------------------------------------------------------------
    | Refund
    |--------------------------------------------------------------------------
    */

    EVENT_REFUND_REQUESTED => [
        'title' => 'Refund Requested',
        'icon'  => '💸',
        'badge' => 'warning'
    ],

    EVENT_REFUND_APPROVED => [
        'title' => 'Refund Approved',
        'icon'  => '✅',
        'badge' => 'success'
    ],

    EVENT_REFUND_REJECTED => [
        'title' => 'Refund Rejected',
        'icon'  => '❌',
        'badge' => 'danger'
    ],


    /*
    |--------------------------------------------------------------------------
    | System / Administrator
    |--------------------------------------------------------------------------
    */

    EVENT_SYSTEM_NOTE => [
        'title' => 'System Note',
        'icon'  => '⚙️',
        'badge' => 'secondary'
    ],

    EVENT_ADMIN_NOTE => [
        'title' => 'Administrator Note',
        'icon'  => '📝',
        'badge' => 'secondary'
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
| Consultation Review
|--------------------------------------------------------------------------
*/

EVENT_CONSULTATION_MISSED => [
    'title' => 'Consultation Missed',
    'icon'  => '⚠️',
    'badge' => 'danger'
],

EVENT_CONSULTATION_OVERDUE_EXPLANATION => [
    'title' => 'Consultation Overdue Explanation Submitted',
    'icon'  => '📝',
    'badge' => 'info'
],


/*
|--------------------------------------------------------------------------
| Closure Agreement
|--------------------------------------------------------------------------
*/

EVENT_CLOSURE_AGREEMENT_SENT => [
    'title' => 'Closure Agreement Sent',
    'icon'  => '📄',
    'badge' => 'info'
],

EVENT_CLOSURE_AGREEMENT_RESENT => [
    'title' => 'Closure Agreement Resent',
    'icon'  => '📤',
    'badge' => 'warning'
],


/*
|--------------------------------------------------------------------------
| Retention / Legal Hold
|--------------------------------------------------------------------------
*/

EVENT_RETENTION_EXTENDED => [
    'title' => 'Retention Extension',
    'icon'  => '📅',
    'badge' => 'success'
],

EVENT_RETENTION_EXPORTED => [
    'title' => 'Retention Exported',
    'icon'  => '📤',
    'badge' => 'info'
],

EVENT_LEGAL_HOLD_PLACED => [
    'title' => 'Legal Hold Placed',
    'icon'  => '⚖️',
    'badge' => 'danger'
],

EVENT_LEGAL_HOLD_RELEASED => [
    'title' => 'Legal Hold Released',
    'icon'  => '🔓',
    'badge' => 'success'
],

EVENT_CONSULTATION_RESCHEDULE_REQUESTED => [
    'title' => 'Consultation Reschedule Requested',
    'icon'  => '🔄',
    'badge' => 'warning'
],

];