<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

// Where did the visitor come from? registration (temp_email) or forgot-password (reset_email)
if (isset($_SESSION['temp_email'])) {
    $email = $_SESSION['temp_email'];
    $type  = 'registration';
} elseif (isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
    $type  = 'reset';
} else {
    js_alert_redirect('Session expired. Please try again.', 'login.php');
}

if (isset($_POST['verify'])) {
    $result = otp_check($conn, $email, $_POST['otp'] ?? '');

    if ($result === 'ok') {
        if ($type === 'registration') {
            db_exec($conn, "UPDATE users SET is_verified=1, otp=NULL, otp_expiry=NULL WHERE email=?", 's', [$email]);
            unset($_SESSION['temp_email']);
            js_alert_redirect('✅ Email verified successfully! Please login.', 'login.php');
        }
        // password reset: remember WHICH email was verified; reset_email must stay in the session
        session_regenerate_id(true);
        $_SESSION['otp_verified_email'] = $email;
        $_SESSION['otp_verified_at']    = time();
        otp_clear($conn, $email);
        js_alert_redirect('✅ OTP Verified! Please set your new password.', 'forgot_password.php?step=reset');
    }

    $msgs = [
        'invalid' => '❌ Invalid OTP! Please try again.',
        'expired' => '⌛ This OTP has expired. Please request a new one.',
        'locked'  => '⛔ Too many wrong attempts. Please request a new OTP.',
    ];
    js_alert_redirect($msgs[$result] ?? $msgs['invalid'], $result === 'invalid' ? 'verify_otp.php' : ($type === 'registration' ? 'register.php' : 'forgot_password.php'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Verify OTP - Book's Library</title>
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
            text-align: center;
        }
        .form-box h2 {
            color: #0b1f3a;
            margin-bottom: 10px;
        }
        .form-box p {
            color: #666;
            margin-bottom: 20px;
        }
        .form-box input {
            width: 80%;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            font-size: 24px;
            letter-spacing: 8px;
        }
        .form-box input:focus {
            outline: none;
            border-color: #0b1f3a;
        }
        .form-box button {
            width: 80%;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }
        .form-box button:hover {
            background: #218838;
        }
        .form-box a {
            color: #0b1f3a;
            text-decoration: none;
        }
        .form-box a:hover {
            text-decoration: underline;
        }
        .email-display {
            background: #f4f6f9;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .back-link {
            margin-top: 20px;
        }
        .dark-mode .form-box {
            background: #16213e;
            color: white;
        }
        .dark-mode .form-box h2 {
            color: white;
        }
        .dark-mode .form-box input {
            background: #1a1a3a;
            color: white;
            border-color: #333;
        }
        .dark-mode .form-box p {
            color: #aaa;
        }
        .dark-mode .form-box a {
            color: #ffc72c;
        }
        .dark-mode .email-display {
            background: #1a1a3a;
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">📖 Book's Library</div>
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
        <h2>🔐 Verify OTP</h2>
        <p>Enter the 6-digit OTP sent to</p>
        <div class="email-display">
            <strong><?php echo htmlspecialchars($email); ?></strong>
        </div>
        <form method="POST">
<?php echo csrf_field(); ?>
            <input type="text" name="otp" placeholder="Enter OTP" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required>
            <br>
            <button type="submit" name="verify">✅ Verify OTP</button>
        </form>
        <?php if($type == 'registration'): ?>
        <form method="POST" action="resend_otp.php" style="margin-top:10px;">
<?php echo csrf_field(); ?>
            <button type="submit" name="resend" style="width:80%;background:#0b1f3a;">🔁 Resend OTP</button>
        </form>
        <?php endif; ?>
        <div class="back-link">
            <a href="<?php echo ($type == 'registration') ? 'register.php' : 'forgot_password.php'; ?>">
                ← Back
            </a>
        </div>
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
    }
    else{
        localStorage.setItem("theme", "light");
    }
}
</script>

</body>
</html>