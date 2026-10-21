<?php
include 'session_timeout.php';
include 'config.php';

require_admin($conn);

// Optimize tables
if(isset($_POST['optimize'])){
    $result = mysqli_query($conn, "SHOW TABLES");
    while($row = mysqli_fetch_row($result)){
        $t = str_replace('`', '``', $row[0]);
        mysqli_query($conn, "OPTIMIZE TABLE `$t`");
    }
    echo "<script>alert('✅ All tables optimized!');</script>";
}

// Clear cache = temporary rate-limit files (the old code destroyed the ADMIN'S OWN session)
if(isset($_POST['clear_cache'])){
    foreach(glob(sys_get_temp_dir() . '/bl_rl_*') ?: [] as $f){ @unlink($f); }
    if(function_exists('opcache_reset')){ @opcache_reset(); }
    echo "<script>alert('✅ Cache cleared!');</script>";
}

// Get table stats
$table_stats = [];
$result = mysqli_query($conn, "SHOW TABLE STATUS");
while($row = mysqli_fetch_assoc($result)){
    $table_stats[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Performance</title>
    <link rel="stylesheet" href="style.css?v=8000">
    <style>
        .perf-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .perf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .perf-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); text-align: center; }
        .perf-card h3 { color: #0b1f3a; margin-bottom: 10px; }
        .perf-card p { color: #666; }
        .dark-mode .perf-card { background: #16213e; color: white; }
        .dark-mode .perf-card h3 { color: white; }
        .dark-mode .perf-card p { color: #aaa; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #0b1f3a; color: white; }
        .dark-mode td { border-color: #333; }
    </style>
</head>
<body>

<nav>
    <div class="logo">🚀 Performance</div>
    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="performance.php" class="active">Performance</a></li>
        <li><a href="admin_logout.php">Logout</a></li>
        <li><button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark</button></li>
    </ul>
</nav>

<section class="page-header">
    <h1>🚀 Performance Optimization</h1>
    <p>Optimize your database and clear cache</p>
</section>

<div class="perf-container">
    <div class="perf-grid">
        <div class="perf-card">
            <h3>🗄️ Optimize Database</h3>
            <p>Optimize all tables for better performance</p>
            <form method="POST">
<?php echo csrf_field(); ?>
                <button type="submit" name="optimize" class="green">🔄 Optimize Now</button>
            </form>
        </div>
        <div class="perf-card">
            <h3>🗑️ Clear Cache</h3>
            <p>Clear session cache and temporary data</p>
            <form method="POST">
<?php echo csrf_field(); ?>
                <button type="submit" name="clear_cache" class="green">🗑️ Clear Cache</button>
            </form>
        </div>
    </div>

    <!-- Table Stats -->
    <div style="background:white;padding:20px;border-radius:16px;box-shadow:0 5px 20px rgba(0,0,0,0.08);overflow-x:auto;">
        <h3 style="margin-bottom:15px;color:#0b1f3a;">📊 Table Statistics</h3>
        <table>
            <tr>
                <th>Table</th>
                <th>Rows</th>
                <th>Size</th>
                <th>Created</th>
            </tr>
            <?php foreach($table_stats as $stat){ ?>
            <tr>
                <td><?php echo e($stat['Name']); ?></td>
                <td><?php echo (int)$stat['Rows']; ?></td>
                <td><?php echo $stat['Data_length'] > 0 ? round($stat['Data_length'] / 1024, 2) . ' KB' : '0 KB'; ?></td>
                <td><?php echo $stat['Create_time'] ?? 'N/A'; ?></td>
            </tr>
            <?php } ?>
        </table>
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