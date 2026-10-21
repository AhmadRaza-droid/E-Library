<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');   // admin full name OR e-mail
    $password = (string)($_POST['password'] ?? '');

    $rl_key = 'adminlogin:' . client_ip();
    if (rate_limited($rl_key, 5, 900)) {
        js_alert_redirect('⛔ Too many failed attempts. Try again in 15 minutes.', 'admin_login.php');
    }

    $candidates = db_all($conn, "SELECT id, fullname, email, password FROM users WHERE (fullname=? OR email=?) AND is_admin=1", 'ss', [$username, strtolower($username)]);

    foreach ($candidates as $admin) {
        if (verify_password_value($password, $admin['password'])) {
            if (password_upgrade_needed($admin['password'])) {
                db_exec($conn, "UPDATE users SET password=? WHERE id=?", 'si', [hash_password_value($password), (int)$admin['id']]);
            }
            rate_limit_reset($rl_key);
            session_regenerate_id(true);
            $_SESSION['admin']       = $admin['fullname'];
            $_SESSION['admin_id']    = (int)$admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['CREATED']       = time();
            $_SESSION['LAST_ACTIVITY'] = time();
            js_alert_redirect('Admin Login Successful', 'admin_dashboard.php');
        }
    }

    rate_limit_add($rl_key, 900);
    js_alert_redirect('Invalid username or password', 'admin_login.php');   // same message for both cases
}
?>
<!DOCTYPE html>
<html>

<head>

<title>Admin Login</title>

<link rel="stylesheet" href="style.css">
<style>
    /* Additional styles to fix full screen */
    .form-section {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background: linear-gradient(135deg, #0b1f3a 0%, #1a3a5c 100%);
        padding: 20px;
    }
    .form-box {
        background: white;
        padding: 40px 35px;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        width: 100%;
        max-width: 420px;
    }
    .form-box h2 {
        color: #0b1f3a;
        text-align: center;
        margin-bottom: 25px;
        font-size: 28px;
    }
    .form-box input {
        width: 100%;
        padding: 14px 18px;
        margin-bottom: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 16px;
        box-sizing: border-box;
    }
    .form-box input:focus {
        outline: none;
        border-color: #0b1f3a;
    }
    .form-box button {
        width: 100%;
        padding: 14px;
        background: #0b1f3a;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: bold;
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
    .form-box p a {
        color: #0b1f3a;
        text-decoration: none;
        font-weight: 500;
    }
    .form-box p a:hover {
        text-decoration: underline;
    }
    body.dark-mode .form-box {
        background: #16213e;
    }
    body.dark-mode .form-box h2 {
        color: white;
    }
    body.dark-mode .form-box input {
        background: #1a1a3a;
        color: white;
        border-color: #333;
    }
    body.dark-mode .form-box p {
        color: #aaa;
    }
    body.dark-mode .form-box p a {
        color: #ffc72c;
    }
</style>

</head>

<body>

<section class="form-section">

<div class="form-box">

<h2>🔐 Admin Login</h2>

<form method="POST">
<?php echo csrf_field(); ?>

<input type="text"
       name="username"
       placeholder="Enter Username"
       required>

<input type="password"
       name="password"
       placeholder="Enter Password"
       required>

<button type="submit" name="login">

Login

</button>

</form>

<p>

<a href="admin_forgot_password.php">

Forgot Password?

</a>

</p>

<p>

<a href="admin_change_password.php">

Change Admin Password

</a>

</p>

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