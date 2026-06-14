<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function sendEmail(
    string $to,
    string $subject,
    string $body,
    array $attachments = []
): bool {

    $config = require dirname(__DIR__, 2) . '/config/mail_config.php';

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;

        $mail->Username = $config['username'];
        $mail->Password = $config['password'];

        $mail->Port = $config['port'];

        $mail->setFrom(
            $config['username'],
            $config['from_name']
        );

        $mail->addAddress($to);

        foreach ($attachments as $attachment) {

            if (file_exists($attachment)) {

                $mail->addAttachment($attachment);

            }
        }

        $mail->isHTML(true);

        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();

        return true;

    } catch (Exception $e) {

        return false;
    }
}

function sendConsultationApprovedEmail($email, $name)
{
    $subject = 'Consultation Approved';

    $body = "
        <h2>Hello {$name},</h2>

        <p>Your consultation request has been approved.</p>

        <p>Please log in and schedule your consultation.</p>

        <p>IT Consultancy Team</p>
    ";

    return sendEmail($email, $subject, $body);
}

function sendServiceCompletedEmail(
    string $email,
    string $name,
    string $service,
    string $invoicePath
): bool {

    $subject = 'Your IT Service Has Been Successfully Completed';

    $body = "
    <h2>Hello {$name},</h2>

    <p>
        We are pleased to inform you that your requested IT service
        has been completed successfully.
    </p>

    <p>
        <strong>Service:</strong> {$service}
    </p>

    <p>
        Your official invoice (PDF) is attached to this email
        for your records and accounting purposes.
    </p>

    <p>
        You can also log in to your customer portal at any time
        to view your request history, invoices, and future services.
    </p>

    <hr>

    <p>
        <strong>Need additional IT assistance?</strong><br>
        Visit <a href='https://ramiphp.com'>https://ramiphp.com</a>
        to explore our services and learn about our upcoming
        monthly and annual business support plans.
    </p>

    <p>
        Thank you for choosing IT Consultancy.
        We appreciate your trust and look forward to assisting
        you again in the future.
    </p>

    <br>

    <p>
    📧 support@itconsultancy.com<br>
    📞 +962 XX XXX XXXX<br>
    🌐 https://ramiphp.com
    </p>


    <p>
        Kind regards,<br>
        <strong>IT Consultancy Team</strong>
    </p>
";

    return sendEmail(
        $email,
        $subject,
        $body,
        [$invoicePath]
    );
}