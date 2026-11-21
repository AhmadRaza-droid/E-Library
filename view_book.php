<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

$book_id = (int)($_GET['id'] ?? 0);
if ($book_id <= 0) { redirect('books.php'); }

$book = db_row($conn, "SELECT * FROM books WHERE id=?", 'i', [$book_id]);
if (!$book) { redirect('books.php'); }

$readValue = trim((string)($book['read_link'] ?? ''));
if ($readValue === '' && (int)$book['id'] > 0) $readValue = (string)(int)$book['id'];
$readLink = resolve_read_link($readValue);
if ($readLink === '') { redirect('books.php'); }   // never redirect to search, javascript:/data: or malformed URLs
// Keep older book links within the site reader.
redirect('read_book.php?id=' . $book_id);
