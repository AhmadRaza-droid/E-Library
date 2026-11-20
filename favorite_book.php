<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_login();

$user_id = (int)$_SESSION['user_id'];
$book_id = (int)($_POST['book_id'] ?? 0);

if ($book_id > 0 && db_row($conn, "SELECT id FROM books WHERE id=?", 'i', [$book_id])) {
    if (!db_row($conn, "SELECT id FROM favorites WHERE user_id=? AND book_id=?", 'ii', [$user_id, $book_id])) {
        db_exec($conn, "INSERT INTO favorites (user_id, book_id) VALUES (?, ?)", 'ii', [$user_id, $book_id]);
    }
}
$back = $_SERVER['HTTP_REFERER'] ?? '';
redirect((strpos($back, '/books.php') !== false) ? 'books.php' : 'profile.php');
