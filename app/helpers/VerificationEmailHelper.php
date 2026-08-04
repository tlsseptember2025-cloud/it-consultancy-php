<?php

require_once APP_PATH . '/helpers/email.php';

function sendFirstVerificationEmail(array $consultation): bool
{
    $subject = 'Action Required: We Could Not Reach You Regarding Your Consultation';

    $body = '

    <!-- Paste the HTML email you already created here -->

    ';

    return sendEmail(
        $consultation['email'],
        $subject,
        $body
    );
}

function sendSecondVerificationEmail(array $consultation): bool
{
    $subject = 'Second Reminder: Please Contact Us Regarding Your Consultation';

    $body = '

    <h2>Second Contact Verification Reminder</h2>

    <p>Dear <strong>' . htmlspecialchars($consultation['customer_name']) . '</strong>,</p>

    <p>
    This is a second reminder regarding your consultation request.
    We previously attempted to contact you but have not yet received a response.
    </p>

    <p>
    Please contact us or reply to this email as soon as possible so we can continue processing your request.
    </p>

    <p>
    If we do not receive a response within the required follow-up period,
    your consultation request may eventually be closed.
    </p>

    ';

    return sendEmail(
        $consultation['email'],
        $subject,
        $body
    );
}