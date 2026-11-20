<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

if (isset($_POST['send_notification'])) {
    $title   = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($title !== '' && $message !== '') {
        db_exec($conn, "INSERT INTO notifications (title, message, created_at) VALUES (?, ?, NOW())", 'ss', [$title, $message]);
    }
}
redirect('admin_dashboard.php?page=notifications');
