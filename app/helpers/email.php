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