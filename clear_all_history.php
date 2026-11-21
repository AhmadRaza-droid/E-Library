<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_login();

$user_id    = (int)$_SESSION['user_id'];
$user_email = (string)($_SESSION['email'] ?? '');

if (!$conn->begin_transaction()) {
    js_alert_redirect('Could not clear your activity right now. Please try again.', 'profile.php');
}

foreach (['recently_viewed', 'reading_progress', 'bookmarks', 'favorites', 'book_requests'] as $table) {
    if (db_exec($conn, "DELETE FROM `$table` WHERE user_id=?", 'i', [$user_id]) === false) {
        $conn->rollback();
        js_alert_redirect('Could not clear all activity. No changes were saved; please try again.', 'profile.php');
    }
}

// Older installations may not have both activity tables yet.
foreach (['reading_history', 'downloads'] as $table) {
    $check = db_query($conn,
        "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?",
        's', [$table]);
    if (!$check instanceof mysqli_result) {
        $conn->rollback();
        js_alert_redirect('Could not check your activity records. No changes were saved.', 'profile.php');
    }
    $row = $check->fetch_assoc();
    $check->free();
    if ((int)($row['total'] ?? 0) > 0
        && db_exec($conn, "DELETE FROM `$table` WHERE user_id=?", 'i', [$user_id]) === false) {
        $conn->rollback();
        js_alert_redirect('Could not clear all activity. No changes were saved; please try again.', 'profile.php');
    }
}

if ($user_email !== '' && db_exec($conn, "DELETE FROM messages WHERE email=?", 's', [$user_email]) === false) {
    $conn->rollback();
    js_alert_redirect('Could not clear your messages. No changes were saved.', 'profile.php');
}

if (!$conn->commit()) {
    $conn->rollback();
    js_alert_redirect('Could not finish clearing your activity. Please try again.', 'profile.php');
}

// Notifications are global and are intentionally retained.
js_alert_redirect("Your activity was cleared:\n• Reading history\n• Reading progress\n• Download activity\n• Bookmarks\n• Favorites\n• Book requests\n• Messages", 'profile.php');
