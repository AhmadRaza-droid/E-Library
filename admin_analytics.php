<?php
include 'session_timeout.php';
include 'config.php';

require_admin($conn);

// Get stats
$total_users     = (int)db_value($conn, "SELECT COUNT(*) FROM users", '', [], 0);
$total_books     = (int)db_value($conn, "SELECT COUNT(*) FROM books", '', [], 0);
$total_downloads = (int)db_value($conn, "SELECT COUNT(*) FROM downloads", '', [], 0);
$total_reviews   = (int)db_value($conn, "SELECT COUNT(*) FROM reviews", '', [], 0);

// Most read books
$most_read = mysqli_query($conn,
"SELECT books.title, COUNT(reading_history.id) AS read_count 
 FROM reading_history 
 JOIN books ON reading_history.book_id = books.id 
 GROUP BY books.id 
 ORDER BY read_count DESC 
 LIMIT 5");

// Most downloaded books
$most_downloaded = mysqli_query($conn,
"SELECT books.title, COUNT(downloads.id) AS download_count 
 FROM downloads 
 JOIN books ON downloads.book_id = books.id 
 GROUP BY books.id 
 ORDER BY download_count DESC 
 LIMIT 5");

// Top users
$top_users = mysqli_query($conn,
"SELECT users.fullname, COUNT(reading_history.id) AS read_count 
 FROM reading_history 
 JOIN users ON reading_history.user_id = users.id 
 GROUP BY users.id 
 ORDER BY read_count DESC 
 LIMIT 5");

// Category stats
$category_stats = mysqli_query($conn,
"SELECT category, COUNT(*) AS count 
 FROM books 
 WHERE category IS NOT NULL AND category != '' 
 GROUP BY category 
 ORDER BY count DESC");

// Reading progress stats
$total_reading = (int)db_value($conn, "SELECT COUNT(*) FROM reading_progress", '', [], 0);
$avg_progress = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(progress) AS avg FROM reading_progress"));
$avg_progress_value = round($avg_progress['avg'] ?? 0, 1);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Analytics</title>
    <link rel="stylesheet" href="style.css?v=8000">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .analytics-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            transition: 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .icon { font-size: 35px; margin-bottom: 10px; }
        .stat-card h3 { color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .number { font-size: 34px; font-weight: bold; color: #0b1f3a; margin: 5px 0; }
        .stat-card .label { color: #888; font-size: 13px; }
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .chart-card h3 { margin-bottom: 20px; color: #0b1f3a; font-size: 20px; }
        .bar-container { display: flex; flex-direction: column; gap: 12px; }
        .bar-item {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .bar-item .label { width: 140px; font-size: 14px; color: #333; word-break: break-word; }
        .bar-item .bar-bg {
            flex: 1;
            height: 22px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            min-width: 100px;
        }
        .bar-item .bar-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease;
        }
        .bar-item .value {
            min-width: 40px;
            font-size: 14px;
            font-weight: bold;
            color: #0b1f3a;
            text-align: right;
        }
        .color-blue { background: linear-gradient(90deg, #0b1f3a, #1a3a5c); }
        .color-green { background: linear-gradient(90deg, #28a745, #218838); }
        .color-yellow { background: linear-gradient(90deg, #ffc72c, #ff9800); }
        .color-purple { background: linear-gradient(90deg, #6f42c1, #8b5cf6); }
        .dark-mode .stat-card { background: #16213e; color: white; }
        .dark-mode .stat-card h3 { color: #aaa; }
        .dark-mode .stat-card .number { color: white; }
        .dark-mode .stat-card .label { color: #888; }
        .dark-mode .chart-card { background: #16213e; color: white; }
        .dark-mode .chart-card h3 { color: white; }
        .dark-mode .bar-item .label { color: #ddd; }
        .dark-mode .bar-item .bar-bg { background: #2a2a4e; }
        .dark-mode .bar-item .value { color: white; }
        .nav-link {
            background: rgba(255,255,255,0.1);
            padding: 8px 16px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
        }
        .nav-link:hover { background: rgba(255,255,255,0.2); }
        .nav-link.active { background: #ffc72c; color: #0b1f3a; }
        @media (max-width: 768px) {
            .bar-item .label { width: 100px; font-size: 12px; }
            .stat-card .number { font-size: 28px; }
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">📊 Admin Analytics</div>
    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="admin_analytics.php" class="active">Analytics</a></li>
        <li><a href="admin_logout.php">Logout</a></li>
        <li><button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark</button></li>
    </ul>
</nav>

<section class="page-header">
    <h1>📊 Analytics Dashboard</h1>
    <p>User activity, book popularity, and system insights</p>
</section>

<div class="analytics-container">

    <!-- Stats Cards -->
    <div class="analytics-grid">
        <div class="stat-card">
            <div class="icon">👥</div>
            <h3>Total Users</h3>
            <div class="number"><?php echo $total_users; ?></div>
            <div class="label">Registered Users</div>
        </div>
        <div class="stat-card">
            <div class="icon">📚</div>
            <h3>Total Books</h3>
            <div class="number"><?php echo $total_books; ?></div>
            <div class="label">In Library</div>
        </div>
        <div class="stat-card">
            <div class="icon">📥</div>
            <h3>Total Downloads</h3>
            <div class="number"><?php echo $total_downloads; ?></div>
            <div class="label">Books Downloaded</div>
        </div>
        <div class="stat-card">
            <div class="icon">⭐</div>
            <h3>Total Reviews</h3>
            <div class="number"><?php echo $total_reviews; ?></div>
            <div class="label">Reviews Given</div>
        </div>
        <div class="stat-card">
            <div class="icon">📖</div>
            <h3>Reading Progress</h3>
            <div class="number"><?php echo $total_reading; ?></div>
            <div class="label">Avg Progress: <?php echo $avg_progress_value; ?>%</div>
        </div>
    </div>

    <!-- Most Read Books -->
    <div class="chart-card">
        <h3>📖 Most Read Books</h3>
        <div class="bar-container">
            <?php 
            $max_read = 1;
            $read_data = [];
            while($row = mysqli_fetch_assoc($most_read)){
                $read_data[] = $row;
                if($row['read_count'] > $max_read) $max_read = $row['read_count'];
            }
            if(!empty($read_data)):
            foreach($read_data as $row):
                $percent = ($row['read_count'] / $max_read) * 100;
            ?>
            <div class="bar-item">
                <span class="label"><?php echo htmlspecialchars($row['title']); ?></span>
                <div class="bar-bg">
                    <div class="bar-fill color-blue" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <span class="value"><?php echo $row['read_count']; ?></span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p>No reading data yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Most Downloaded Books -->
    <div class="chart-card">
        <h3>📥 Most Downloaded Books</h3>
        <div class="bar-container">
            <?php 
            $max_download = 1;
            $download_data = [];
            while($row = mysqli_fetch_assoc($most_downloaded)){
                $download_data[] = $row;
                if($row['download_count'] > $max_download) $max_download = $row['download_count'];
            }
            if(!empty($download_data)):
            foreach($download_data as $row):
                $percent = ($row['download_count'] / $max_download) * 100;
            ?>
            <div class="bar-item">
                <span class="label"><?php echo htmlspecialchars($row['title']); ?></span>
                <div class="bar-bg">
                    <div class="bar-fill color-green" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <span class="value"><?php echo $row['download_count']; ?></span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p>No download data yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Active Users -->
    <div class="chart-card">
        <h3>🏆 Top Active Users</h3>
        <div class="bar-container">
            <?php 
            $max_activity = 1;
            $user_data = [];
            while($row = mysqli_fetch_assoc($top_users)){
                $user_data[] = $row;
                if($row['read_count'] > $max_activity) $max_activity = $row['read_count'];
            }
            if(!empty($user_data)):
            foreach($user_data as $row):
                $percent = ($row['read_count'] / $max_activity) * 100;
            ?>
            <div class="bar-item">
                <span class="label"><?php echo htmlspecialchars($row['fullname']); ?></span>
                <div class="bar-bg">
                    <div class="bar-fill color-yellow" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <span class="value"><?php echo $row['read_count']; ?></span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p>No user activity yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Stats -->
    <div class="chart-card">
        <h3>📂 Book Categories</h3>
        <div class="bar-container">
            <?php 
            $max_category = 1;
            $category_data = [];
            while($row = mysqli_fetch_assoc($category_stats)){
                $category_data[] = $row;
                if($row['count'] > $max_category) $max_category = $row['count'];
            }
            if(!empty($category_data)):
            foreach($category_data as $row):
                $percent = ($row['count'] / $max_category) * 100;
            ?>
            <div class="bar-item">
                <span class="label"><?php echo htmlspecialchars($row['category']); ?></span>
                <div class="bar-bg">
                    <div class="bar-fill color-purple" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <span class="value"><?php echo $row['count']; ?></span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p>No categories yet.</p>
            <?php endif; ?>
        </div>
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