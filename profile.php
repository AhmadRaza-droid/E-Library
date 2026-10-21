<?php
include 'session_timeout.php';
include 'config.php';

require_login();

$user_id = (int)$_SESSION['user_id'];

$user = db_row($conn, "SELECT * FROM users WHERE id=?", 'i', [$user_id]);
if (!$user) { redirect('logout.php'); }
$user_email = $user['email'];

$favorites = db_query($conn,
"SELECT books.*
 FROM favorites
 JOIN books ON favorites.book_id = books.id
 WHERE favorites.user_id=?", 'i', [$user_id]);

$recentlyViewed = db_query($conn,
"SELECT books.*
 FROM recently_viewed
 JOIN books ON recently_viewed.book_id = books.id
 WHERE recently_viewed.user_id=?
 ORDER BY recently_viewed.viewed_at DESC
 LIMIT 5", 'i', [$user_id]);

$myMessages = db_query($conn, "SELECT * FROM messages WHERE email=? ORDER BY id DESC", 's', [$user_email]);

$myRequests = db_query($conn, "SELECT * FROM book_requests WHERE user_id=? ORDER BY id DESC", 'i', [$user_id]);

$notifications = mysqli_query($conn, "SELECT * FROM notifications ORDER BY id DESC LIMIT 5");

$readingProgress = db_query($conn,
"SELECT reading_progress.*, books.title
 FROM reading_progress
 JOIN books ON reading_progress.book_id = books.id
 WHERE reading_progress.user_id=?
 ORDER BY reading_progress.updated_at DESC", 'i', [$user_id]);

$bookmarks = db_query($conn,
"SELECT bookmarks.*, books.title
 FROM bookmarks
 JOIN books ON bookmarks.book_id = books.id
 WHERE bookmarks.user_id=?
 ORDER BY bookmarks.id DESC", 'i', [$user_id]);

$is_admin = ((int)($user['is_admin'] ?? 0) === 1);
$settings = get_settings($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet" href="style.css?v=11000">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            width: 100%;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f6f9;
            overflow-x: hidden;
        }
        
        nav {
            background: #0b1f3a;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        nav .logo {
            font-size: 20px;
            font-weight: bold;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        nav ul li a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            transition: 0.3s;
            font-size: 14px;
        }
        
        nav ul li a:hover,
        nav ul li a.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .dark-btn {
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .dark-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            min-height: calc(100vh - 80px);
        }
        
        .profile-box {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }
        
        .profile-box h2 {
            color: #0b1f3a;
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .profile-pic {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .profile-pic img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #0b1f3a;
        }
        
        .profile-pic .default-icon {
            font-size: 75px;
        }
        
        .profile-info {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .profile-info p {
            font-size: 16px;
            margin: 5px 0;
            color: #333;
        }
        
        .profile-info p b {
            color: #0b1f3a;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 15px 0;
        }
        
        .btn-group button,
        .btn-group a button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #0b1f3a;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1a3a5c;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #ffc72c;
            color: #0b1f3a;
        }
        
        .btn-warning:hover {
            background: #e6b300;
            transform: translateY(-2px);
        }
        
        .profile-heading {
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #0b1f3a;
            color: #0b1f3a;
            font-size: 20px;
        }
        
        .profile-book-card {
            background: #f9f9f9;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #0b1f3a;
            transition: 0.3s;
        }
        
        .profile-book-card:hover {
            transform: translateX(5px);
        }
        
        .profile-book-card h3 {
            color: #0b1f3a;
            font-size: 17px;
            margin-bottom: 5px;
        }
        
        .profile-book-card p {
            color: #555;
            font-size: 14px;
        }
        
        .profile-book-card button {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 8px;
            transition: 0.3s;
        }
        
        .profile-book-card .btn-remove {
            background: #dc3545;
            color: white;
        }
        
        .profile-book-card .btn-remove:hover {
            background: #c82333;
        }
        
        .empty-text {
            text-align: center;
            padding: 30px;
            color: #888;
            font-style: italic;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: #1a1a2e;
        }
        
        body.dark-mode .profile-box {
            background: #16213e;
            color: white;
        }
        
        body.dark-mode .profile-box h2 {
            color: white;
        }
        
        body.dark-mode .profile-info p {
            color: #ccc;
        }
        
        body.dark-mode .profile-info p b {
            color: #ffc72c;
        }
        
        body.dark-mode .profile-heading {
            border-bottom-color: #ffc72c;
            color: white;
        }
        
        body.dark-mode .profile-book-card {
            background: #1a1a3a;
            border-left-color: #ffc72c;
        }
        
        body.dark-mode .profile-book-card h3 {
            color: white;
        }
        
        body.dark-mode .profile-book-card p {
            color: #aaa;
        }
        
        body.dark-mode nav {
            background: #0a0a1a;
        }
        
        body.dark-mode .empty-text {
            color: #666;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 10px;
            }
            .profile-box {
                padding: 20px;
            }
            .profile-box h2 {
                font-size: 22px;
            }
            nav {
                padding: 10px 15px;
            }
            nav .logo {
                font-size: 16px;
            }
            nav ul li a {
                font-size: 12px;
                padding: 5px 10px;
            }
        }
        
        @media (max-width: 480px) {
            .profile-box {
                padding: 15px;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn-group button,
            .btn-group a button {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">📖 <?php echo htmlspecialchars($settings['site_name'] ?? 'Book\'s Library'); ?></div>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="books.php">Books</a></li>
        <li><a class="active" href="profile.php">Profile</a></li>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <li><a href="logout.php">🚪 Logout</a></li>
            
            <?php if($is_admin): ?>
                <li><a href="admin_dashboard.php" style="color:#ffc72c;font-weight:bold;">🛠️ Admin Panel</a></li>
            <?php endif; ?>
            
        <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php">Register</a></li>
        <?php endif; ?>
        
        <li><a href="contact.php">Contact</a></li>
        <li><a href="about.php">About</a></li>
        <li><button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark</button></li>
    </ul>
</nav>

<div class="main-container">
    <div class="profile-box">

        <h2>👤 My Profile</h2>

        <!-- Profile Picture -->
        <div class="profile-pic">
            <?php if(isset($user['profile_image']) && $user['profile_image'] != ""){ ?>
                <img src="<?php echo e($user['profile_image']); ?>" alt="Profile Picture">
            <?php } else { ?>
                <div class="default-icon">👤</div>
            <?php } ?>
        </div>

        <!-- Upload Profile Picture -->
        <form action="upload_profile_image.php" method="POST" enctype="multipart/form-data" style="text-align:center;margin-bottom:20px;">
<?php echo csrf_field(); ?>
            <input type="file" name="profile_image" accept="image/*" required style="display:inline-block;">
            <button type="submit" name="upload_image" class="btn-primary">Upload Profile Picture</button>
        </form>

        <!-- Profile Info -->
        <div class="profile-info">
            <p><b>Name:</b> <?php echo htmlspecialchars($user['fullname']); ?></p>
            <p><b>Email:</b> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><b>Password:</b> ********</p>
        </div>

        <!-- Buttons -->
        <div class="btn-group">
            <a href="change_password.php"><button class="btn-primary">🔑 Change Password</button></a>
            <a href="logout.php"><button class="btn-danger">🚪 Logout</button></a>
        </div>

        <!-- Clear All Activity -->
        <form action="clear_all_history.php" method="POST" onsubmit="return confirm('⚠️ WARNING: This will delete ALL your activity!');">
<?php echo csrf_field(); ?>
            <button type="submit" class="btn-danger" style="width:100%;padding:12px;font-size:16px;border:none;border-radius:8px;cursor:pointer;margin:15px 0;">
                🗑️ Clear All My Activity
            </button>
        </form>

        <!-- Notifications -->
        <h2 class="profile-heading">🔔 Notifications</h2>
        <?php if(mysqli_num_rows($notifications) > 0){ ?>
            <?php while($note = mysqli_fetch_assoc($notifications)){ ?>
                <div class="profile-book-card">
                    <h3><?php echo htmlspecialchars($note['title']); ?></h3>
                    <p><?php echo htmlspecialchars($note['message']); ?></p>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p class="empty-text">No notifications yet.</p>
        <?php } ?>

        <!-- Request a Book -->
        <h2 class="profile-heading">📚 Request a Book</h2>
        <form action="request_book.php" method="POST">
<?php echo csrf_field(); ?>
            <input type="text" name="book_name" placeholder="Enter book name" required style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:6px;">
            <input type="text" name="category" placeholder="Category e.g. Programming, Science" style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:6px;">
            <textarea name="message" placeholder="Write details..." rows="3" style="width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:6px;"></textarea>
            <button type="submit" name="request_book" class="btn-primary" style="width:100%;padding:12px;">Send Request</button>
        </form>

        <!-- Favorite Books -->
        <h2 class="profile-heading">❤️ My Favorite Books</h2>
        <?php if(mysqli_num_rows($favorites) > 0){ ?>
            <?php while($book = mysqli_fetch_assoc($favorites)){ ?>
                <div class="profile-book-card">
                    <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                    <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                    <a href="books.php"><button class="btn-primary">View Book</button></a>
                    <form method="POST" action="remove_favorite.php" style="display:inline;">
<?php echo csrf_field(); ?><input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>"><button type="submit" class="btn-remove">Remove Favorite</button></form>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p class="empty-text">No favorite books yet 😢</p>
        <?php } ?>

        <!-- Recently Viewed -->
        <h2 class="profile-heading">🕒 Recently Viewed Books</h2>
        <?php if(mysqli_num_rows($recentlyViewed) > 0){ ?>
            <?php while($book = mysqli_fetch_assoc($recentlyViewed)){ ?>
                <div class="profile-book-card">
                    <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                    <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                    <a href="read_book.php?id=<?php echo $book['id']; ?>"><button class="btn-primary">Continue Reading</button></a>
                    <form method="POST" action="remove_history.php" style="display:inline;">
<?php echo csrf_field(); ?><input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>"><button type="submit" class="btn-remove">Remove History</button></form>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p class="empty-text">No recently viewed books yet.</p>
        <?php } ?>

        <!-- Reading Progress -->
        <h2 class="profile-heading">📖 Reading Progress</h2>
        <?php if(mysqli_num_rows($readingProgress) > 0){ ?>
            <?php while($progress = mysqli_fetch_assoc($readingProgress)){ ?>
                <div class="profile-book-card">
                    <h3><?php echo htmlspecialchars($progress['title']); ?></h3>
                    <p><strong>Progress:</strong> <?php echo (int)$progress['progress']; ?>%</p>
                    <div style="width:100%;background:#e0e0e0;height:8px;border-radius:10px;margin:8px 0;">
                        <div style="width:<?php echo (int)$progress['progress']; ?>%;background:#28a745;height:8px;border-radius:10px;"></div>
                    </div>
                    <a href="read_book.php?id=<?php echo $progress['book_id']; ?>"><button class="btn-primary">Continue Book</button></a>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p class="empty-text">No reading progress yet.</p>
        <?php } ?>

        <!-- Bookmarks -->
        <h2 class="profile-heading">🔖 My Bookmarks</h2>
        <?php if(mysqli_num_rows($bookmarks) > 0){ ?>
            <?php while($bm = mysqli_fetch_assoc($bookmarks)){ ?>
                <div class="profile-book-card">
                    <h3><?php echo htmlspecialchars($bm['title']); ?></h3>
                    <p><?php echo htmlspecialchars($bm['note']); ?></p>
                    <a href="read_book.php?id=<?php echo $bm['book_id']; ?>"><button class="btn-primary">Open Book</button></a>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p class="empty-text">No bookmarks yet.</p>
        <?php } ?>

        <!-- My Book Requests -->
        <h2 class="profile-heading">📚 My Book Requests</h2>
        <?php if(mysqli_num_rows($myRequests) > 0){ ?>
            <?php while($req = mysqli_fetch_assoc($myRequests)){ ?>
                <div class="profile-book-card">
                    <h3><?php echo htmlspecialchars($req['book_name']); ?></h3>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($req['category']); ?></p>
                    <p><strong>Message:</strong> <?php echo htmlspecialchars($req['message']); ?></p>
                    <p><strong>Status:</strong> <span style="color:#28a745;font-weight:bold;"><?php echo htmlspecialchars($req['status']); ?></span></p>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p class="empty-text">No book requests yet.</p>
        <?php } ?>

        <!-- Messages -->
        <h2 class="profile-heading">📩 My Messages & Admin Replies</h2>
        <?php if(mysqli_num_rows($myMessages) > 0){ ?>
            <?php while($msg = mysqli_fetch_assoc($myMessages)){ ?>
                <div class="profile-book-card">
                    <p><strong>Your Message:</strong></p>
                    <p><?php echo htmlspecialchars($msg['message']); ?></p>
                    <p><strong>Admin Reply:</strong></p>
                    <?php if(isset($msg['reply']) && $msg['reply'] != ""){ ?>
                        <p><?php echo htmlspecialchars($msg['reply']); ?></p>
                    <?php } else { ?>
                        <p>No reply yet.</p>
                    <?php } ?>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p class="empty-text">No messages sent yet.</p>
        <?php } ?>

    </div>
</div>

<script>
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