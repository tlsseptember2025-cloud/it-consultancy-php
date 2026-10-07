<?php

require_once APP_PATH . '/helpers/email.php';

function sendFirstVerificationEmail(array $consultation): bool
{
    $customerName = htmlspecialchars(
        (string) ($consultation['customer_name'] ?? 'Customer'),
        ENT_QUOTES,
        'UTF-8'
    );

    $subject = 'Action Required: We Could Not Reach You Regarding Your Consultation';

    $body = '
        <h2>Customer Contact Verification</h2>

        <p>Dear <strong>' . $customerName . '</strong>,</p>

        <p>
            We recently attempted to contact you regarding your scheduled
            consultation, but unfortunately we were unable to reach you by
            telephone.
        </p>

        <p>
            Please reply to this email or contact us so we can confirm how
            you would like to proceed with your consultation.
        </p>

        <p>
            Your request will remain pending while we wait for your response.
        </p>

        <p>
            Regards,<br>
            <strong>IT Consultancy Team</strong>
        </p>
    ';

    return sendEmail(
        (string) ($consultation['email'] ?? ''),
        $subject,
        $body
    );
}

function sendSecondVerificationEmail(array $consultation): bool
{
    $customerName = htmlspecialchars(
        (string) ($consultation['customer_name'] ?? 'Customer'),
        ENT_QUOTES,
        'UTF-8'
    );

    $subject = 'Second Reminder: Please Contact Us Regarding Your Consultation';

    $body = '
        <h2>Second Contact Verification Reminder</h2>

        <p>Dear <strong>' . $customerName . '</strong>,</p>

        <p>
            This is a second reminder regarding your consultation request.
            We previously attempted to contact you but have not yet received
            a response.
        </p>

        <p>
            Please contact us or reply to this email as soon as possible so we
            can continue processing your request.
        </p>

        <p>
            If we do not receive a response within the required follow-up
            period, your consultation request may eventually be closed.
        </p>

        <p>
            Regards,<br>
            <strong>IT Consultancy Team</strong>
        </p>
    ';

    return sendEmail(
        (string) ($consultation['email'] ?? ''),
        $subject,
        $body
    );
}
