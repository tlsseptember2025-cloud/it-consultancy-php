<?php

/*
|--------------------------------------------------------------------------
| Request Event Codes
|--------------------------------------------------------------------------
*/

/*
| Customer Request
*/

define('EVENT_REQUEST_CREATED', 'REQUEST_CREATED');
define('EVENT_REQUEST_UPDATED', 'REQUEST_UPDATED');
define('EVENT_REQUEST_ARCHIVED', 'REQUEST_ARCHIVED');

/*
| Customer Contact
*/

define('EVENT_CONTACT_ATTEMPT_APPROVED', 'CONTACT_ATTEMPT_APPROVED');
define('EVENT_CONTACT_NO_ANSWER', 'CONTACT_NO_ANSWER');
define('EVENT_CONTACT_WRONG_NUMBER', 'CONTACT_WRONG_NUMBER');
define('EVENT_CONTACT_EMAIL_SENT', 'CONTACT_VERIFICATION_EMAIL_SENT');
define('EVENT_CONTACT_PHONE_UPDATED', 'CONTACT_PHONE_UPDATED');
define('EVENT_CONTACT_RESUMED', 'CONTACT_RESUMED');


define(
    'EVENT_CONSULTATION_COMPLETION_CONFIRMED',
    'CONSULTATION_COMPLETION_CONFIRMED'
);

define(
    'EVENT_CONSULTATION_REVIEW_ACCEPTED',
    'CONSULTATION_REVIEW_ACCEPTED'
);

define(
    'EVENT_SERVICE_COMPLETION_CONFIRMED',
    'SERVICE_COMPLETION_CONFIRMED'
);

define(
    'EVENT_CONSULTATION_FINAL_APPROVED',
    'CONSULTATION_FINAL_APPROVED'
);

/*
| Awaiting Customer Confirmation
*/

define('AWAITING_CUSTOMER_CONFIRMATION', 'Awaiting Customer Confirmation');


/*
| Consultation
*/

define('EVENT_CONSULTATION_SCHEDULED', 'CONSULTATION_SCHEDULED');
define('EVENT_CONSULTATION_RESCHEDULED', 'CONSULTATION_RESCHEDULED');
define('EVENT_CONSULTATION_CONFIRMED', 'CONSULTATION_CONFIRMED');
define('EVENT_CONSULTATION_COMPLETED', 'CONSULTATION_COMPLETED');
define('EVENT_CONSULTATION_CANCELLED', 'CONSULTATION_CANCELLED');
define(
    'EVENT_CUSTOMER_REQUESTED_RESCHEDULE',
    'CUSTOMER_REQUESTED_RESCHEDULE'
);
define(
    'EVENT_CUSTOMER_REQUESTED_CANCELLATION',
    'CUSTOMER_REQUESTED_CANCELLATION'
);
define(
    'EVENT_CUSTOMER_CONTINUED_CONSULTATION',
    'CUSTOMER_CONTINUED_CONSULTATION'
);
define(
    'EVENT_CONSULTATION_INCOMPLETE',
    'CONSULTATION_INCOMPLETE'
);
define(
    'EVENT_NO_ANSWER',
    'NO_ANSWER'
);
define(
    'EVENT_WRONG_NUMBER',
    'WRONG_NUMBER'
);
define(
    'EVENT_CONSULTATION_CLOSED',
    'CONSULTATION_CLOSED'
);
define(
    'EVENT_CONSULTATION_RESCHEDULE_APPROVED',
    'CONSULTATION_RESCHEDULE_APPROVED'
);
define(
    'EVENT_CUSTOMER_RESPONSE_RECORDED',
    'CUSTOMER_RESPONSE_RECORDED'
);
define(
    'EVENT_CONTACT_ATTEMPT',
    'CONTACT_ATTEMPT'
);
define(
    'EVENT_AGENT_ASSIGNED',
    'AGENT_ASSIGNED'
);
define(
    'EVENT_AGENT_REASSIGNED',
    'AGENT_REASSIGNED'
);
define(
    'EVENT_PAYMENT_RECEIVED',
    'PAYMENT_RECEIVED'
);
define(
    'EVENT_CUSTOMER_ANSWERED',
    'CUSTOMER_ANSWERED'
);
define(
    'EVENT_CUSTOMER_CLOSURE_AGREEMENT_SUBMITTED',
    'CUSTOMER_CLOSURE_AGREEMENT_SUBMITTED'
);
define(
    'EVENT_CLOSURE_REQUEST_CONFIRMED',
    'CLOSURE_REQUEST_CONFIRMED'
);

define(
    'EVENT_MISSED_CONSULTATION_EXPLAINED',
    'MISSED_CONSULTATION_EXPLAINED'
);

define(
    'EVENT_SERVICE_RESCHEDULE_REQUESTED',
    'SERVICE_RESCHEDULE_REQUESTED'
);

define(
    'EVENT_SERVICE_RESCHEDULE_REJECTED',
    'SERVICE_RESCHEDULE_REJECTED'
);

define(
    'EVENT_MISSED_CONSULTATION_APPROVED',
    'MISSED_CONSULTATION_APPROVED'
);

define(
    'EVENT_CONSULTATION_DECISION_REQUIRED',
    'CONSULTATION_DECISION_REQUIRED'
);

define(
    'EVENT_SERVICE_NOT_COMPLETED_CONFIRMED',
    'SERVICE_NOT_COMPLETED_CONFIRMED'
);

define(
    'EVENT_SERVICE_RESCHEDULE_APPROVED',
    'SERVICE_RESCHEDULE_APPROVED'
);

/*
|--------------------------------------------------------------------------
| Service Review / Missed / Overdue
|--------------------------------------------------------------------------
*/

define('EVENT_SERVICE_MISSED', 'SERVICE_MISSED');

define(
    'EVENT_SERVICE_MISSED_EXPLANATION',
    'SERVICE_MISSED_EXPLANATION'
);

define('EVENT_SERVICE_OVERDUE', 'SERVICE_OVERDUE');

define(
    'EVENT_SERVICE_EXPLANATION_REJECTED',
    'SERVICE_EXPLANATION_REJECTED'
);

define(
    'EVENT_SERVICE_EXPLANATION_RESUBMITTED',
    'SERVICE_EXPLANATION_RESUBMITTED'
);

define(
    'EVENT_SERVICE_REVIEW_ACCEPTED',
    'SERVICE_REVIEW_ACCEPTED'
);

define(
    'EVENT_SERVICE_RESCHEDULE_REQUIRED',
    'SERVICE_RESCHEDULE_REQUIRED'
);


/*
|--------------------------------------------------------------------------
| Consultation Approval
|--------------------------------------------------------------------------
*/

define(
    'EVENT_CONSULTATION_APPROVED',
    'CONSULTATION_APPROVED'
);

/*
|--------------------------------------------------------------------------
| Consultation Review
|--------------------------------------------------------------------------
*/

define(
    'EVENT_CONSULTATION_MISSED',
    'CONSULTATION_MISSED'
);

define(
    'EVENT_CONSULTATION_OVERDUE_EXPLANATION',
    'CONSULTATION_OVERDUE_EXPLANATION'
);

define(
    'EVENT_CONSULTATION_RESCHEDULE_REQUESTED',
    'CONSULTATION_RESCHEDULE_REQUESTED'
);


/*
|--------------------------------------------------------------------------
| Closure Agreement
|--------------------------------------------------------------------------
*/

define(
    'EVENT_CLOSURE_AGREEMENT_SENT',
    'CLOSURE_AGREEMENT_SENT'
);

define(
    'EVENT_CLOSURE_AGREEMENT_RESENT',
    'CLOSURE_AGREEMENT_RESENT'
);


/*
|--------------------------------------------------------------------------
| Retention
|--------------------------------------------------------------------------
*/

define(
    'EVENT_RETENTION_EXTENDED',
    'RETENTION_EXTENDED'
);

define(
    'EVENT_RETENTION_EXPORTED',
    'RETENTION_EXPORTED'
);


/*
|--------------------------------------------------------------------------
| Legal Hold
|--------------------------------------------------------------------------
*/

define(
    'EVENT_LEGAL_HOLD_PLACED',
    'LEGAL_HOLD_PLACED'
);

define(
    'EVENT_LEGAL_HOLD_RELEASED',
    'LEGAL_HOLD_RELEASED'
);


/*
|--------------------------------------------------------------------------
| Verification Emails
|--------------------------------------------------------------------------
*/

define(
    'EVENT_VERIFICATION_EMAIL_2_SENT',
    'VERIFICATION_EMAIL_2_SENT'
);

define(
    'EVENT_SERVICE_RESCHEDULED',
    'SERVICE_RESCHEDULED'
);

define('EVENT_CUSTOMER_REQUESTED_NEW_CONSULTATION', 'CUSTOMER_REQUESTED_NEW_CONSULTATION');
define('EVENT_CUSTOMER_REQUESTED_CLOSURE', 'CUSTOMER_REQUESTED_CLOSURE');

/*
| Proposal
*/

define('EVENT_PROPOSAL_CREATED', 'PROPOSAL_CREATED');
define('EVENT_PROPOSAL_SENT', 'PROPOSAL_SENT');
define('EVENT_PROPOSAL_ACCEPTED', 'PROPOSAL_ACCEPTED');
define('EVENT_PROPOSAL_REJECTED', 'PROPOSAL_REJECTED');
define('EVENT_PROPOSAL_REVISED', 'PROPOSAL_REVISED');

/*
| Payment
*/

define('EVENT_PAYMENT_REQUEST_SENT', 'PAYMENT_REQUEST_SENT');
define('EVENT_PAYMENT_RECEIPT_UPLOADED', 'PAYMENT_RECEIPT_UPLOADED');
define('EVENT_PAYMENT_APPROVED', 'PAYMENT_APPROVED');
define('EVENT_PAYMENT_REJECTED', 'PAYMENT_REJECTED');

/*
| Service
*/

define('EVENT_SERVICE_SCHEDULED', 'SERVICE_SCHEDULED');
define('EVENT_SERVICE_STARTED', 'SERVICE_STARTED');
define('EVENT_SERVICE_COMPLETED', 'SERVICE_COMPLETED');
define('EVENT_SERVICE_REJECTED', 'SERVICE_REJECTED');

/*
| Refund
*/

define('EVENT_REFUND_REQUESTED', 'REFUND_REQUESTED');
define('EVENT_REFUND_APPROVED', 'REFUND_APPROVED');
define('EVENT_REFUND_REJECTED', 'REFUND_REJECTED');

/*
| System
*/

define('EVENT_SYSTEM_NOTE', 'SYSTEM_NOTE');
define('EVENT_ADMIN_NOTE', 'ADMIN_NOTE');