<?php
require_once __DIR__ . '/session_init.php';
include 'session_timeout.php';
include 'config.php';

require_post();
require_login();
csrf_require();

$user_id = (int)$_SESSION['user_id'];
if (!isset($_POST['upload_image'])) redirect('profile.php');

[$ok, $result] = save_upload($_FILES['profile_image'] ?? [], 'upload/profile',
    ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    2 * 1024 * 1024, 'user' . $user_id);

if (!$ok) js_alert_redirect('❌ ' . $result, 'profile.php');

// Save the new path first. Keep the old picture if the database update fails.
$old = db_value($conn, "SELECT profile_image FROM users WHERE id=?", 'i', [$user_id], '');
$updated = db_exec($conn, "UPDATE users SET profile_image=? WHERE id=?", 'si', [$result, $user_id]);
if ($updated === false) {
    @unlink(__DIR__ . '/' . $result);
    error_log('Profile image database update failed for user ' . $user_id);
    js_alert_redirect('Could not update your profile picture. Please try again.', 'profile.php');
}

// Delete only a previous local upload belonging to this application.
if ($old && preg_match('#^upload/profile/[A-Za-z0-9._\\-]+$#', $old)
    && is_file(__DIR__ . '/' . $old)) {
    @unlink(__DIR__ . '/' . $old);
}
js_alert_redirect('✅ Profile picture updated!', 'profile.php');
