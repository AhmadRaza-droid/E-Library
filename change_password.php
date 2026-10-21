<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_login();

if (isset($_POST['change'])) {
    $user_id = (int)$_SESSION['user_id'];
    $old     = (string)($_POST['old_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');

    if (rate_limited('chgpw:' . $user_id, 5, 900)) {
        js_alert_redirect('⛔ Too many attempts. Try again later.', 'profile.php');
    }

    $user = db_row($conn, "SELECT password FROM users WHERE id=?", 'i', [$user_id]);

    if (!$user || !verify_password_value($old, $user['password'])) {
        rate_limit_add('chgpw:' . $user_id, 900);
        js_alert_redirect('Old Password Incorrect', 'change_password.php');
    }
    if ($err = password_error($new)) {
        js_alert_redirect('❌ ' . $err, 'change_password.php');
    }

    db_exec($conn, "UPDATE users SET password=? WHERE id=?", 'si', [hash_password_value($new), $user_id]);
    rate_limit_reset('chgpw:' . $user_id);
    session_regenerate_id(true);
    js_alert_redirect('Password Changed Successfully', 'profile.php');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<section class="form-section">

<div class="form-box">

<h2>Change Password</h2>

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

</div>

</section>

</body>
</html>