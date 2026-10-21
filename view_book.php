<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

$book_id = (int)($_GET['id'] ?? 0);
if ($book_id <= 0) { redirect('books.php'); }

$book = db_row($conn, "SELECT * FROM books WHERE id=?", 'i', [$book_id]);
if (!$book) { redirect('books.php'); }

if (!empty($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    db_exec($conn, "DELETE FROM recently_viewed WHERE user_id=? AND book_id=?", 'ii', [$uid, $book_id]);
    db_exec($conn, "INSERT INTO recently_viewed (user_id, book_id) VALUES (?, ?)", 'ii', [$uid, $book_id]);
}

$readLink = resolve_read_link($book['read_link']);
if ($readLink === '') { redirect('books.php'); }   // never redirect to javascript:/data: or malformed URLs
redirect($readLink);
