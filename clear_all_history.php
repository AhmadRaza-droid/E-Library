<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_login();

$user_id    = (int)$_SESSION['user_id'];
$user_email = (string)($_SESSION['email'] ?? '');

foreach (['recently_viewed', 'reading_progress', 'bookmarks', 'favorites', 'book_requests'] as $t) {
    db_exec($conn, "DELETE FROM `$t` WHERE user_id=?", 'i', [$user_id]);
}
// Older installations may not have both activity tables yet.
foreach (['reading_history', 'downloads'] as $t) {
    $exists = db_query($conn, "SHOW TABLES LIKE ?", 's', [$t]);
    if ($exists === false) {
        error_log('Could not check optional activity table during history cleanup.');
        continue;
    }
    if ($exists instanceof mysqli_result) {
        $found = $exists->fetch_row();
        $exists->free();
        if ($found) db_exec($conn, "DELETE FROM `$t` WHERE user_id=?", 'i', [$user_id]);
    }
}
if ($user_email !== '') {
    db_exec($conn, "DELETE FROM messages WHERE email=?", 's', [$user_email]);
}
// NOTE: notifications are global (sent by admin to everybody) so they are NOT deleted here any more.

js_alert_redirect("✅ Your activity was cleared:\n• Reading history\n• Reading progress\n• Download and reading activity\n• Bookmarks\n• Favorites\n• Book requests\n• Messages", 'profile.php');
