<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';
include 'maintenance_check.php';

$is_admin = current_user_is_admin($conn);
$settings = get_settings($conn);

// Search functionality (prepared + LIKE wildcards escaped)
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $like  = '%' . like_escape($search) . '%';
    $books = db_query($conn, "SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR category LIKE ? LIMIT 8", 'sss', [$like, $like, $like]);
} else {
    $books = db_query($conn, "SELECT * FROM books LIMIT 8");
}

$recentBooks = mysqli_query($conn, "SELECT * FROM books ORDER BY id DESC LIMIT 4");

$topRated = mysqli_query($conn,
"SELECT books.*, AVG(reviews.rating) AS avg_rating
 FROM books
 LEFT JOIN reviews ON books.id = reviews.book_id
 GROUP BY books.id
 ORDER BY avg_rating DESC
 LIMIT 4");

// data for the on-page chatbot (descriptions trimmed so the page stays small)
$chatRows = db_all($conn, "SELECT title, author, SUBSTRING(description, 1, 300) AS description, category FROM books LIMIT 1000");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($settings['site_name'] ?? 'Library Management System'); ?></title>
<link rel="stylesheet" href="style.css?v=2400">
<style>
    .hero-search {
        margin-top: 25px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .hero-search input {
        padding: 14px 20px;
        border-radius: 12px;
        border: none;
        width: 100%;
        max-width: 420px;
        font-size: 16px;
        background: rgba(255,255,255,0.15);
        color: white;
        backdrop-filter: blur(10px);
    }
    .hero-search input::placeholder {
        color: rgba(255,255,255,0.6);
    }
    .hero-search input:focus {
        outline: none;
        background: rgba(255,255,255,0.25);
    }
    .hero-search button {
        padding: 14px 30px;
        border-radius: 12px;
        border: none;
        background: #ffc72c;
        color: #061b33;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
        transition: 0.3s ease;
    }
    .hero-search button:hover {
        transform: scale(1.05);
    }
    .hero-search .clear-btn {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 14px 20px;
        border-radius: 12px;
        text-decoration: none;
        transition: 0.3s ease;
    }
    .hero-search .clear-btn:hover {
        background: rgba(255,255,255,0.3);
    }
    .section-title {
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 25px;
        border-bottom: 4px solid #ffc72c;
        display: inline-block;
        padding-bottom: 8px;
    }
    .welcome-msg {
        text-align: center;
        padding: 10px 0;
        color: #666;
    }
    .welcome-msg strong {
        color: #0b183d;
    }
    .empty-text {
        text-align: center;
        color: #666;
        padding: 30px 0;
        font-size: 18px;
    }
    .featured-badge {
        background: #ffc72c;
        color: #061b33;
        font-size: 11px;
        padding: 2px 10px;
        border-radius: 20px;
        display: inline-block;
        font-weight: bold;
        margin-top: 5px;
    }
    body.dark-mode .featured-badge {
        color: #061b33;
    }
    body.dark-mode .welcome-msg {
        color: #aaa;
    }
    body.dark-mode .welcome-msg strong {
        color: white;
    }
    body.dark-mode .empty-text {
        color: #aaa;
    }
</style>
</head>
<body>

<nav>
    <div class="logo">📖 <?php echo htmlspecialchars($settings['site_name'] ?? 'Library Management System'); ?></div>
    <ul>
        <li><a class="active" href="index.php">Home</a></li>
        <li><a href="books.php">Books</a></li>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <li><a href="profile.php">👤 Profile</a></li>
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

<!-- Hero Section -->
<section class="hero">
    <div class="hero-text">
        <h1>Welcome To <br><b>Digital Library</b></h1>
        <p>Manage books easily and explore a world of knowledge. Read. Learn. Grow.</p>
        
        <form class="hero-search" method="GET">
            <input type="text" name="search" placeholder="🔍 Search books by title, author or category..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
            <?php if($search != ''): ?>
                <a href="index.php" class="clear-btn">✕ Clear</a>
            <?php endif; ?>
        </form>
        
        <div class="hero-buttons" style="margin-top:20px;">
            <a href="books.php" class="btn">📖 Explore Books</a>
            <?php if(!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="btn-outline">👤 Login Now</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Welcome Message -->
<?php if(isset($_SESSION['fullname'])): ?>
    <div style="text-align:center;padding:15px 0 5px;">
        <p class="welcome-msg">👋 Welcome back, <strong><?php echo htmlspecialchars($_SESSION['fullname']); ?></strong>!</p>
    </div>
<?php endif; ?>

<!-- Features -->
<section class="features">
    <div class="card"><h3>📘 Huge Collection</h3><p>Explore thousands of books across different categories.</p></div>
    <div class="card"><h3>🔍 Easy Search</h3><p>Find books quickly by title, author, or category.</p></div>
    <div class="card"><h3>📥 Easy Download</h3><p>Read books online and download EPUB files.</p></div>
    <div class="card"><h3>🛡 Secure & Reliable</h3><p>Your data is safe with our secure system.</p></div>
</section>

<!-- Featured Books -->
<section class="books">
    <h2 class="section-title"><?php echo ($search != '') ? '🔍 Search Results for "' . htmlspecialchars($search) . '"' : '📚 Featured Books'; ?></h2>
    
    <?php if(mysqli_num_rows($books) > 0): ?>
        <div class="book-container">
            <?php while($row = mysqli_fetch_assoc($books)){ ?>
                <div class="book-card">
                    <img src="<?php echo htmlspecialchars($row['cover_image_url']); ?>" alt="Book Cover">
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p>✍️ <?php echo htmlspecialchars($row['author']); ?></p>
                    <?php if(isset($row['category']) && $row['category'] != ''): ?>
                        <span class="featured-badge">📂 <?php echo htmlspecialchars($row['category']); ?></span>
                    <?php endif; ?>
                    <div style="margin-top:12px;">
                        <a href="read_book.php?id=<?php echo $row['id']; ?>"><button style="margin:3px;">📖 Read</button></a>
                        <a href="books.php"><button style="margin:3px;background:#6c757d;">Details</button></a>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php else: ?>
        <p class="empty-text">❌ No books found matching your search.</p>
    <?php endif; ?>
</section>

<!-- Recently Added -->
<section class="books">
    <h2 class="section-title">🆕 Recently Added Books</h2>
    <div class="book-container">
        <?php while($recent = mysqli_fetch_assoc($recentBooks)){ ?>
            <div class="book-card">
                <img src="<?php echo htmlspecialchars($recent['cover_image_url']); ?>" alt="Book Cover">
                <h3><?php echo htmlspecialchars($recent['title']); ?></h3>
                <p>✍️ <?php echo htmlspecialchars($recent['author']); ?></p>
                <?php if(isset($recent['category']) && $recent['category'] != ''): ?>
                    <span class="featured-badge">📂 <?php echo htmlspecialchars($recent['category']); ?></span>
                <?php endif; ?>
                <div style="margin-top:12px;">
                    <a href="read_book.php?id=<?php echo $recent['id']; ?>"><button style="margin:3px;">📖 Read Now</button></a>
                </div>
            </div>
        <?php } ?>
    </div>
</section>

<!-- Top Rated -->
<section class="books">
    <h2 class="section-title">⭐ Top Rated Books</h2>
    <div class="book-container">
        <?php if(mysqli_num_rows($topRated) > 0){ ?>
            <?php while($top = mysqli_fetch_assoc($topRated)){ ?>
                <div class="book-card">
                    <img src="<?php echo htmlspecialchars($top['cover_image_url']); ?>" alt="Book Cover">
                    <h3><?php echo htmlspecialchars($top['title']); ?></h3>
                    <p>✍️ <?php echo htmlspecialchars($top['author']); ?></p>
                    <p><strong>Rating:</strong> <?php echo round($top['avg_rating'], 1); ?> ⭐</p>
                    <?php if(isset($top['category']) && $top['category'] != ''): ?>
                        <span class="featured-badge">📂 <?php echo htmlspecialchars($top['category']); ?></span>
                    <?php endif; ?>
                    <div style="margin-top:12px;">
                        <a href="read_book.php?id=<?php echo $top['id']; ?>"><button style="margin:3px;">📖 Read</button></a>
                    </div>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p class="empty-text">No rated books yet.</p>
        <?php } ?>
    </div>
</section>

<!-- Footer -->
<footer>
    <p><?php echo htmlspecialchars($settings['footer_text'] ?? '© 2026 Book\'s Library. All Rights Reserved.'); ?></p>
    <p>Developed by Ahmad Raza</p>
</footer>

<!-- Chatbot -->
<div class="chatbot-icon" onclick="toggleChat()">🤖</div>
<div class="chatbot-box" id="chatbot">
    <div class="chat-header">🤖 AI Library Assistant</div>
    <div class="chat-body" id="chat-body">
        <div class="bot-message">Hello 👋 Tell me a book name or category like programming, science, islamic, urdu, novel, history or horror.</div>
    </div>
    <div class="chat-input">
        <input type="text" id="userInput" placeholder="Ask something..." onkeydown="if(event.key === 'Enter'){sendMessage();}">
        <button onclick="sendMessage()">Send</button>
    </div>
</div>

<script>
let bookData = <?php echo json_encode($chatRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>;

function escapeHtml(s){
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function toggleChat(){
    let bot = document.getElementById("chatbot");
    bot.style.display = (bot.style.display === "flex") ? "none" : "flex";
}

function addChatMessage(cssClass, content, asHtml){
    let chatBody = document.getElementById("chat-body");
    let div = document.createElement("div");
    div.className = cssClass;
    if(asHtml){ div.innerHTML = content; } else { div.textContent = content; }   // user text is NEVER parsed as HTML
    chatBody.appendChild(div);
    chatBody.scrollTop = chatBody.scrollHeight;
    return div;
}

function sendMessage(){
    let input = document.getElementById("userInput");
    let message = input.value.toLowerCase().trim();
    if(message === "") return;
    addChatMessage("user-message", input.value, false);
    let typing = addChatMessage("bot-message", "Typing...", false);
    let reply = getBotReply(message);
    setTimeout(() => {
        typing.remove();
        addChatMessage("bot-message", reply, true);   // replies are built from escaped data only
    }, 700);
    input.value = "";
}

function getBotReply(message){
    if(/\b(hello|hi|salam)\b/.test(message)){
        return "👋 Hello! Tell me a book name or category like horror, novel, science, adventure, detective, history, children, fantasy or poetry.";
    }
    if(message.includes("download")){
        return "⬇ Open the Books page and press Download EPUB under any book.";
    }
    if(message.includes("login")){
        return "🔐 Use the Login page to access your account.";
    }
    if(message.includes("register") || message.includes("signup")){
        return "📝 Use the Register page to create a new account.";
    }
    if(message.includes("favorite")){
        return "❤️ Login first, then press Favorite under any book. Favorite books show in your Profile.";
    }
    if(message.includes("review") || message.includes("rating")){
        return "⭐ You can rate books and write reviews on the Books page.";
    }
    if(message.includes("admin")){
        return "🛡 Admin can manage books, users, reviews, requests and analytics from Admin Panel.";
    }
    if(message.includes("dark")){
        return "🌙 Press the Dark button in the navbar to switch theme.";
    }
    let categories = {
        horror: ["horror","vampire","dracula","frankenstein","ghost","gothic","terror","fear","dark","mystery"],
        novel: ["novel","fiction","story","classic","moby","pride","love","family"],
        science: ["science","scientist","physics","chemistry","biology","invisible","time machine"],
        adventure: ["adventure","sea","island","treasure","journey","travel","pirate"],
        detective: ["detective","sherlock","holmes","crime","case","mystery"],
        history: ["history","historical","war","revolution","empire"],
        children: ["children","child","alice","wonderland","animal","family"],
        fantasy: ["fantasy","wonderland","dream","strange","magic"],
        poetry: ["poetry","poem","poems"],
        romance: ["romance","love","marriage"],
        programming: ["programming","coding","computer","assembly","software","java","python","algorithm"]
    };
    for(let cat in categories){
        if(message.includes(cat)){
            return recommendByWords(cat, categories[cat]);
        }
    }
    let words = message.split(" ").filter(w => w.length > 2);
    let matches = bookData.filter(book => {
        let text = (book.title + " " + book.author + " " + book.description + " " + book.category).toLowerCase();
        return words.some(word => text.includes(word));
    });
    if(matches.length > 0){
        return makeReply(matches, "📚 Recommended Books");
    }
    return "😢 No matching books found. Try Dracula, Frankenstein, Sherlock, horror, novel, adventure, detective, science, children or fantasy.";
}

function recommendByWords(category, words){
    let matches = bookData.filter(book => {
        let text = (book.title + " " + book.author + " " + book.description + " " + book.category).toLowerCase();
        return words.some(word => text.includes(word));
    });
    if(matches.length === 0){
        return "😢 No books found for " + category + ". Try another word like novel, adventure, detective, science or children.";
    }
    return makeReply(matches, "📚 Recommended " + category + " books");
}

function makeReply(matches, heading){
    let reply = heading + ":<br><br>";
    matches.slice(0,5).forEach(book => {
        reply += "• " + escapeHtml(book.title) + " - " + escapeHtml(book.author) + "<br>";
    });
    reply += "<br><a href='books.php'>Open Books Page</a>";
    return reply;
}

function toggleDarkMode(){
    document.body.classList.toggle("dark-mode");
    if(document.body.classList.contains("dark-mode")){
        localStorage.setItem("theme", "dark");
    } else {
        localStorage.setItem("theme", "light");
    }
}

if(localStorage.getItem("theme") === "dark"){
    document.body.classList.add("dark-mode");
}
</script>

</body>
</html>