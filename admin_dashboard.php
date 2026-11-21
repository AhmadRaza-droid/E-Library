<?php
include 'session_timeout.php';
include 'config.php';
require_once __DIR__ . '/mailer.php';

require_admin($conn);
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/student_resources.php';
$pendingMaterials = resources_pending($conn);
$me = (int)$_SESSION['admin_id'];

$notice = null;   // ['message', 'redirect']
function done($msg, $page){
    global $conn;
    $actions = [
        'delete_book'=>'Delete book','delete_user'=>'Delete user','add_book'=>'Add book',
        'add_category'=>'Add category','delete_category'=>'Delete category',
        'make_admin'=>'Grant admin role','remove_admin'=>'Remove admin role',
        'delete_message'=>'Delete message','reply_submit'=>'Reply to message',
        'send_notification'=>'Send notification'
    ];
    foreach ($actions as $key => $label) {
        if (!isset($_POST[$key])) continue;
        $target = is_scalar($_POST[$key]) ? (string)$_POST[$key] : '';
        if ($key === 'add_book') $target = trim((string)($_POST['title'] ?? ''));
        if ($key === 'add_category') $target = trim((string)($_POST['category'] ?? ''));
        if ($key === 'send_notification') $target = trim((string)($_POST['title'] ?? ''));
        $detail = $target !== '' ? 'Target: ' . $target . ' | Result: ' . trim(strip_tags((string)$msg)) : 'Result: ' . trim(strip_tags((string)$msg));
        audit_log($conn, $label, $detail);
        break;
    }
    js_alert_redirect($msg, 'admin_dashboard.php?page=' . $page);
}

// ========== ALL STATE-CHANGING ACTIONS ARE POST + CSRF (checked globally in config.php) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- DELETE BOOK ----
    if (isset($_POST['delete_book'])) {
        $id = (int)$_POST['delete_book'];
        done(admin_delete_record($conn, 'book', $id) ? 'Book deleted successfully!' : '❌ Book not found or could not be deleted.', 'all_books');
    }

    // ---- DELETE USER (not yourself) ----
    if (isset($_POST['delete_user'])) {
        $id = (int)$_POST['delete_user'];
        if ($id === $me) { done('❌ You cannot delete your own account.', 'all_users'); }
        done(admin_delete_record($conn, 'user', $id) ? 'User deleted successfully!' : '❌ User not found or could not be deleted.', 'all_users');
    }

    // ---- ADD BOOK ----
    if (isset($_POST['add_book'])) {
        $title       = trim($_POST['title'] ?? '');
        $author      = trim($_POST['author'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $cover       = safe_url($_POST['cover_image_url'] ?? '');
        $read_link   = trim($_POST['read_link'] ?? '');
        $dl_link     = trim($_POST['download_epub_link'] ?? '');

        $read_ok = ctype_digit($read_link) || safe_url($read_link) !== '';
        $dl_ok   = $dl_link === '' || ctype_digit($dl_link) || safe_url($dl_link) !== '';
        if ($title === '' || $author === '' || !$read_ok || !$dl_ok) {
            done('❌ Title, author and valid http(s) links are required.', 'add_book');
        }
        $ok = db_exec($conn,
            "INSERT INTO books (title, author, description, category, cover_image_url, read_link, download_epub_link)
             VALUES (?, ?, ?, ?, ?, ?, ?)", 'sssssss', [$title, $author, $description, $category, $cover, $read_link, $dl_link]);
        done($ok === false ? '❌ Error adding book.' : '✅ Book added successfully!', 'all_books');
    }

    // ---- CATEGORIES ----
    if (isset($_POST['add_category'])) {
        $category = trim($_POST['category'] ?? '');
        if ($category === '' || mb_strlen($category) > 100) {
            done('❌ Enter a category name of at most 100 characters.', 'categories');
        }
        $ok = db_exec($conn, "INSERT INTO categories (name) VALUES (?)", 's', [$category]);
        done($ok === 1 ? '✅ Category added!' : '❌ Could not add the category.', 'categories');
    }
    if (isset($_POST['delete_category'])) {
        $ok = db_exec($conn, "DELETE FROM categories WHERE id=?", 'i', [(int)$_POST['delete_category']]);
        done($ok === 1 ? 'Category deleted!' : '❌ Category not found or could not be deleted.', 'categories');
    }

    // ---- ADMIN RIGHTS ----
    if (isset($_POST['make_admin'])) {
        $ok = db_exec($conn, "UPDATE users SET is_admin=1 WHERE id=?", 'i', [(int)$_POST['make_admin']]);
        done($ok === 1 ? 'User is now Admin!' : '❌ User not found or already an admin.', 'all_users');
    }
    if (isset($_POST['remove_admin'])) {
        $id = (int)$_POST['remove_admin'];
        $admins = (int)db_value($conn, "SELECT COUNT(*) FROM users WHERE is_admin=1", '', [], 0);
        if ($id === $me || $admins <= 1) { done('❌ You cannot remove the last / your own admin rights.', 'all_users'); }
        $ok = db_exec($conn, "UPDATE users SET is_admin=0 WHERE id=?", 'i', [$id]);
        done($ok === 1 ? 'Admin rights removed!' : '❌ Admin not found or rights could not be removed.', 'all_users');
    }

    // ---- MESSAGES ----
    if (isset($_POST['delete_message'])) {
        $ok = db_exec($conn, "DELETE FROM messages WHERE id=?", 'i', [(int)$_POST['delete_message']]);
        done($ok === 1 ? 'Message deleted successfully!' : '❌ Message not found or could not be deleted.', 'messages');
    }
    if (isset($_POST['reply_submit'])) {
        $message_id = (int)($_POST['message_id'] ?? 0);
        $reply      = trim($_POST['reply'] ?? '');
        if ($message_id <= 0 || $reply === '') { done('❌ Enter a reply before sending.', 'messages'); }
        if (db_exec($conn, "UPDATE messages SET reply=? WHERE id=?", 'si', [$reply, $message_id]) === false) {
            done('❌ Could not save the reply.', 'messages');
        }

        $msg = db_row($conn, "SELECT * FROM messages WHERE id=?", 'i', [$message_id]);
        if (!$msg) { done('❌ Message not found.', 'messages'); }
        if (validate_email($msg['email'])) {
            $html = "<div style='font-family: Arial; padding: 15px;'>
                        <h3>Reply from Admin</h3>
                        <p><strong>Your message:</strong> " . nl2br(e($msg['message'])) . "</p>
                        <p><strong>Admin Reply:</strong> " . nl2br(e($reply)) . "</p>
                        <hr><small>Book's Library Team</small></div>";
            if (send_mail($msg['email'], $msg['name'], 'Reply to your message', $html)) {
                done('✅ Reply saved and email sent.', 'messages');
            }
        }
        done('⚠️ Reply saved, but the email could not be sent.', 'messages');
    }

    // ---- NOTIFICATIONS (saved + e-mailed over ONE smtp connection) ----
    if (isset($_POST['send_notification'])) {
        $title        = trim($_POST['title'] ?? '');
        $message_body = trim($_POST['message'] ?? '');
        if ($title === '' || $message_body === '') { done('❌ Title and message are required.', 'notifications'); }

        if (db_exec($conn, "INSERT INTO notifications (title, message, created_at) VALUES (?, ?, NOW())", 'ss', [$title, $message_body]) === false) {
            done('❌ Could not save the notification.', 'notifications');
        }

        $recipients  = db_all($conn, "SELECT email, fullname FROM users WHERE email != '' AND is_verified=1");
        $total_users = count($recipients);
        $sent_count  = 0;
        @set_time_limit(300);

        try {
            $mail = make_mailer(true);
            $mail->isHTML(true);
            $mail->Subject = $title;
            foreach ($recipients as $u) {
                try {
                    $mail->clearAddresses();
                    $mail->addAddress($u['email'], $u['fullname']);
                    $mail->Body = "<div style='font-family: Arial; padding: 20px;'>
                        <h2 style='color: #0b1f3a;'>📢 " . e($title) . "</h2>
                        <p>" . nl2br(e($message_body)) . "</p><br><hr>
                        <small style='color: #666;'>📚 Book's Library Team</small></div>";
                    $mail->AltBody = $title . "\n\n" . $message_body;
                    $mail->send();
                    $sent_count++;
                } catch (\Throwable $ex) { error_log('Notification mail failed: ' . $ex->getMessage()); }
            }
            $mail->smtpClose();
        } catch (\Throwable $ex) { error_log('Notification mailer failed: ' . $ex->getMessage()); }

        done("✅ Notification sent to $sent_count out of $total_users users via email!\nSaved to database also.", 'notifications');
    }
}

// Get current page (whitelist)
$valid_pages = ['dashboard', 'add_book', 'categories', 'website', 'all_books', 'all_users', 'messages', 'notifications', 'auto_import', 'book_requests', 'reviews'];
$page = (isset($_GET['page']) && in_array($_GET['page'], $valid_pages, true)) ? $_GET['page'] : 'dashboard';

$books         = mysqli_query($conn, "SELECT * FROM books ORDER BY id DESC");
$messages      = mysqli_query($conn, "SELECT * FROM messages ORDER BY id DESC");
$all_users     = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
$notifications = mysqli_query($conn, "SELECT * FROM notifications ORDER BY id DESC");
$categories    = mysqli_query($conn, "SELECT * FROM categories ORDER BY id DESC");

$total_users      = (int)db_value($conn, "SELECT COUNT(*) FROM users", '', [], 0);
$total_books      = (int)db_value($conn, "SELECT COUNT(*) FROM books", '', [], 0);
$total_reviews    = (int)db_value($conn, "SELECT COUNT(*) FROM reviews", '', [], 0);
$total_categories = (int)db_value($conn, "SELECT COUNT(*) FROM categories", '', [], 0);
$total_downloads  = (int)db_value($conn, "SELECT COALESCE(SUM(download_count),0) FROM books", '', [], 0);
$verified_users   = (int)db_value($conn, "SELECT COUNT(*) FROM users WHERE is_verified=1", '', [], 0);
$pending_requests = (int)db_value($conn, "SELECT COUNT(*) FROM book_requests WHERE status='Pending'", '', [], 0);

$most_downloaded = mysqli_query($conn, "SELECT title, download_count FROM books ORDER BY download_count DESC LIMIT 5");
$most_favorites  = mysqli_query($conn,
"SELECT books.title, COUNT(favorites.id) AS total
 FROM favorites
 JOIN books ON favorites.book_id = books.id
 GROUP BY books.id, books.title
 ORDER BY total DESC
 LIMIT 5");

$book_requests = mysqli_query($conn,
"SELECT book_requests.*, users.fullname, users.email
 FROM book_requests
 JOIN users ON book_requests.user_id = users.id
 ORDER BY book_requests.id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css?v=8000">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; }
        .side-menu {
            position: fixed; left: 0; top: 0; width: 260px; height: 100%;
            background: linear-gradient(180deg, #0b1f3a 0%, #1a3a5c 100%);
            color: white; z-index: 100; overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .side-menu .logo { padding: 25px 20px; font-size: 22px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .side-menu a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: white; text-decoration: none; border-left: 3px solid transparent; }
        .side-menu a:hover { background: rgba(255,255,255,0.1); border-left-color: #ffc72c; }
        .side-menu a.active { background: rgba(255,255,255,0.15); border-left-color: #ffc72c; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-bar { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .top-bar h2 { color: #0b1f3a; }
        .dark-btn { background: #0b1f3a; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; }
        .stats-container { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .stat-card { flex: 1 1 190px; min-width: 0; }
        .stat-card h1 { overflow-wrap: anywhere; }
        .quick-action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
        .quick-action-grid a { display: flex; align-items: center; min-height: 48px; padding: 12px 14px; border-radius: 9px; background: #f1f5f9; color: #0b1f3a; text-decoration: none; font-weight: 600; border: 1px solid #e2e8f0; }
        .quick-action-grid a:hover, .quick-action-grid a:focus { background: #e3edf7; border-color: #8aa9c8; outline: 2px solid transparent; }
        body.dark-mode .quick-action-grid a { background: #202d46; color: #f5f7fb; border-color: #34435e; }
        body.dark-mode .quick-action-grid a:hover, body.dark-mode .quick-action-grid a:focus { background: #2b3b59; }

        .stat-card { background: white; padding: 20px; border-radius: 10px; width: 220px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-card h2 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card h1 { color: #0b1f3a; font-size: 32px; }
        .table-section { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; overflow-x: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .table-section h2 { margin-bottom: 15px; color: #0b1f3a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #0b1f3a; color: white; }
        button, .btn { background: #0b1f3a; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; }
        button.red, .btn-red { background: #dc3545; }
        button.green, .btn-green { background: #28a745; }
        input, textarea, select { padding: 10px; margin: 5px; border: 1px solid #ddd; border-radius: 5px; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .dark-mode { background: #1a1a2e; color: white; }
        .dark-mode .stat-card, .dark-mode .table-section, .dark-mode .top-bar { background: #16213e; color: white; }
        .dark-mode .stat-card h2, .dark-mode .stat-card h1 { color: white; }
        .hamburger-mobile { display: none; }
        @media (max-width: 768px) {
            .side-menu { left: -260px; transition: 0.3s; }
            .side-menu.open { left: 0; }
            .main-content { margin-left: 0; }
            .hamburger-mobile { display: block; position: fixed; top: 15px; left: 15px; z-index: 101; background: #0b1f3a; padding: 10px; border-radius: 5px; cursor: pointer; color: white; font-size: 24px; }
        }
        .badge-admin { background: #ffc72c; color: #0b1f3a; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }

        /* Refined admin control centre */
        body { background:#eef2f7; color:#172338; }
        .side-menu { width:270px; background:linear-gradient(160deg,#10243d 0%,#173e5c 55%,#12645d 100%); padding-bottom:18px; }
        .side-menu .logo { text-align:left; padding:24px 22px; font-size:20px; }
        .side-menu a { margin:3px 12px; padding:11px 14px; border:0; border-radius:9px; font-size:14px; transition:background .18s,transform .18s; }
        .side-menu a:hover { background:rgba(255,255,255,.13); border:0; transform:translateX(2px); }
        .side-menu a.active { background:rgba(255,255,255,.19); border:0; box-shadow:inset 3px 0 #f5c84c; }
        .main-content { margin-left:270px; padding:26px clamp(14px,2.5vw,36px); max-width:1800px; }
        .top-bar { position:sticky; top:10px; z-index:50; padding:17px 22px; border:1px solid #e4eaf1; border-radius:14px; box-shadow:0 8px 24px rgba(20,40,70,.07); }
        .top-bar h2 { font-size:22px; text-transform:capitalize; }
        .dark-btn { border-radius:9px; padding:10px 16px; }
        .stats-container { display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:15px; margin:18px 0 24px; }
        .stat-card { width:auto; min-height:126px; border:1px solid #e5ebf2; border-radius:14px; box-shadow:0 6px 20px rgba(20,40,70,.055); position:relative; overflow:hidden; }
        .stat-card:after { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:#1b8175; }
        .stat-card h2 { font-size:12px; letter-spacing:.07em; text-transform:uppercase; }
        .stat-card h1 { font-size:30px; }
        .table-section { border:1px solid #e5ebf2; border-radius:14px; box-shadow:0 6px 22px rgba(20,40,70,.055); padding:22px; margin-bottom:20px; }
        .table-section h2 { font-size:19px; margin-bottom:16px; }
        table { min-width:650px; }
        th { background:#173b56; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
        td { font-size:14px; vertical-align:middle; }
        tr:nth-child(even) td { background:#f8fafc; }
        tr:hover td { background:#edf6f5; }
        button,.btn { border-radius:8px; font-weight:600; transition:filter .15s,transform .15s; }
        button:hover,.btn:hover { filter:brightness(1.08); transform:translateY(-1px); }
        input,textarea,select { max-width:100%; border-radius:8px; }
        .admin-table-search { width:min(100%,420px); margin:0 0 14px; padding:12px 14px; border:1px solid #d6e0e9; background:#fff; }
        .admin-date { color:#728096; font-size:13px; font-weight:500; }
        body.dark-mode { background:#0c1422; color:#e7edf5; }
        body.dark-mode .top-bar,body.dark-mode .stat-card,body.dark-mode .table-section { background:#152235; border-color:#263a53; color:#e7edf5; }
        body.dark-mode .top-bar h2,body.dark-mode .table-section h2,body.dark-mode .stat-card h1 { color:#f4f7fb; }
        body.dark-mode .stat-card h2 { color:#aebbd0; }
        body.dark-mode td { color:#e1e8f1; border-color:#31445c; }
        body.dark-mode tr:nth-child(even) td { background:#192b41; }
        body.dark-mode tr:hover td { background:#223b54; }
        body.dark-mode .admin-table-search,body.dark-mode input,body.dark-mode textarea,body.dark-mode select { background:#101c2d; color:#eef4fb; border-color:#344961; }
        @media(max-width:768px) {
            .side-menu { width:270px; left:-270px; }
            .side-menu.open { left:0; }
            .main-content { margin-left:0; padding:72px 12px 20px; }
            .top-bar { top:8px; padding:14px; }
            .top-bar h2 { font-size:18px; }
            .stats-container { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
            .stat-card { padding:15px; min-height:105px; }
            .stat-card h1 { font-size:25px; }
            .table-section { padding:15px; }
        }
        @media(max-width:390px) { .stats-container { grid-template-columns:1fr; } }

    </style>
</head>
<body>
<?php echo notification_markup(); ?>

<div class="hamburger-mobile" onclick="toggleMobileMenu()">☰</div>

<div class="side-menu" id="sideMenu">
    <div class="logo">📖 Admin Panel</div>
    <a href="?page=dashboard" class="<?php echo $page == 'dashboard' ? 'active' : ''; ?>">🏠 Dashboard</a>
    <a href="?page=website" class="<?php echo $page == 'website' ? 'active' : ''; ?>">🌐 Website</a>
    <a href="?page=all_books" class="<?php echo $page == 'all_books' ? 'active' : ''; ?>">📚 All Books</a>
    <a href="?page=add_book" class="<?php echo $page == 'add_book' ? 'active' : ''; ?>">➕ Add Book</a>
    <a href="upload_book.php">📤 Upload PDF</a>
    <a href="admin_resources.php">📑 Student materials <span class="resource-admin-badge" data-resource-count><?php echo $pendingMaterials; ?></span></a>
    <a href="?page=categories" class="<?php echo $page == 'categories' ? 'active' : ''; ?>">📂 Categories</a>
    <a href="?page=all_users" class="<?php echo $page == 'all_users' ? 'active' : ''; ?>">👥 All Users</a>
    <a href="?page=messages" class="<?php echo $page == 'messages' ? 'active' : ''; ?>">💬 Messages</a>
    <a href="?page=notifications" class="<?php echo $page == 'notifications' ? 'active' : ''; ?>">🔔 Notifications</a>
    <a href="?page=auto_import" class="<?php echo $page == 'auto_import' ? 'active' : ''; ?>">📥 Auto Import</a>
    <a href="?page=book_requests" class="<?php echo $page == 'book_requests' ? 'active' : ''; ?>">📝 Book Requests</a>
    <a href="?page=reviews" class="<?php echo $page == 'reviews' ? 'active' : ''; ?>">⭐ Reviews</a>
    <a href="admin_analytics.php">📊 Analytics</a>
    <a href="admin_audit_logs.php">🧾 Audit Log</a>
    <a href="admin_user_activity.php">👥 User Activity</a>
    <a href="admin_settings.php">⚙️ Settings</a>
    <a href="admin_backup.php">💾 Backup</a>
    <a href="performance.php">🚀 Performance</a>
    <a href="admin_change_password.php">🔑 Change Password</a>
    <a href="admin_logout.php">🚪 Logout</a>
</div>

<div class="main-content">
    <div class="top-bar">
        <div><h2><?php echo ucfirst(str_replace('_', ' ', $page)); ?></h2><span class="admin-date">Library control centre · <?php echo date('D, d M Y'); ?></span></div>
        <button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark Mode</button>
    </div>

    <!-- ========== DASHBOARD PAGE ========== -->
    <p class="resource-callout" data-resource-notice role="status" <?php if (!$pendingMaterials) echo 'hidden'; ?>><a href="admin_resources.php">🔔 <span data-resource-count><?php echo $pendingMaterials; ?></span> student uploads awaiting your approval — Review materials</a></p>
    <link rel="stylesheet" href="resources.css?v=1">
    <script src="resource-admin.js?v=1" defer></script>
    <?php if($page == 'dashboard'): ?>
    <div class="stats-container">
        <div class="stat-card"><h2>Total Users</h2><h1><?php echo $total_users; ?></h1></div>
        <div class="stat-card"><h2>Total Books</h2><h1><?php echo $total_books; ?></h1></div>
        <div class="stat-card"><h2>Total Categories</h2><h1><?php echo $total_categories; ?></h1></div>
        <div class="stat-card"><h2>Total Reviews</h2><h1><?php echo $total_reviews; ?></h1></div>
        <div class="stat-card"><h2>Total Downloads</h2><h1><?php echo $total_downloads; ?></h1></div>
        <div class="stat-card"><h2>Verified Users</h2><h1><?php echo $verified_users; ?></h1></div>
        <div class="stat-card"><h2>Pending Book Requests</h2><h1><?php echo $pending_requests; ?></h1></div>
    </div>

    <div class="table-section admin-quick-actions">
        <h2>Quick Actions</h2>
        <div class="quick-action-grid">
            <a href="admin_dashboard.php?page=add_book">＋ Add a Book</a>
            <a href="admin_dashboard.php?page=all_books">📚 Manage Books</a>
            <a href="admin_dashboard.php?page=all_users">👥 Manage Users</a>
            <a href="admin_dashboard.php?page=book_requests">📝 Review Book Requests</a>
            <a href="admin_resources.php">📂 Student Materials</a>
            <a href="admin_analytics.php">📊 View Analytics</a>
        </div>
    </div>

    <div class="table-section">
        <h2>Most Downloaded Books</h2>
        <table>
            <tr><th>Book Title</th><th>Downloads</th></tr>
            <?php while($row = mysqli_fetch_assoc($most_downloaded)){ ?>
            <tr><td><?php echo htmlspecialchars($row['title']); ?></td><td><?php echo $row['download_count']; ?></td></tr>
            <?php } ?>
        </table>
    </div>

    <div class="table-section">
        <h2>Most Favorited Books</h2>
        <table>
            <tr><th>Book Title</th><th>Favorites</th></tr>
            <?php while($row = mysqli_fetch_assoc($most_favorites)){ ?>
            <tr><td><?php echo htmlspecialchars($row['title']); ?></td><td><?php echo $row['total']; ?></td></tr>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ========== ADD BOOK PAGE ========== -->
    <?php if($page == 'add_book'): ?>
    <div class="table-section">
        <h2>➕ Add New Book</h2>
        <form method="POST" action="">
<?php echo csrf_field(); ?>
            <input type="text" name="title" placeholder="Book Title" required>
            <input type="text" name="author" placeholder="Author" required>
            <select name="category">
                <option value="">Select Category</option>
                <?php 
                $cats = mysqli_query($conn, "SELECT * FROM categories");
                while($cat = mysqli_fetch_assoc($cats)){ ?>
                    <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php } ?>
            </select>
            <textarea name="description" placeholder="Description" rows="4"></textarea>
            <input type="text" name="cover_image_url" placeholder="Cover Image URL">
            <input type="text" name="read_link" placeholder="Read Link" required>
            <input type="text" name="download_epub_link" placeholder="Download EPUB Link">
            <button type="submit" name="add_book" class="green">Add Book</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ========== CATEGORIES PAGE ========== -->
    <?php if($page == 'categories'): ?>
    <div class="table-section">
        <h2>📂 Manage Categories</h2>
        <form method="POST" action="" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
<?php echo csrf_field(); ?>
            <input type="text" name="category" placeholder="Category Name" required style="flex:1;min-width:200px;">
            <button type="submit" name="add_category" class="green">Add Category</button>
        </form>
        <table>
            <tr><th>ID</th><th>Category Name</th><th>Action</th></tr>
            <?php while($cat = mysqli_fetch_assoc($categories)){ ?>
            <tr>
                <td><?php echo $cat['id']; ?></td>
                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                <td><form method="POST" action="?page=categories" style="display:inline;" onsubmit="return confirm('Delete this category?')">
<?php echo csrf_field(); ?><input type="hidden" name="delete_category" value="<?php echo (int)$cat['id']; ?>"><button type="submit" class="red">Delete</button></form></td>
            </tr>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ========== WEBSITE PAGE ========== -->
    <?php if($page == 'website'): ?>
    <div class="table-section">
        <h2>🌐 Website</h2>
        <p>Website is live at: <a href="index.php" target="_blank">index.php</a></p>
        <button onclick="location.href='index.php'">Go to Website</button>
    </div>
    <?php endif; ?>

    <!-- ========== ALL BOOKS PAGE ========== -->
    <?php if($page == 'all_books'): ?>
    <div class="table-section">
        <h2>📚 All Books (<?php echo mysqli_num_rows($books); ?>)</h2>
        <table>
            <tr><th>ID</th><th>Cover</th><th>Title</th><th>Author</th><th>Category</th><th>Downloads</th><th>Action</th></tr>
            <?php while($row = mysqli_fetch_assoc($books)){ ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <?php $adminCover = safe_url($row['cover_image_url'] ?? '') ?: (safe_url($row['cover_file'] ?? '') ?: safe_url($row['image'] ?? '')); ?>
                <td><?php if ($adminCover !== ''): ?><img src="<?php echo e($adminCover); ?>" width="50" alt="Book cover" style="border-radius:5px;"><?php else: ?>📚<?php endif; ?></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['author']); ?></td>
                <td><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                <td><?php echo $row['download_count'] ?? 0; ?></td>
                <td><form method="POST" action="?page=all_books" style="display:inline;" onsubmit="return confirm('<?php echo !empty($row['pdf_file']) || !empty($row['cover_file']) ? 'Delete this book? Uploaded files remain on the server.' : 'Delete this book?'; ?>')">
<?php echo csrf_field(); ?><input type="hidden" name="delete_book" value="<?php echo (int)$row['id']; ?>"><button type="submit" class="red">Delete</button></form></td>
            </tr>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ========== ALL USERS PAGE ========== -->
    <?php if($page == 'all_users'): ?>
    <div class="table-section">
        <h2>👥 All Users (<?php echo mysqli_num_rows($all_users); ?>)</h2>
        <table>
            <tr><th>ID</th><th>Profile</th><th>Full Name</th><th>Email</th><th>Role</th><th>Action</th></tr>
            <?php while($user = mysqli_fetch_assoc($all_users)){ ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo !empty($user['profile_image']) ? '<img src="'.e($user['profile_image']).'" width="40" style="border-radius:50%;">' : '👤'; ?></td>
                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                    <?php if(isset($user['is_admin']) && $user['is_admin'] == 1){ ?>
                        <span class="badge-admin">👑 Admin</span>
                    <?php } else { ?>
                        User
                    <?php } ?>
                </td>
                <td class="action-buttons">
                    <?php if(isset($user['is_admin']) && $user['is_admin'] == 1){ ?>
                        <form method="POST" action="?page=all_users" style="display:inline;" onsubmit="return confirm('Remove admin rights?')">
<?php echo csrf_field(); ?><input type="hidden" name="remove_admin" value="<?php echo (int)$user['id']; ?>"><button type="submit" class="" style="background:#ffc72c;color:#0b1f3a;">Remove Admin</button></form>
                    <?php } else { ?>
                        <form method="POST" action="?page=all_users" style="display:inline;" onsubmit="return confirm('Give this user admin rights?')">
<?php echo csrf_field(); ?><input type="hidden" name="make_admin" value="<?php echo (int)$user['id']; ?>"><button type="submit" class="green">Make Admin</button></form>
                    <?php } ?>
                    <form method="POST" action="?page=all_users" style="display:inline;" onsubmit="return confirm('Delete this user?')">
<?php echo csrf_field(); ?><input type="hidden" name="delete_user" value="<?php echo (int)$user['id']; ?>"><button type="submit" class="red">Delete</button></form>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ========== MESSAGES PAGE ========== -->
    <?php if($page == 'messages'): ?>
    <div class="table-section">
        <h2>💬 User Messages</h2>
        <table>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Message</th><th>Reply</th><th>Action</th></tr>
            <?php while($row = mysqli_fetch_assoc($messages)){ ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['message']); ?></td>
                    <td width="250">
                        <form id="message-<?php echo (int)$row['id']; ?>" method="POST" action="">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="message_id" value="<?php echo (int)$row['id']; ?>">
                            <input type="text" name="reply" value="<?php echo htmlspecialchars($row['reply'] ?? ''); ?>" placeholder="Type reply..." style="width:100%">
                        </form>
                    </td>
                    <td class="action-buttons">
                        <button type="submit" form="message-<?php echo (int)$row['id']; ?>" name="reply_submit" class="green">Reply</button>
                        <button type="submit" form="message-<?php echo (int)$row['id']; ?>" name="delete_message" value="<?php echo (int)$row['id']; ?>" class="red" formnovalidate onclick="return confirm('Delete this message?')">Delete</button>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ========== NOTIFICATIONS PAGE ========== -->
    <?php if($page == 'notifications'): ?>
    <div class="table-section">
        <h2>🔔 Send Notification</h2>
        <p style="margin-bottom: 15px;">Notifications are saved to the library and emailed to verified users.</p>
        <form method="POST" action="">
<?php echo csrf_field(); ?>
            <input type="text" name="title" placeholder="Notification Title" required style="width:100%; margin-bottom:10px;">
            <textarea name="message" placeholder="Write notification..." rows="5" required style="width:100%; margin-bottom:10px;"></textarea>
            <button type="submit" name="send_notification" class="green">📢 Send to All Users</button>
        </form>
    </div>
    <div class="table-section">
        <h2>📋 Sent Notifications</h2>
        <table>
            <tr><th>ID</th><th>Title</th><th>Message</th><th>Date</th></tr>
            <?php while($notif = mysqli_fetch_assoc($notifications)){ ?>
            <tr><td><?php echo $notif['id']; ?></td><td><?php echo htmlspecialchars($notif['title']); ?></td><td><?php echo htmlspecialchars($notif['message']); ?></td><td><?php echo $notif['created_at']; ?></td></tr>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ========== AUTO IMPORT PAGE ========== -->
    <?php if($page == 'auto_import'): ?>
    <div class="table-section">
        <h2>📥 Auto Import Books</h2>
        <a href="auto_scrape_books.php"><button class="green">🚀 Auto Import Books</button></a>
    </div>
    <?php endif; ?>

    <!-- ========== BOOK REQUESTS PAGE ========== -->
    <?php if($page == 'book_requests'): ?>
    <div class="table-section">
        <h2>📝 Book Requests</h2>
        <table>
            <tr><th>ID</th><th>User</th><th>Email</th><th>Book</th><th>Category</th><th>Status</th><th>Action</th></tr>
            <?php while($req = mysqli_fetch_assoc($book_requests)){ ?>
                <tr>
                    <td><?php echo $req['id']; ?></td>
                    <td><?php echo htmlspecialchars($req['fullname']); ?></td>
                    <td><?php echo htmlspecialchars($req['email']); ?></td>
                    <td><?php echo htmlspecialchars($req['book_name']); ?></td>
                    <td><?php echo htmlspecialchars($req['category']); ?></td>
                    <td>
                        <form id="request-<?php echo (int)$req['id']; ?>" method="POST" action="update_request_status.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="request_id" value="<?php echo (int)$req['id']; ?>">
                        <select name="status">
                            <option <?php echo $req['status']=='Pending'?'selected':''; ?>>Pending</option>
                            <option <?php echo $req['status']=='Approved'?'selected':''; ?>>Approved</option>
                            <option <?php echo $req['status']=='Added'?'selected':''; ?>>Added</option>
                            <option <?php echo $req['status']=='Rejected'?'selected':''; ?>>Rejected</option>
                        </select>
                        </form>
                    </td>
                    <td><button type="submit" form="request-<?php echo (int)$req['id']; ?>" name="update_status">Update</button></td>
                </tr>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ========== REVIEWS PAGE ========== -->
    <?php if($page == 'reviews'): ?>
    <div class="table-section">
        <h2>⭐ Book Reviews</h2>
        <table>
            <tr><th>ID</th><th>User</th><th>Book</th><th>Rating</th><th>Review</th><th>Action</th></tr>
            <?php
            $reviews = mysqli_query($conn, "SELECT reviews.*, users.fullname, books.title FROM reviews JOIN users ON reviews.user_id = users.id JOIN books ON reviews.book_id = books.id ORDER BY reviews.id DESC");
            while($review = mysqli_fetch_assoc($reviews)){ ?>
            <tr>
                <td><?php echo $review['id']; ?></td>
                <td><?php echo htmlspecialchars($review['fullname']); ?></td>
                <td><?php echo htmlspecialchars($review['title']); ?></td>
                <td><?php echo $review['rating']; ?> ⭐</td>
                <td><?php echo htmlspecialchars($review['review']); ?></td>
                <td><form method="POST" action="delete_review.php" style="display:inline;" onsubmit="return confirm('Delete this review?')">
<?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$review['id']; ?>"><button type="submit" class="red">Delete</button></form></td>
            </tr>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.main-content .table-section table').forEach(function(table){
        if (table.rows.length < 3) return;
        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'admin-table-search';
        search.placeholder = 'Search this table...';
        search.setAttribute('aria-label', 'Search table rows');
        table.parentNode.insertBefore(search, table);
        search.addEventListener('input', function(){
            const q = this.value.toLocaleLowerCase().trim();
            const rows = table.tBodies[0] ? Array.from(table.tBodies[0].rows) : Array.from(table.rows);
            rows.forEach(function(row){
                if (row.querySelector('th')) return;
                row.hidden = q !== '' && !row.textContent.toLocaleLowerCase().includes(q);
            });
        });
    });
});
function toggleMobileMenu() {
    document.getElementById('sideMenu').classList.toggle('open');
}

if(localStorage.getItem("theme") === "dark"){
    document.body.classList.add("dark-mode");
}

function toggleDarkMode(){
    document.body.classList.toggle("dark-mode");
    if(document.body.classList.contains("dark-mode")){
        localStorage.setItem("theme", "dark");
    } else {
        localStorage.setItem("theme", "light");
    }
}
</script>

</body>
</html>
