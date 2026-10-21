<?php
include 'session_timeout.php';
include 'config.php';
include 'maintenance_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('books.php'); }

$book = db_row($conn, "SELECT * FROM books WHERE id=?", 'i', [$id]);
if (!$book) { redirect('books.php'); }

$readUrl = resolve_read_link($book['read_link']);   // only http(s) / our own uploads
$current_progress = 0;

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];

    // recently viewed (no duplicates)
    db_exec($conn, "DELETE FROM recently_viewed WHERE user_id=? AND book_id=?", 'ii', [$user_id, $id]);
    db_exec($conn, "INSERT INTO recently_viewed (user_id, book_id) VALUES (?, ?)", 'ii', [$user_id, $id]);

    $current_progress = (int)db_value($conn, "SELECT progress FROM reading_progress WHERE user_id=? AND book_id=?", 'ii', [$user_id, $id], 0);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($book['title']); ?></title>
    <link rel="stylesheet" href="style.css?v=10000">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .reader-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .reader-header {
            background: var(--card-bg, white);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .reader-header h1 {
            color: var(--text, #0b1f3a);
            font-size: 28px;
        }
        .reader-header .meta {
            color: var(--text-light, #666);
            font-size: 16px;
        }
        .reader-iframe {
            width: 100%;
            height: 750px;
            border: 2px solid #061b33;
            border-radius: 12px;
            background: white;
        }
        .progress-section {
            background: var(--card-bg, white);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin: 20px 0;
        }
        .progress-section h2 {
            color: var(--text, #0b1f3a);
            margin-bottom: 15px;
        }
        .progress-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .progress-controls select,
        .progress-controls input {
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            background: var(--card-bg, white);
            color: var(--text, #333);
        }
        .progress-controls select:focus,
        .progress-controls input:focus {
            outline: none;
            border-color: #0b1f3a;
        }
        .progress-controls button {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s ease;
        }
        .btn-save {
            background: #28a745;
            color: white;
        }
        .btn-save:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .btn-download {
            background: #ffc72c;
            color: #0b1f3a;
        }
        .btn-download:hover {
            background: #e6b300;
            transform: translateY(-2px);
        }
        .btn-back {
            background: #0b1f3a;
            color: white;
        }
        .btn-back:hover {
            background: #1a3a5c;
            transform: translateY(-2px);
        }
        .bookmark-section {
            background: var(--card-bg, white);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin: 20px 0;
        }
        .bookmark-section textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            resize: vertical;
            min-height: 100px;
            background: var(--card-bg, white);
            color: var(--text, #333);
        }
        .bookmark-section textarea:focus {
            outline: none;
            border-color: #0b1f3a;
        }
        .dark-mode .reader-iframe {
            background: #1a1a2e;
        }
        .dark-mode .progress-controls select,
        .dark-mode .progress-controls input {
            background: #1a1a3a;
            color: white;
            border-color: #333;
        }
        .dark-mode .bookmark-section textarea {
            background: #1a1a3a;
            color: white;
            border-color: #333;
        }
        @media (max-width: 768px) {
            .reader-header {
                flex-direction: column;
                text-align: center;
            }
            .reader-header h1 { font-size: 22px; }
            .reader-iframe { height: 500px; }
            .progress-controls { flex-direction: column; }
            .progress-controls select,
            .progress-controls input,
            .progress-controls button { width: 100%; }
        }
        @media (max-width: 480px) {
            .reader-iframe { height: 400px; }
            .reader-container { padding: 10px; }
            .reader-header { padding: 15px; }
            .progress-section { padding: 15px; }
            .bookmark-section { padding: 15px; }
        }
    </style>
</head>

<body>

<nav>
    <div class="logo">📖 Reading Book</div>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="books.php">Books</a></li>
        <li><a href="profile.php">Profile</a></li>
        <li><button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark</button></li>
    </ul>
</nav>

<div class="reader-container">

    <!-- Header -->
    <div class="reader-header">
        <div>
            <h1><?php echo htmlspecialchars($book['title']); ?></h1>
            <div class="meta">✍️ <?php echo htmlspecialchars($book['author']); ?></div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="books.php" class="btn-back" style="text-decoration:none;padding:10px 20px;border-radius:8px;">📚 Books</a>
            <?php if(!empty($book['download_epub_link'])): ?>
                <a href="download_book.php?id=<?php echo $id; ?>" style="text-decoration:none;">
                    <button class="btn-download">📥 Download EPUB</button>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Book Content (IFRAME) -->
    <?php if($readUrl !== ''): ?>
    <iframe
        class="reader-iframe"
        src="<?php echo e($readUrl); ?>"
        sandbox="allow-scripts allow-same-origin allow-popups allow-forms"
        referrerpolicy="no-referrer"
        loading="lazy">
    </iframe>
    <?php else: ?>
        <p style="padding:30px;text-align:center;">This book has no valid reading link.</p>
    <?php endif; ?>

    <!-- Progress Section (Only for logged-in users) -->
    <?php if(isset($_SESSION['user_id'])): ?>
    <div class="progress-section">
        <h2>📖 Reading Progress</h2>
        
        <div class="progress-controls">
            <input type="hidden" id="book_id" value="<?php echo $id; ?>">
            
            <select id="progress_select" style="flex:1;min-width:150px;">
                <option value="0" <?php echo $current_progress == 0 ? 'selected' : ''; ?>>0%</option>
                <option value="10" <?php echo $current_progress == 10 ? 'selected' : ''; ?>>10%</option>
                <option value="20" <?php echo $current_progress == 20 ? 'selected' : ''; ?>>20%</option>
                <option value="30" <?php echo $current_progress == 30 ? 'selected' : ''; ?>>30%</option>
                <option value="40" <?php echo $current_progress == 40 ? 'selected' : ''; ?>>40%</option>
                <option value="50" <?php echo $current_progress == 50 ? 'selected' : ''; ?>>50%</option>
                <option value="60" <?php echo $current_progress == 60 ? 'selected' : ''; ?>>60%</option>
                <option value="70" <?php echo $current_progress == 70 ? 'selected' : ''; ?>>70%</option>
                <option value="80" <?php echo $current_progress == 80 ? 'selected' : ''; ?>>80%</option>
                <option value="90" <?php echo $current_progress == 90 ? 'selected' : ''; ?>>90%</option>
                <option value="100" <?php echo $current_progress == 100 ? 'selected' : ''; ?>>100%</option>
            </select>
            
            <button class="btn-save" onclick="saveProgress()">💾 Save Progress</button>
            
            <?php if($current_progress > 0): ?>
                <span style="color:#28a745;font-weight:bold;">Current: <?php echo $current_progress; ?>%</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bookmark Section -->
    <div class="bookmark-section">
        <h2>🔖 Save Bookmark</h2>
        
        <form action="add_bookmark.php" method="POST">
<?php echo csrf_field(); ?>
            <input type="hidden" name="book_id" value="<?php echo $id; ?>">
            <input type="hidden" name="page" id="page_input" value="0">
            
            <textarea 
                name="note" 
                placeholder="Example: Resume from Chapter 5, page 45..."
                maxlength="1000"
                required></textarea>
            
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:15px;">
                <button type="submit" style="padding:12px 30px;background:#0b1f3a;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:bold;">
                    💾 Save Bookmark
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>

<script>

function saveProgress(){
    let book_id = document.getElementById('book_id').value;
    let progress = document.getElementById('progress_select').value;
    
    fetch('update_progress.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': <?php echo json_encode(csrf_token()); ?>
        },
        credentials: 'same-origin',
        body: 'book_id=' + encodeURIComponent(book_id) + '&progress=' + encodeURIComponent(progress)
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success'){
            alert('✅ Progress saved: ' + progress + '%');
            location.reload();
        } else {
            alert('❌ Error saving progress: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error: ' + error);
    });
}

// Dark Mode
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

// Auto-save progress on select change
document.addEventListener('DOMContentLoaded', function() {
    let select = document.getElementById('progress_select');
    if(select){
        select.addEventListener('change', function() {
            saveProgress();
        });
    }
});
</script>

</body>
</html>