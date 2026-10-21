<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

if (isset($_POST['reply_submit'])) {
    $message_id = (int)($_POST['message_id'] ?? 0);
    $reply      = trim($_POST['reply'] ?? '');
    if ($message_id > 0) {
        db_exec($conn, "UPDATE messages SET reply=? WHERE id=?", 'si', [$reply, $message_id]);
    }
    js_alert_redirect('Reply saved successfully', 'admin_dashboard.php?page=messages');
}
redirect('admin_dashboard.php?page=messages');
