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
                if ($reportPath && file_exists($reportPath)) {
                    $mail->addAttachment($reportPath);
                }

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
    string $to,
    string $name,
    string $service,
    string $invoicePath,
    ?string $reportPath = null
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
        <strong>Need additional IT assistance?</strong><br>
        Visit <a href='https://ramiphp.com'>https://ramiphp.com</a>
        to explore our services and learn about our upcoming
        monthly and annual business support plans.
    </p>

    <p>
        Kind regards,<br>
        <strong>IT Consultancy Team</strong>
    </p>
";

    $attachments = [$invoicePath];

        if (
            $reportPath !== null &&
            file_exists($reportPath)
        ) {
            $attachments[] = $reportPath;
        }

        return sendEmail(
            $to,
            $subject,
            $body,
            $attachments
        );
}

function sendContractLeadNotification(
    string $companyName,
    string $contactPerson,
    string $email,
    string $phone,
    ?int $employees,
    string $comments
): bool {

    $subject = 'New Company Support Lead';

    $body = "
        <h2>🏢 New Company Support Lead</h2>

        <p>
            A new company has expressed interest in your IT support services.
        </p>

        <hr>

        <p><strong>Company:</strong> {$companyName}</p>

        <p><strong>Contact Person:</strong> {$contactPerson}</p>

        <p><strong>Email:</strong> {$email}</p>

        <p><strong>Phone:</strong> {$phone}</p>

        <p><strong>Employees:</strong> " . ($employees ?? 'Not specified') . "</p>

        <p><strong>Comments / Requirements:</strong></p>

        <blockquote style='border-left:4px solid #28a745;padding-left:10px;'>
            " . nl2br(htmlspecialchars($comments)) . "
        </blockquote>

        <hr>

        <p>
            Please log in to the Admin Dashboard and follow up with this lead.
        </p>
    ";

    $config = require dirname(__DIR__, 2) . '/config/mail_config.php';

    return sendEmail(
        $config['admin_email'],
        $subject,
        $body
    );
}

function sendPasswordResetEmail(
    string $to,
    string $customerName,
    string $token
): bool {

    $subject = 'Reset Your Password';

    $resetLink =
        'http://localhost/it-consultancy-php/public/index.php?page=customer-reset-password&token='
        . urlencode($token);

    $body = "
        <h2>Password Reset Request</h2>

        <p>Hello {$customerName},</p>

        <p>
            We received a request to reset your password.
        </p>

        <p>
            Click the link below to choose a new password:
        </p>

        <p>
            <a href='{$resetLink}'>
                Reset My Password
            </a>
        </p>

        <p>
            This link will expire in <strong>1 hour</strong>.
        </p>

        <p>
            If you did not request a password reset, you can safely ignore this email.
        </p>

        <br>

        <p>
            Regards,<br>
            IT Consultancy
        </p>
    ";

    return sendEmail(
        $to,
        $subject,
        $body
    );
}