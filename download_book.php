<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

if(isset($_GET['id'])){

    $book_id = (int)$_GET['id'];
    $book = $book_id > 0 ? db_row($conn, "SELECT * FROM books WHERE id=?", 'i', [$book_id]) : null;

    if($book){

        // Project Gutenberg SQL imports contain the Gutenberg ID in books.id, not download_epub_link.
        // Use the ID as a fallback for imported catalog entries.
        $downloadValue = trim((string)($book['download_epub_link'] ?? ''));
        if ($downloadValue === '' && (int)$book['id'] > 0) {
            $downloadValue = (string)(int)$book['id'];
        }
        $downloadLink = resolve_download_link($downloadValue);
        if($downloadLink === ''){
            js_alert_redirect('Sorry, no download is available for this book.', 'books.php');
        }

        // Local uploads are served as attachments so browsers download the PDF
        // instead of merely opening it in an embedded PDF viewer.
        $isLocalPdf = (bool)preg_match('#^uploads/pdfs/[A-Za-z0-9._-]+$#', $downloadLink);
        $downloadHref = $isLocalPdf
            ? 'download_book.php?id=' . $book_id . '&file=1'
            : $downloadLink;

        // count each book once per browser session (page refreshes no longer inflate the counter)
        if(empty($_SESSION['dl_counted'][$book_id])){
            $_SESSION['dl_counted'][$book_id] = true;
            db_exec($conn, "UPDATE books SET download_count = download_count + 1 WHERE id=?", 'i', [$book_id]);

            if(isset($_SESSION['user_id'])){
                db_exec($conn, "INSERT INTO downloads (user_id, book_id, book_title) VALUES (?, ?, ?)",
                        'iis', [(int)$_SESSION['user_id'], $book_id, $book['title']]);
            }
        }

        if ($isLocalPdf && (string)($_GET['file'] ?? '') === '1') {
            $pdfRoot = realpath(__DIR__ . '/uploads/pdfs');
            $pdfPath = realpath(__DIR__ . '/' . $downloadLink);
            if ($pdfRoot === false || $pdfPath === false
                || !str_starts_with($pdfPath, $pdfRoot . DIRECTORY_SEPARATOR)
                || !is_file($pdfPath) || !is_readable($pdfPath)) {
                http_response_code(404);
                exit('The PDF file could not be found.');
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="book-' . $book_id . '.pdf"');
            header('Content-Length: ' . (string)filesize($pdfPath));
            header('X-Content-Type-Options: nosniff');
            readfile($pdfPath);
            exit();
        }

        $book_title  = $book['title'];
        $book_author = $book['author'];
        $downloadHref = e($downloadHref);   // escaped for the popup link

        // ========== SHOW POPUP BEFORE DOWNLOAD ==========
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Download - " . htmlspecialchars($book_title) . "</title>
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .popup-box {
            background: white;
            padding: 40px 35px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            max-width: 500px;
            width: 100%;
            text-align: center;
            animation: fadeUp 0.5s ease;
        }
        .popup-box .icon {
            font-size: 64px;
            margin-bottom: 10px;
        }
        .popup-box h2 {
            color: #0b1f3a;
            margin-bottom: 8px;
            font-size: 26px;
        }
        .popup-box .book-name {
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
            display: block;
            margin: 5px 0;
        }
        .popup-box .book-author {
            color: #666;
            font-size: 15px;
            display: block;
            margin-bottom: 15px;
        }
        .popup-box .app-section {
            background: #e8f5e9;
            padding: 15px 20px;
            border-radius: 12px;
            margin: 15px 0;
        }
        .popup-box .app-section .label {
            font-size: 13px;
            color: #555;
        }
        .popup-box .app-section .app-name {
            font-size: 22px;
            font-weight: bold;
            color: #2e7d32;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .popup-box .app-section .app-name .app-icon {
            font-size: 28px;
        }
        .popup-box .info-text {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin: 10px 0;
        }
        .apps-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin: 15px 0 5px;
        }
        .apps-list .app-item {
            background: #f5f5f5;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #333;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .btn-group a {
            text-decoration: none;
        }
        .btn-group button {
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s ease;
        }
        .btn-group .btn-download {
            background: #28a745;
            color: white;
        }
        .btn-group .btn-download:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .btn-group .btn-read {
            background: #0b1f3a;
            color: white;
        }
        .btn-group .btn-read:hover {
            background: #1a3a5c;
            transform: translateY(-2px);
        }
        .btn-group .btn-profile {
            background: #6c757d;
            color: white;
        }
        .btn-group .btn-profile:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .btn-group .btn-download-now {
            background: #ffc72c;
            color: #0b1f3a;
            padding: 14px 35px;
            font-size: 17px;
        }
        .btn-group .btn-download-now:hover {
            background: #e6b300;
            transform: translateY(-2px);
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .bounce-icon {
            animation: bounce 1.5s infinite;
        }
        /* Dark Mode */
        body.dark-mode .popup-box {
            background: #1a1a2e;
            color: white;
        }
        body.dark-mode .popup-box h2 {
            color: white;
        }
        body.dark-mode .popup-box .book-author {
            color: #aaa;
        }
        body.dark-mode .popup-box .info-text {
            color: #aaa;
        }
        body.dark-mode .apps-list .app-item {
            background: #2a2a4e;
            color: #ddd;
        }
        body.dark-mode .popup-box .app-section {
            background: #1a3a2a;
        }
        body.dark-mode .popup-box .app-section .app-name {
            color: #81c784;
        }
        body.dark-mode .popup-box .app-section .label {
            color: #aaa;
        }
        /* Mobile Responsive */
        @media (max-width: 480px) {
            .popup-box {
                padding: 25px 20px;
            }
            .popup-box h2 {
                font-size: 22px;
            }
            .btn-group button {
                width: 100%;
            }
            .popup-box .app-section .app-name {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
" . notification_markup() . "

<div class=\"popup-box\">
    <div class=\"icon bounce-icon\">📚</div>
    <h2>✅ Ready to Download!</h2>
    
    <span class=\"book-name\">\"" . htmlspecialchars($book_title) . "\"</span>
    <span class=\"book-author\">✍️ " . htmlspecialchars($book_author) . "</span>
    
    <div class=\"app-section\">
        <div class=\"label\">📱 Opens in</div>
        <div class=\"app-name\">
            <span class=\"app-icon\">▶️</span>
            <span>Google Play Books</span>
        </div>
    </div>
    
    <p class=\"info-text\">
        Book files can be opened in compatible reading apps:<br>
        <span style=\"color:#0b1f3a;font-weight:bold;\">
        📱 Google Play Books • 📖 Adobe Digital Editions • 📚 Apple Books
        </span>
    </p>
    
    <div class=\"apps-list\">
        <span class=\"app-item\">📱 Google Play Books</span>
        <span class=\"app-item\">📖 Adobe Digital Editions</span>
        <span class=\"app-item\">📚 Apple Books</span>
        <span class=\"app-item\">📕 Amazon Kindle</span>
        <span class=\"app-item\">📗 FBReader</span>
        <span class=\"app-item\">📘 Lithium</span>
    </div>
    
    <div class=\"btn-group\">
        <a href=\"$downloadHref\" target=\"_blank\" rel=\"noopener noreferrer\">
            <button class=\"btn-download-now\">📥 Download Book File</button>
        </a>
    </div>
    
    <div class=\"btn-group\" style=\"margin-top:10px;\">
        <a href=\"read_book.php?id=$book_id\">
            <button class=\"btn-read\">📖 Read Online</button>
        </a>
        <a href=\"profile.php\">
            <button class=\"btn-profile\">👤 My Profile</button>
        </a>
        <a href=\"books.php\">
            <button class=\"btn-profile\">📚 All Books</button>
        </a>
    </div>
</div>

<script>
// Dark mode check
if(localStorage.getItem(\"theme\") === \"dark\"){
    document.body.classList.add(\"dark-mode\");
}
</script>

</body>
</html>";

        exit();

    }

}

header("Location: books.php");
exit();
?>