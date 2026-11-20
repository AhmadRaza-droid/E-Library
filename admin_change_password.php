<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_admin($conn);

if (isset($_POST['change'])) {
    $admin_id = (int)$_SESSION['admin_id'];
    $old      = (string)($_POST['old_password'] ?? '');
    $new      = (string)($_POST['new_password'] ?? '');

    if (rate_limited('adminchg:' . $admin_id, 5, 900)) {
        js_alert_redirect('⛔ Too many attempts. Try again later.', 'admin_dashboard.php');
    }

    $row = db_row($conn, "SELECT password FROM users WHERE id=? AND is_admin=1", 'i', [$admin_id]);
    if (!$row || !verify_password_value($old, $row['password'])) {
        rate_limit_add('adminchg:' . $admin_id, 900);
        js_alert_redirect('Old Password Incorrect', 'admin_change_password.php');
    }
    if ($err = password_error($new)) {
        js_alert_redirect('❌ ' . $err, 'admin_change_password.php');
    }
    if (db_exec($conn, "UPDATE users SET password=? WHERE id=?", 'si', [hash_password_value($new), $admin_id]) !== 1) {
        js_alert_redirect('Could not change password. Please try again.', 'admin_change_password.php');
    }
    $_SESSION = [];
    session_regenerate_id(true);
    js_alert_redirect('Password changed. Please log in again.', 'admin_login.php');
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Change Password</title>
    <link rel="stylesheet" href="style.css?v=2402">
</head>
<body class="auth-page">
<?php echo notification_markup(); ?>

<section class="form-section">
<div class="form-box">

<h2>Change Admin Password</h2>

<form method="POST">
<?php echo csrf_field(); ?>

<input type="password"
       name="old_password"
       placeholder="Old Password"
       required>

<input type="password"
       name="new_password"
       placeholder="New Password"
       required>

<button type="submit" name="change">
Change Password
</button>

</form>

<p>
    <a href="admin_dashboard.php">Back to Dashboard</a>
</p>

</div>
</section>

</body>
</html>
