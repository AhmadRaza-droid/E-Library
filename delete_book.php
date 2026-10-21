<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    db_exec($conn, "DELETE FROM favorites WHERE book_id=?", 'i', [$id]);
    db_exec($conn, "DELETE FROM reviews WHERE book_id=?", 'i', [$id]);
    db_exec($conn, "DELETE FROM recently_viewed WHERE book_id=?", 'i', [$id]);
    db_exec($conn, "DELETE FROM bookmarks WHERE book_id=?", 'i', [$id]);
    db_exec($conn, "DELETE FROM reading_progress WHERE book_id=?", 'i', [$id]);
    db_exec($conn, "DELETE FROM books WHERE id=?", 'i', [$id]);
}
js_alert_redirect('Book Deleted Successfully', 'admin_dashboard.php?page=all_books');
