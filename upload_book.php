<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_admin($conn);

$error = '';
if (isset($_POST['upload'])) {
    $title       = trim($_POST['title'] ?? '');
    $author      = trim($_POST['author'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $author === '') {
        $error = 'Title and author are required.';
    } else {
        [$okPdf, $pdfPath] = save_upload($_FILES['pdf'] ?? [], 'uploads/pdfs', ['application/pdf' => 'pdf'], 20 * 1024 * 1024, 'book');
        [$okCov, $coverPath] = save_upload($_FILES['cover'] ?? [], 'uploads/covers',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 3 * 1024 * 1024, 'cover');

        if (!$okPdf)      { $error = 'PDF: ' . $pdfPath; }
        elseif (!$okCov)  { @unlink(__DIR__ . '/' . $pdfPath); $error = 'Cover: ' . $coverPath; }
        else {
            $ok = db_exec($conn,
                "INSERT INTO books (title, author, category, description, cover_image_url, read_link, download_epub_link, pdf_file, cover_file, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PDF Upload')",
                'sssssssss', [$title, $author, $category, $description, $coverPath, $pdfPath, $pdfPath, $pdfPath, $coverPath]);
            if ($ok === false) {
                @unlink(__DIR__ . '/' . $pdfPath); @unlink(__DIR__ . '/' . $coverPath);
                $error = 'Could not save the book.';
            } else {
                js_alert_redirect('PDF Book Uploaded Successfully', 'admin_dashboard.php?page=all_books');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload PDF Book</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<section class="form-section" style="display:flex;justify-content:center;padding:30px;">
<div class="form-box" style="max-width:480px;width:100%;">
    <h2>📤 Upload PDF Book</h2>
    <?php if ($error): ?><p style="color:#c0392b;"><?php echo e($error); ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="text" name="title" placeholder="Title" required>
        <input type="text" name="author" placeholder="Author" required>
        <input type="text" name="category" placeholder="Category">
        <textarea name="description" placeholder="Description" rows="4"></textarea>
        <label>PDF file (max 20 MB)</label>
        <input type="file" name="pdf" accept="application/pdf" required>
        <label>Cover image (jpg/png/webp, max 3 MB)</label>
        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit" name="upload">Upload</button>
    </form>
    <p><a href="admin_dashboard.php">← Back to dashboard</a></p>
</div>
</section>
</body>
</html>
