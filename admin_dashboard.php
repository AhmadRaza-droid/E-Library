<?php
include 'session_timeout.php';
include 'config.php';
require_once __DIR__ . '/mailer.php';

require_admin($conn);
$me = (int)$_SESSION['admin_id'];

$notice = null;   // ['message', 'redirect']
function done($msg, $page){ js_alert_redirect($msg, 'admin_dashboard.php?page=' . $page); }

// ========== ALL STATE-CHANGING ACTIONS ARE POST + CSRF (checked globally in config.php) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- DELETE BOOK ----
    if (isset($_POST['delete_book'])) {
        $id = (int)$_POST['delete_book'];
        foreach (['favorites', 'reviews', 'recently_viewed', 'bookmarks', 'reading_progress'] as $t) {
            db_exec($conn, "DELETE FROM `$t` WHERE book_id=?", 'i', [$id]);
        }
        db_exec($conn, "DELETE FROM books WHERE id=?", 'i', [$id]);
        done('Book deleted successfully!', 'all_books');
    }

    // ---- DELETE USER (not yourself) ----
    if (isset($_POST['delete_user'])) {
        $id = (int)$_POST['delete_user'];
        if ($id === $me) { done('❌ You cannot delete your own account.', 'all_users'); }
        foreach (['favorites', 'recently_viewed', 'reviews', 'bookmarks', 'reading_progress', 'book_requests'] as $t) {
            db_exec($conn, "DELETE FROM `$t` WHERE user_id=?", 'i', [$id]);
        }
        db_exec($conn, "DELETE FROM users WHERE id=?", 'i', [$id]);
        done('User deleted successfully!', 'all_users');
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
        if ($category !== '' && mb_strlen($category) <= 100) {
            db_exec($conn, "INSERT INTO categories (name) VALUES (?)", 's', [$category]);
        }
        done('✅ Category added!', 'categories');
    }
    if (isset($_POST['delete_category'])) {
        db_exec($conn, "DELETE FROM categories WHERE id=?", 'i', [(int)$_POST['delete_category']]);
        done('Category deleted!', 'categories');
    }

    // ---- ADMIN RIGHTS ----
    if (isset($_POST['make_admin'])) {
        db_exec($conn, "UPDATE users SET is_admin=1 WHERE id=?", 'i', [(int)$_POST['make_admin']]);
        done('User is now Admin!', 'all_users');
    }
    if (isset($_POST['remove_admin'])) {
        $id = (int)$_POST['remove_admin'];
        $admins = (int)db_value($conn, "SELECT COUNT(*) FROM users WHERE is_admin=1", '', [], 0);
        if ($id === $me || $admins <= 1) { done('❌ You cannot remove the last / your own admin rights.', 'all_users'); }
        db_exec($conn, "UPDATE users SET is_admin=0 WHERE id=?", 'i', [$id]);
        done('Admin rights removed!', 'all_users');
    }

    // ---- MESSAGES ----
    if (isset($_POST['delete_message'])) {
        db_exec($conn, "DELETE FROM messages WHERE id=?", 'i', [(int)$_POST['delete_message']]);
        done('Message deleted successfully!', 'messages');
    }
    if (isset($_POST['reply_submit'])) {
        $message_id = (int)($_POST['message_id'] ?? 0);
        $reply      = trim($_POST['reply'] ?? '');
        db_exec($conn, "UPDATE messages SET reply=? WHERE id=?", 'si', [$reply, $message_id]);

        $msg = db_row($conn, "SELECT * FROM messages WHERE id=?", 'i', [$message_id]);
        if ($msg && $reply !== '' && validate_email($msg['email'])) {
            $html = "<div style='font-family: Arial; padding: 15px;'>
                        <h3>Reply from Admin</h3>
                        <p><strong>Your message:</strong> " . nl2br(e($msg['message'])) . "</p>
                        <p><strong>Admin Reply:</strong> " . nl2br(e($reply)) . "</p>
                        <hr><small>Book's Library Team</small></div>";
            send_mail($msg['email'], $msg['name'], 'Reply to your message', $html);
        }
        done('Reply sent successfully!', 'messages');
    }

    // ---- NOTIFICATIONS (saved + e-mailed over ONE smtp connection) ----
    if (isset($_POST['send_notification'])) {
        $title        = trim($_POST['title'] ?? '');
        $message_body = trim($_POST['message'] ?? '');
        if ($title === '' || $message_body === '') { done('❌ Title and message are required.', 'notifications'); }

        db_exec($conn, "INSERT INTO notifications (title, message, created_at) VALUES (?, ?, NOW())", 'ss', [$title, $message_body]);

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

$most_downloaded = mysqli_query($conn, "SELECT title, download_count FROM books ORDER BY download_count DESC LIMIT 5");
$most_favorites  = mysqli_query($conn,
"SELECT books.title, COUNT(favorites.id) AS total
 FROM favorites
 JOIN books ON favorites.book_id = books.id
 GROUP BY books.id
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
    </style>
</head>
<body>

<div class="hamburger-mobile" onclick="toggleMobileMenu()">☰</div>

<div class="side-menu" id="sideMenu">
    <div class="logo">📖 Admin Panel</div>
    <a href="?page=dashboard" class="<?php echo $page == 'dashboard' ? 'active' : ''; ?>">🏠 Dashboard</a>
    <a href="?page=website" class="<?php echo $page == 'website' ? 'active' : ''; ?>">🌐 Website</a>
    <a href="?page=all_books" class="<?php echo $page == 'all_books' ? 'active' : ''; ?>">📚 All Books</a>
    <a href="?page=add_book" class="<?php echo $page == 'add_book' ? 'active' : ''; ?>">➕ Add Book</a>
    <a href="?page=categories" class="<?php echo $page == 'categories' ? 'active' : ''; ?>">📂 Categories</a>
    <a href="?page=all_users" class="<?php echo $page == 'all_users' ? 'active' : ''; ?>">👥 All Users</a>
    <a href="?page=messages" class="<?php echo $page == 'messages' ? 'active' : ''; ?>">💬 Messages</a>
    <a href="?page=notifications" class="<?php echo $page == 'notifications' ? 'active' : ''; ?>">🔔 Notifications</a>
    <a href="?page=auto_import" class="<?php echo $page == 'auto_import' ? 'active' : ''; ?>">📥 Auto Import</a>
    <a href="?page=book_requests" class="<?php echo $page == 'book_requests' ? 'active' : ''; ?>">📝 Book Requests</a>
    <a href="?page=reviews" class="<?php echo $page == 'reviews' ? 'active' : ''; ?>">⭐ Reviews</a>
    <a href="admin_analytics.php">📊 Analytics</a>
    <a href="admin_settings.php">⚙️ Settings</a>
<a href="admin_backup.php">💾 Backup</a>
<a href="performance.php">🚀 Performance</a>
    <a href="admin_logout.php">🚪 Logout</a>
</div>

<div class="main-content">
    <div class="top-bar">
        <h2><?php echo ucfirst(str_replace('_', ' ', $page)); ?></h2>
        <button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark Mode</button>
    </div>

    <!-- ========== DASHBOARD PAGE ========== -->
    <?php if($page == 'dashboard'): ?>
    <div class="stats-container">
        <div class="stat-card"><h2>Total Users</h2><h1><?php echo $total_users; ?></h1></div>
        <div class="stat-card"><h2>Total Books</h2><h1><?php echo $total_books; ?></h1></div>
        <div class="stat-card"><h2>Total Categories</h2><h1><?php echo $total_categories; ?></h1></div>
        <div class="stat-card"><h2>Total Reviews</h2><h1><?php echo $total_reviews; ?></h1></div>
        <div class="stat-card"><h2>Total Downloads</h2><h1><?php echo $total_downloads; ?></h1></div>
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
            <input type="text" name="read_link" placeholder="Read Link">
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
                <td><img src="<?php echo htmlspecialchars($row['cover_image_url']); ?>" width="50" style="border-radius:5px;"></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['author']); ?></td>
                <td><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                <td><?php echo $row['download_count'] ?? 0; ?></td>
                <td><form method="POST" action="?page=all_books" style="display:inline;" onsubmit="return confirm('Delete this book?')">
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
            <form method="POST" action="">
<?php echo csrf_field(); ?>
                <input type="hidden" name="message_id" value="<?php echo $row['id']; ?>">
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['message']); ?></td>
                    <td width="250"><input type="text" name="reply" value="<?php echo htmlspecialchars($row['reply'] ?? ''); ?>" placeholder="Type reply..." style="width:100%"></td>
                    <td class="action-buttons">
                        <button type="submit" name="reply_submit" class="green">Reply</button>
                        <button type="submit" name="delete_message" value="<?php echo (int)$row['id']; ?>" class="red" formnovalidate onclick="return confirm('Delete this message?')">Delete</button>
                    </td>
                </tr>
            </form>
            <?php } ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ========== NOTIFICATIONS PAGE ========== -->
    <?php if($page == 'notifications'): ?>
    <div class="table-section">
        <h2>🔔 Send Notification</h2>
        <p style="color: #28a745; margin-bottom: 15px;">✅ Notification sent via EMAIL + saved to database!</p>
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
            <form method="POST" action="update_request_status.php">
<?php echo csrf_field(); ?>
                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                <tr>
                    <td><?php echo $req['id']; ?></td>
                    <td><?php echo htmlspecialchars($req['fullname']); ?></td>
                    <td><?php echo htmlspecialchars($req['email']); ?></td>
                    <td><?php echo htmlspecialchars($req['book_name']); ?></td>
                    <td><?php echo htmlspecialchars($req['category']); ?></td>
                    <td>
                        <select name="status">
                            <option <?php echo $req['status']=='Pending'?'selected':''; ?>>Pending</option>
                            <option <?php echo $req['status']=='Approved'?'selected':''; ?>>Approved</option>
                            <option <?php echo $req['status']=='Added'?'selected':''; ?>>Added</option>
                            <option <?php echo $req['status']=='Rejected'?'selected':''; ?>>Rejected</option>
                        </select>
                    </td>
                    <td><button type="submit" name="update_status">Update</button></td>
                </tr>
            </form>
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