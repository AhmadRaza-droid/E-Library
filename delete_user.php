<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

$id = (int)($_POST['id'] ?? 0);
if ($id > 0 && $id !== (int)$_SESSION['admin_id']) {          // an admin cannot delete himself
    foreach (['favorites', 'recently_viewed', 'reviews', 'bookmarks', 'reading_progress', 'book_requests'] as $t) {
        db_exec($conn, "DELETE FROM `$t` WHERE user_id=?", 'i', [$id]);
    }
    db_exec($conn, "DELETE FROM users WHERE id=?", 'i', [$id]);
}
redirect('admin_dashboard.php?page=all_users');
