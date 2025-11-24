<?php
// C:\xampp\htdocs\smartnutrition\sendEmail.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust these paths if your PHPMailer folder is in a different place
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

/**
 * Send OTP mail using PHPMailer.
 *
 * @param string $toEmail Recipient email
 * @param string $otp     6-digit OTP as string
 * @return bool           true on success, false on failure
 */
function sendOtpMail(string $toEmail, string $otp): bool
{
    $mail = new PHPMailer(true);

    try {
        /* ========== CHOOSE ONE SMTP CONFIG ========== */
        /* --- A) GMAIL (recommended) --- 
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'saishureddy@gmail.com';      // TODO
        $mail->Password   = 'YOUR_GMAIL_APP_PASSWORD';           // TODO (16-char app password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587; 

        /* --- OR B) OUTLOOK / OFFICE 365 --- */
        $mail->isSMTP();
        $mail->Host       = 'smtp.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aishwaryareddy.sarsan@gmail.com';
        $mail->Password   = '9491469541Vv$';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        

        $mail->CharSet = 'UTF-8';

        // From & To
        $mail->setFrom('YOUR_GMAIL_ADDRESS@gmail.com', 'Smart Nutrition'); // same as Username
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Smart Nutrition – Your OTP code';
        $mail->Body    = '
            <p>Hello,</p>
            <p>Your One-Time Password (OTP) for resetting your Smart Nutrition password is:</p>
            <p style="font-size:20px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($otp) . '</p>
            <p>This code is valid for <strong>10 minutes</strong>.</p>
            <p>If you did not request this, you can safely ignore this email.</p>
        ';
        $mail->AltBody = "Your Smart Nutrition OTP is: $otp (valid for 10 minutes).";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log detailed error for debugging; don’t show to user
        error_log('PHPMailer OTP error: ' . $mail->ErrorInfo);
        return false;
    }
}