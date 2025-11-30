<?php
// C:\xampp\htdocs\smartnutrition\sendEmail.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

function sendOtpMail(string $toEmail, string $otp): bool
{
    $mail = new PHPMailer(true);

    try {
        // ===== TEMP DEBUG (turn to 0 when it works) =====
        $mail->SMTPDebug   = 0;       // 0 = off, 2 = verbose
        $mail->Debugoutput = 'html';
        // =================================================

        /* ========== GMAIL SMTP CONFIG ========== */
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rajbharathdere@gmail.com';   // <-- change
        $mail->Password   = 'mhjlsrpbwkqdfjva';      // <-- change
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // XAMPP / local dev: be a bit lenient with SSL
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->CharSet = 'UTF-8';

        // From MUST match the Gmail username
        $mail->setFrom('rajbharathdere@gmail.com', 'Smart Nutrition');

        // Recipient = user who requested reset
        $mail->addAddress($toEmail);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Smart Nutrition – Your OTP Code';
        $mail->Body    = "
            <p>Hello,</p>
            <p>Your OTP for resetting your Smart Nutrition password is:</p>
            <h2 style='letter-spacing:4px;'>$otp</h2>
            <p>This code is valid for <strong>10 minutes</strong>.</p>
            <p>If you did not request this, you can safely ignore this email.</p>
        ";
        $mail->AltBody = "Your OTP is: $otp (valid for 10 minutes).";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}
