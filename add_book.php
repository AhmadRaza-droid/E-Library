<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

$title       = trim($_POST['title'] ?? '');
$author      = trim($_POST['author'] ?? '');
$description = trim($_POST['description'] ?? '');
$category    = trim($_POST['category'] ?? '');
$cover       = safe_url($_POST['cover_image_url'] ?? '');
$read_link   = trim($_POST['read_link'] ?? '');
$dl_link     = trim($_POST['download_epub_link'] ?? '');

// numeric = Project Gutenberg id, otherwise it must be a safe http(s) URL
$read_ok = ctype_digit($read_link) || safe_url($read_link) !== '';
$dl_ok   = $dl_link === '' || ctype_digit($dl_link) || safe_url($dl_link) !== '';

if ($title === '' || $author === '' || !$read_ok || !$dl_ok) {
    js_alert_redirect('❌ Title, author and valid http(s) links are required.', 'admin_dashboard.php?page=add_book');
}

$ok = db_exec($conn,
    "INSERT INTO books (title, author, description, category, cover_image_url, read_link, download_epub_link, source)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'Admin')",
    'sssssss', [$title, $author, $description, $category, $cover, $read_link, $dl_link]);

js_alert_redirect($ok === false ? '❌ Error adding book.' : 'Book Added Successfully', 'admin_dashboard.php?page=all_books');
