<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';
require_once __DIR__ . '/mailer.php';

require_post();

if (isset($_SESSION['temp_email'])) {
    $email = $_SESSION['temp_email'];
    $type = 'registration';
} elseif (isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
    $type = 'reset';
} else {
    redirect('forgot_password.php');
}

if (isset($_POST['resend'])) {
    // Limit both registration and password-reset resend requests.
    $key = 'resend:' . strtolower($email);
    if (rate_limited($key, 3, 600)) {
        js_alert_redirect('Too many OTP requests. Please wait a few minutes.', 'verify_otp.php');
    }
    if (!empty($_SESSION['last_resend']) && time() - (int)$_SESSION['last_resend'] < 60) {
        js_alert_redirect('Please wait 60 seconds before requesting another OTP.', 'verify_otp.php');
    }

    rate_limit_add($key, 600);
    $_SESSION['last_resend'] = time();
    $user = db_row($conn, "SELECT fullname FROM users WHERE email=? LIMIT 1", 's', [$email]);

    if ($user) {
        $otp = otp_issue($conn, $email);
        if ($otp === false) {
            js_alert_redirect('Could not create a new OTP. Please try again later.', 'verify_otp.php');
        }
        $subject = $type === 'reset' ? 'Password Reset OTP' : 'New OTP Code - Book Library';
        $title = $type === 'reset' ? 'Password Reset OTP' : 'Your New OTP';
        if (send_mail($email, $user['fullname'], $subject, otp_mail_html($title, $user['fullname'], $otp))) {
            js_alert_redirect('A new OTP has been sent to your email.', 'verify_otp.php');
        }
        if ($type === 'registration') {
            js_alert_redirect('OTP could not be emailed. Please check your mail settings or contact support.', 'verify_otp.php');
        }
    }

    // Keep password-reset responses identical for known and unknown addresses.
    if ($type === 'reset') {
        js_alert_redirect('If this email is registered, a new OTP has been sent.', 'verify_otp.php');
    }
}
redirect($type === 'registration' ? 'verify_otp.php' : 'forgot_password.php');
