<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

$book_id = (int)($_GET['id'] ?? 0);
if ($book_id <= 0) { redirect('books.php'); }

$book = db_row($conn, "SELECT * FROM books WHERE id=?", 'i', [$book_id]);
if (!$book) { redirect('books.php'); }

$readLink = resolve_read_link($book['read_link']);
if ($readLink === '') { redirect('books.php'); }   // never redirect to javascript:/data: or malformed URLs
// Keep older book links within the site reader.
redirect('read_book.php?id=' . $book_id);
