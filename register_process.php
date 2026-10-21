<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';
require_once __DIR__ . '/mailer.php';

require_post();

if (!isset($_POST['register'])) { redirect('register.php'); }

// registration can be switched off in admin settings
if (get_setting($conn, 'allow_registration', '1') == '0') {
    js_alert_redirect('Registration is currently closed.', 'register.php');
}

// ========== RATE LIMITING (10 registrations / hour / IP) ==========
$rl_key = 'register:' . client_ip();
if (rate_limited($rl_key, 10, 3600)) {
    js_alert_redirect('⛔ Too many registration attempts! Try again after 1 hour.', 'register.php');
}

$fullname = trim($_POST['fullname'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm  = (string)($_POST['confirm_password'] ?? '');

if ($fullname === '' || mb_strlen($fullname) > 100) {
    js_alert_redirect('❌ Please enter your full name (max 100 characters).', 'register.php');
}
if (!validate_email($email) || strlen($email) > 150) {
    js_alert_redirect('❌ Invalid email format!', 'register.php');
}
if ($err = password_error($password)) {
    js_alert_redirect('❌ ' . $err, 'register.php');
}
if ($password !== $confirm) {
    js_alert_redirect('Passwords do not match', 'register.php');
}

rate_limit_add($rl_key, 3600);

// ========== EMAIL ALREADY USED? ==========
$existing = db_row($conn, "SELECT id, is_verified FROM users WHERE email=? LIMIT 1", 's', [$email]);
if ($existing) {
    if (empty($existing['is_verified'])) {
        // never finished verification -> let them continue instead of locking the address forever
        $otp = otp_issue($conn, $email);
        $_SESSION['temp_email'] = $email;
        send_mail($email, $fullname, 'Verify Your Email - OTP Code', otp_mail_html('Email Verification', $fullname, $otp));
        js_alert_redirect('This email is registered but not verified. A new OTP was sent.', 'verify_otp.php');
    }
    js_alert_redirect('Email already registered! Please login.', 'login.php');
}

// ========== CREATE USER ==========
$hashed = hash_password_value($password);
$otp    = generate_otp();
$expiry = date('Y-m-d H:i:s', time() + 600);

$ok = db_exec($conn,
    "INSERT INTO users (fullname, email, password, otp, otp_expiry, is_verified) VALUES (?, ?, ?, ?, ?, 0)",
    'sssss', [$fullname, $email, $hashed, $otp, $expiry]);

if ($ok === false) {
    js_alert_redirect('❌ Registration failed. Please try again.', 'register.php');
}

$_SESSION['temp_email'] = $email;

if (send_mail($email, $fullname, 'Verify Your Email - OTP Code', otp_mail_html('Email Verification', $fullname, $otp))) {
    js_alert_redirect('✅ Registration Successful! OTP sent to your email. Please verify.', 'verify_otp.php');
}
js_alert_redirect('⚠️ Registration successful but the OTP email failed. Use "Resend OTP" on the next page.', 'verify_otp.php');
