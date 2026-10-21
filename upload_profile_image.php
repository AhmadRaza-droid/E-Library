<?php
require_once __DIR__ . '/session_init.php';
include 'session_timeout.php';
include 'config.php';

require_post();
require_login();

$user_id = (int)$_SESSION['user_id'];

if (isset($_POST['upload_image'])) {
    [$ok, $result] = save_upload($_FILES['profile_image'] ?? [], 'upload/profile',
        ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 2 * 1024 * 1024, 'user' . $user_id);

    if (!$ok) {
        js_alert_redirect('❌ ' . $result, 'profile.php');
    }

    // remove the previous picture (only if it is one of ours)
    $old = db_value($conn, "SELECT profile_image FROM users WHERE id=?", 'i', [$user_id], '');
    if ($old && preg_match('#^upload/profile/[A-Za-z0-9._\-]+$#', $old) && is_file(__DIR__ . '/' . $old)) {
        @unlink(__DIR__ . '/' . $old);
    }

    db_exec($conn, "UPDATE users SET profile_image=? WHERE id=?", 'si', [$result, $user_id]);
    js_alert_redirect('✅ Profile picture updated!', 'profile.php');
}
redirect('profile.php');
