<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';
require_once __DIR__ . '/mailer.php';

$step = (isset($_GET['step']) && $_GET['step'] === 'reset') ? 'reset' : 'send';

// ========== STEP 1: SEND OTP ==========
if (isset($_POST['send_otp'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!validate_email($email)) {
        js_alert_redirect('❌ Please enter a valid email address.', 'forgot_password.php');
    }
    if (rate_limited('forgot_ip:' . client_ip(), 5, 900) || rate_limited('forgot_mail:' . $email, 3, 900)) {
        js_alert_redirect('⛔ Too many requests. Please try again in 15 minutes.', 'forgot_password.php');
    }
    rate_limit_add('forgot_ip:' . client_ip(), 900);
    rate_limit_add('forgot_mail:' . $email, 900);

    $user = db_row($conn, "SELECT fullname FROM users WHERE email=? LIMIT 1", 's', [$email]);
    if ($user) {
        $otp = otp_issue($conn, $email);
        send_mail($email, $user['fullname'], 'Password Reset OTP', otp_mail_html('Password Reset OTP', '', $otp));
    }
    // same answer whether the account exists or not (no account enumeration)
    unset($_SESSION['otp_verified_email'], $_SESSION['otp_verified_at'], $_SESSION['temp_email']);
    $_SESSION['reset_email'] = $email;
    js_alert_redirect('✅ If this email is registered, an OTP has been sent.', 'verify_otp.php');
}

// ========== STEP 2: RESET PASSWORD (only after OTP was verified for THIS email) ==========
$verified = isset($_SESSION['reset_email'], $_SESSION['otp_verified_email'], $_SESSION['otp_verified_at'])
         && hash_equals($_SESSION['reset_email'], $_SESSION['otp_verified_email'])
         && (time() - (int)$_SESSION['otp_verified_at']) < 900;

if ($step === 'reset' && !$verified) {
    redirect('forgot_password.php');
}

if (isset($_POST['reset_password'])) {
    if (!$verified) {
        js_alert_redirect('Session expired. Please try again.', 'forgot_password.php');
    }
    $new_password     = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');
    $email            = $_SESSION['reset_email'];

    if ($new_password !== $confirm_password) {
        js_alert_redirect('Passwords do not match!', 'forgot_password.php?step=reset');
    }
    if ($err = password_error($new_password)) {
        js_alert_redirect('❌ ' . $err, 'forgot_password.php?step=reset');
    }

    $ok = db_exec($conn, "UPDATE users SET password=?, otp=NULL, otp_expiry=NULL WHERE email=?",
                  'ss', [hash_password_value($new_password), $email]);
    if ($ok === false) {
        js_alert_redirect('❌ Failed to reset password. Try again.', 'forgot_password.php?step=reset');
    }

    unset($_SESSION['reset_email'], $_SESSION['otp_verified_email'], $_SESSION['otp_verified_at']);
    session_regenerate_id(true);
    js_alert_redirect('✅ Password reset successfully! Please login.', 'login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-section {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            padding: 20px;
        }
        .form-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
        }
        .form-box h2 {
            text-align: center;
            color: #0b1f3a;
            margin-bottom: 10px;
        }
        .form-box p {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        .form-box input {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            box-sizing: border-box;
        }
        .form-box input:focus {
            outline: none;
            border-color: #0b1f3a;
        }
        .form-box button {
            width: 100%;
            padding: 12px;
            background: #0b1f3a;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }
        .form-box button:hover {
            background: #1a3a5c;
        }
        .form-box .back-link {
            text-align: center;
            margin-top: 15px;
            color: #666;
        }
        .form-box a {
            color: #0b1f3a;
            text-decoration: none;
            font-weight: bold;
        }
        .form-box a:hover {
            text-decoration: underline;
        }
        .dark-mode .form-box {
            background: #16213e;
            color: white;
        }
        .dark-mode .form-box h2 {
            color: white;
        }
        .dark-mode .form-box p {
            color: #aaa;
        }
        .dark-mode .form-box input {
            background: #1a1a3a;
            color: white;
            border-color: #333;
        }
        .dark-mode .form-box a {
            color: #ffc72c;
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">📖 Library Management System</div>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="books.php">Books</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a href="register.php">Register</a></li>
        <li><button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark</button></li>
    </ul>
</nav>

<section class="form-section">
    <div class="form-box">

        <?php if($step == 'send'): ?>
            <!-- STEP 1: Send OTP -->
            <h2>🔐 Forgot Password</h2>
            <p>Enter your email to receive OTP</p>
            <form method="POST">
<?php echo csrf_field(); ?>
                <input type="email" name="email" placeholder="Enter Email" required>
                <button type="submit" name="send_otp">Send OTP</button>
            </form>
            <div class="back-link">
                <a href="login.php">← Back to Login</a>
            </div>

        <?php elseif($step == 'reset'): ?>
            <!-- STEP 2: Reset Password -->
            <h2>🔑 Reset Password</h2>
            <p>Enter your new password</p>
            <form method="POST">
<?php echo csrf_field(); ?>
                <input type="password" name="new_password" placeholder="New Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="submit" name="reset_password">Reset Password</button>
            </form>
            <div class="back-link">
                <a href="login.php">← Back to Login</a>
            </div>
        <?php endif; ?>

    </div>
</section>

<script>
if(localStorage.getItem("theme") === "dark"){
    document.body.classList.add("dark-mode");
}
function toggleDarkMode(){
    document.body.classList.toggle("dark-mode");
    if(document.body.classList.contains("dark-mode")){
        localStorage.setItem("theme", "dark");
    } else {
        localStorage.setItem("theme", "light");
    }
}
</script>

</body>
</html>