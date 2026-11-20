<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
if (empty($_SESSION['user_id'])) { js_alert_redirect('Please login first!', 'login.php'); }

$user_id = (int)$_SESSION['user_id'];
$book_id = (int)($_POST['book_id'] ?? 0);
$note    = mb_substr(trim($_POST['note'] ?? ''), 0, 1000);
$page    = max(0, (int)($_POST['page'] ?? 0));

if ($book_id <= 0 || !db_row($conn, "SELECT id FROM books WHERE id=?", 'i', [$book_id])) {
    js_alert_redirect('Invalid book!', 'books.php');
}
db_exec($conn, "INSERT INTO bookmarks (user_id, book_id, page, note) VALUES (?, ?, ?, ?)", 'iiis', [$user_id, $book_id, $page, $note]);
js_alert_redirect('✅ Bookmark saved successfully!', 'read_book.php?id=' . $book_id);
