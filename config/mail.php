<?php
/**
 * mail.php
 * Smart Attendance System - Email Utility
 * Handles SMTP config and email sending using PHPMailer (no Composer)
 */

// Include PHPMailer classes
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';
require_once __DIR__ . '/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail {
    // SMTP Configuration
    private const SMTP_HOST = 'smtp.gmail.com';
    private const SMTP_PORT = 587;
    private const SMTP_USERNAME = 'satish@geeksofgurukul.com'; // Your email
    private const SMTP_PASSWORD = 'hiaa oshx vooq dlag';      // App password
    private const SMTP_SECURE = 'tls';

    // Sender info
    private const FROM_EMAIL = 'satish@geeksofgurukul.com';
    private const FROM_NAME  = 'Smart Attendance System';

    // Common subjects
    public const WELCOME_SUBJECT = 'Welcome to Smart Attendance System';
    public const CREDENTIALS_SUBJECT = 'Your Login Credentials - Smart Attendance System';

    // Main method to send email
    public static function sendEmail($to_email, $to_name, $subject, $body) {
        $mail = new PHPMailer(true);

        try {
            // SMTP Settings
            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::SMTP_USERNAME;
            $mail->Password   = self::SMTP_PASSWORD;
            $mail->SMTPSecure = self::SMTP_SECURE;
            $mail->Port       = self::SMTP_PORT;

            // Sender & recipient
            $mail->setFrom(self::FROM_EMAIL, self::FROM_NAME);
            $mail->addAddress($to_email, $to_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mail Error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
?>
