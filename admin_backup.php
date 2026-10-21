<?php
include 'session_timeout.php';
include 'config.php';

require_admin($conn);

// ---------- helpers ----------
function split_sql_statements($sql){
    $stmts = []; $buf = ''; $len = strlen($sql);
    $inStr = false; $quote = ''; $inLineComment = false;
    for($i = 0; $i < $len; $i++){
        $c = $sql[$i];
        if($inLineComment){
            if($c === "\n"){ $inLineComment = false; }
            continue;
        }
        if($inStr){
            $buf .= $c;
            if($c === '\\' && $quote !== '`' && $i + 1 < $len){ $buf .= $sql[++$i]; continue; }
            if($c === $quote){
                if($i + 1 < $len && $sql[$i + 1] === $quote){ $buf .= $sql[++$i]; continue; } // '' escape
                $inStr = false;
            }
            continue;
        }
        if(($c === '-' && substr($sql, $i, 3) === '-- ') || $c === '#'){ $inLineComment = true; continue; }
        if($c === "'" || $c === '"' || $c === '`'){ $inStr = true; $quote = $c; $buf .= $c; continue; }
        if($c === ';'){
            if(trim($buf) !== ''){ $stmts[] = trim($buf); }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if(trim($buf) !== ''){ $stmts[] = trim($buf); }
    return $stmts;
}

// ---------- Create Backup (read-only download) ----------
if(isset($_GET['action']) && $_GET['action'] == 'backup'){
    $tables = [];
    $result = mysqli_query($conn, "SHOW TABLES");
    while($row = mysqli_fetch_row($result)){
        $tables[] = $row[0];
    }

    $output  = "-- Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach($tables as $table){
        $t = str_replace('`', '``', $table);
        $output .= "DROP TABLE IF EXISTS `$t`;\n";
        $create = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE `$t`"));
        $output .= $create[1] . ";\n\n";

        $rows = mysqli_query($conn, "SELECT * FROM `$t`");
        while($row = mysqli_fetch_assoc($rows)){
            $values = [];
            foreach($row as $val){
                $values[] = $val === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $val) . "'";
            }
            $output .= "INSERT INTO `$t` VALUES (" . implode(',', $values) . ");\n";
        }
        $output .= "\n";
    }
    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
    header('Cache-Control: no-store');
    echo $output;
    exit();
}

// ---------- Restore Backup (POST + CSRF + only statements a backup file can contain) ----------
if(isset($_POST['restore'])){
    $file = $_FILES['backup_file']['tmp_name'] ?? '';
    $ok_upload = $file !== '' && is_uploaded_file($file)
              && ($_FILES['backup_file']['size'] ?? 0) > 0
              && ($_FILES['backup_file']['size'] ?? 0) <= 20 * 1024 * 1024;
    if(!$ok_upload){
        js_alert_redirect('❌ Error: please choose a valid .sql backup (max 20 MB).', 'admin_backup.php');
    }

    $stmts = split_sql_statements((string)file_get_contents($file));
    $allowed = '/^(DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO|SET FOREIGN_KEY_CHECKS)\b/i';
    foreach($stmts as $s){
        if(!preg_match($allowed, $s)){
            js_alert_redirect('❌ Restore refused: the file contains statements that a backup should not have.', 'admin_backup.php');
        }
    }
    $failed = 0;
    foreach($stmts as $s){
        if(!mysqli_query($conn, $s)){ $failed++; error_log('Restore failed: ' . mysqli_error($conn)); }
    }
    js_alert_redirect($failed ? "⚠️ Restore finished with $failed failed statement(s). Check error_log.txt." : '✅ Database restored successfully!', 'admin_backup.php');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Backup & Restore</title>
    <link rel="stylesheet" href="style.css?v=8000">
    <style>
        .backup-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .backup-box { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 20px; text-align: center; }
        .backup-box h2 { margin-bottom: 15px; color: #0b1f3a; }
        .backup-box p { color: #666; margin-bottom: 20px; }
        .backup-box button { padding: 12px 30px; font-size: 16px; }
        .dark-mode .backup-box { background: #16213e; color: white; }
        .dark-mode .backup-box h2 { color: white; }
        .dark-mode .backup-box p { color: #aaa; }
        .backup-box input[type="file"] { margin: 15px auto; display: block; }
    </style>
</head>
<body>

<nav>
    <div class="logo">💾 Backup & Restore</div>
    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="admin_backup.php" class="active">Backup</a></li>
        <li><a href="admin_logout.php">Logout</a></li>
        <li><button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark</button></li>
    </ul>
</nav>

<section class="page-header">
    <h1>💾 Backup & Restore</h1>
    <p>Create database backup or restore from backup file</p>
</section>

<div class="backup-container">
    <!-- Backup -->
    <div class="backup-box">
        <h2>📤 Create Backup</h2>
        <p>Download a complete SQL backup of your database</p>
        <a href="?action=backup">
            <button class="green">📥 Download Backup</button>
        </a>
    </div>

    <!-- Restore -->
    <div class="backup-box">
        <h2>📥 Restore Backup</h2>
        <p>Upload a SQL file to restore your database</p>
        <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('This will overwrite existing data. Continue?');">
<?php echo csrf_field(); ?>
            <input type="file" name="backup_file" accept=".sql" required>
            <button type="submit" name="restore" class="green">🔄 Restore Database</button>
        </form>
        <p style="font-size:12px;color:#dc3545;margin-top:10px;">⚠️ Warning: This will overwrite all existing data!</p>
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