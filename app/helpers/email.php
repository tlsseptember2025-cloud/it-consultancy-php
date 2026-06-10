<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function sendEmail(
    string $to,
    string $subject,
    string $body
): bool {

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        $mail->Username = 'ramiwahdan2023@gmail.com';
        $mail->Password = 'avvz tbsb mpld lglj';

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        $mail->setFrom(
            'ramiwahdan2023@gmail.com',
            'IT Consultancy'
        );

        $mail->addAddress($to);

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