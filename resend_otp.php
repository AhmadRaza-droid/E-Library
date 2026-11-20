<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';
require_once __DIR__ . '/mailer.php';

require_post();

if (!isset($_SESSION['temp_email'])) { redirect('register.php'); }
$email = $_SESSION['temp_email'];

if (isset($_POST['resend'])) {
    // max 3 resends per 10 minutes, and at most one every 60 seconds
    if (rate_limited('resend:' . strtolower($email), 3, 600)) {
        js_alert_redirect('⛔ Too many requests. Please wait a few minutes.', 'verify_otp.php');
    }
    if (!empty($_SESSION['last_resend']) && time() - $_SESSION['last_resend'] < 60) {
        js_alert_redirect('Please wait 60 seconds before requesting another OTP.', 'verify_otp.php');
    }

    $user = db_row($conn, "SELECT fullname FROM users WHERE email=? AND is_verified=0 LIMIT 1", 's', [$email]);
    if ($user) {
        rate_limit_add('resend:' . strtolower($email), 600);
        $_SESSION['last_resend'] = time();
        $otp = otp_issue($conn, $email);
        if (send_mail($email, $user['fullname'], 'New OTP Code - Book Library', otp_mail_html('Your New OTP', $user['fullname'], $otp))) {
            js_alert_redirect('✅ New OTP sent to your email!', 'verify_otp.php');
        }
        js_alert_redirect('❌ Failed to send OTP. Please try again.', 'verify_otp.php');
    }
}
redirect('verify_otp.php');
