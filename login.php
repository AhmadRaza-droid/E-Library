<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';
require_once __DIR__ . '/mailer.php';

// Old multi-step "forgot password" screens now live in forgot_password.php (single secure flow)
if (isset($_GET['step']) && $_GET['step'] !== 'login') {
    redirect('forgot_password.php');
}

// ========== LOGIN ==========
if (isset($_POST['login'])) {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $rl_key = 'login:' . client_ip() . ':' . $email;
    if (rate_limit_add($rl_key, 900) > 5) {
        js_alert_redirect('⛔ Too many failed attempts. Please try again in 15 minutes.', 'login.php');
    }

    $user = db_row($conn, "SELECT * FROM users WHERE email=? LIMIT 1", 's', [$email]);

    if ($user && verify_password_value($password, $user['password'])) {

        if (empty($user['is_verified'])) {
            // send a fresh OTP and continue on the verify page
            $otp = otp_issue($conn, $user['email']);
            if ($otp === false) {
                js_alert_redirect('Could not prepare email verification. Please try again later.', 'login.php');
            }
            $_SESSION['temp_email'] = $user['email'];
            send_mail($user['email'], $user['fullname'], 'Verify Your Email - OTP Code',
                      otp_mail_html('Email Verification', $user['fullname'], $otp));
            js_alert_redirect('⚠️ Please verify your email first! A new OTP was sent to your inbox.', 'verify_otp.php');
        }

        // transparently upgrade legacy plain-text / old hashes
        if (password_upgrade_needed($user['password'])) {
            $newHash = hash_password_value($password);
            $updated = db_exec($conn, "UPDATE users SET password=? WHERE id=? AND BINARY password=BINARY ?", 'sis', [$newHash, (int)$user['id'], $user['password']]);
            if ($updated !== 1) {
                js_alert_redirect('Login could not be completed. Please try again.', 'login.php');
            }
            $user['password'] = $newHash;
        }

        rate_limit_reset($rl_key);
        session_regenerate_id(true);            // prevent session fixation
        if (!empty($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] !== (int)$user['id']) {
            unset($_SESSION['admin'], $_SESSION['admin_id'], $_SESSION['admin_email']);
        }
        unset($_SESSION['temp_email'], $_SESSION['reset_email'], $_SESSION['otp_verified_email']);
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['auth_password_hash'] = hash('sha256', $user['password']);
        $_SESSION['CREATED']  = time();
        $_SESSION['LAST_ACTIVITY'] = time();

        js_alert_redirect('✅ Login Successful', 'index.php');
    }

    js_alert_redirect('❌ Invalid Email or Password', 'login.php');
}

$notice = '';
if (isset($_GET['timeout']))  $notice = 'Your session expired. Please log in again.';
if (isset($_GET['security'])) $notice = 'Security check failed. Please log in again.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo e(site_name($conn)); ?></title>
    <link rel="stylesheet" href="style.css?v=2404">
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
            margin-bottom: 25px;
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
        .form-box p {
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
    </style>
</head>

<body class="auth-page">
<?php echo notification_markup(); ?>

<?php render_site_nav($conn, basename(__FILE__)); ?>

<section class="form-section">
    <div class="form-box">

        <h2>🔐 Login</h2>
            <?php if($notice): ?><p style="color:#c0392b;"><?php echo e($notice); ?></p><?php endif; ?>
            <form method="POST">
<?php echo csrf_field(); ?>
                <input type="email" name="email" placeholder="Enter Email" required>
                <input type="password" name="password" placeholder="Enter Password" required>
                <button type="submit" name="login">Login</button>
            </form>
            <p><a href="forgot_password.php">Forgot Password?</a></p>
            <p>Don't have an account? <a href="register.php">Register here</a></p>

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
