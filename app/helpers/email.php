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

        <p>We are pleased to inform you that your requested IT service has now been completed successfully.</p>

        <p>
            <strong>Service:</strong> {$service}
        </p>

        <p>
            Your official invoice is attached for your records.
        </p>

        <p>
            Thank you for choosing IT Consultancy. We appreciate your trust and look forward to assisting you with any future IT needs.
        </p>

        <hr>

        <small>
            Need ongoing IT support? Ask us about our upcoming business support plans for regular clients.
        </small>

        <br><br>

        <p>Kind regards,<br>IT Consultancy Team</p>
    ";

    return sendEmail(
        $email,
        $subject,
        $body,
        [$invoicePath]
    );
}