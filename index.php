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
$heroBooks = db_all($conn, "SELECT title, author FROM books ORDER BY id DESC LIMIT 3");
$subjectRows = db_all($conn, "SELECT category, COUNT(*) AS total FROM books WHERE category IS NOT NULL AND TRIM(category) <> '' GROUP BY category ORDER BY total DESC, category LIMIT 6");
$chatRows = db_all($conn, "SELECT id, title, author, SUBSTRING(description, 1, 300) AS description, category FROM books ORDER BY id DESC LIMIT 500");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($settings['site_name'] ?? 'Library Management System'); ?></title>
<link rel="stylesheet" href="style.css?v=2405">
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
<?php echo notification_markup(); ?>

<?php render_site_nav($conn, basename(__FILE__)); ?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-text">
        <p class="hero-eyebrow">WELCOME TO <?php echo e(site_name($conn)); ?></p><h1>Every book is a new beginning.</h1>
        <p>Explore books, read online and discover something new, wherever you are.</p>
        
        <form class="hero-search" method="GET">
            <input type="text" name="search" placeholder="Search title, author or subject" aria-label="Search books" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
            <?php if($search != ''): ?>
                <a href="index.php" class="clear-btn">✕ Clear</a>
            <?php endif; ?>
        </form>
        
        <div class="hero-buttons" style="margin-top:20px;">
            <a href="books.php" class="btn">📖 Explore Books</a>
            <?php if(empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])): ?>
                <a href="login.php" class="btn-outline">👤 Login Now</a>
            <?php endif; ?>
        </div>
        <button type="button" class="chatbot-icon" onclick="toggleChat()" aria-label="Open library assistant" aria-expanded="false" aria-controls="chatbot">🤖</button>
    </div>
    <div class="hero-stack" aria-hidden="true">
        <?php foreach ($heroBooks as $book): ?>
        <div class="hero-cover"><b><?php echo e($book['title']); ?></b><small><?php echo e($book['author']); ?></small></div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Welcome Message -->
<?php if(isset($_SESSION['fullname'])): ?>
    <div style="text-align:center;padding:15px 0 5px;">
        <p class="welcome-msg">👋 Welcome back, <strong><?php echo htmlspecialchars($_SESSION['fullname']); ?></strong>!</p>
    </div>
<?php endif; ?>

<!-- Browse real library categories -->
<section class="features-wrap">
    <h2>Browse by subject</h2>
    <div class="features">
        <?php if ($subjectRows): foreach ($subjectRows as $subject): ?>
        <a class="card" href="books.php?category=<?php echo urlencode($subject['category']); ?>">
            <h3><?php echo e($subject['category']); ?></h3><p><?php echo (int)$subject['total']; ?> books</p>
        </a>
        <?php endforeach; else: ?>
        <a class="card" href="books.php"><h3>All books</h3><p>Browse the library</p></a>
        <?php endif; ?>
    </div>
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
    <p><?php echo e(site_footer($conn)); ?></p>
    <p>Developed by Ahmad Raza</p>
</footer>

<!-- Library assistant -->

<div class="chatbot-box" id="chatbot">
    <div class="chat-header"><span>🤖 Library Assistant</span><button type="button" class="chat-close" onclick="toggleChat()" aria-label="Close library assistant">×</button></div>
    <div class="chat-body" id="chat-body" role="log" aria-live="polite" aria-relevant="additions">
        <div class="bot-message">Hello 👋 Main Book’s Library Assistant hoon. Books, account, reading aur downloads ke bare mein poochhein. Aap Roman Urdu mein bhi sawal kar sakte hain.</div>
        <div class="chat-suggestions" aria-label="Suggested questions">
            <button type="button" onclick="askLibraryQuestion('How do I reset my password?')">Reset password</button>
            <button type="button" onclick="askLibraryQuestion('PDF download nahi ho rahi')">Download help</button>
            <button type="button" onclick="askLibraryQuestion('Show me science books')">Find books</button>
        </div>
    </div>
    <form class="chat-input" onsubmit="sendMessage(event)">
        <input type="text" id="userInput" aria-label="Ask the library assistant" placeholder="Ask about books..." maxlength="500" autocomplete="off">
        <button type="submit">Send</button>
    </form>
</div>

<script>
const bookData = <?php echo json_encode($chatRows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>;

function toggleChat(){
    const bot = document.getElementById('chatbot');
    const toggle = document.querySelector('.chatbot-icon');
    const open = bot.style.display !== 'flex';
    bot.style.display = open ? 'flex' : 'none';
    document.body.classList.toggle('chat-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close library assistant' : 'Open library assistant');
    if (open) document.getElementById('userInput').focus();
    else toggle.focus();
}

function addChatMessage(cssClass, content){
    const chatBody = document.getElementById('chat-body');
    const div = document.createElement('div');
    div.className = cssClass;
    div.textContent = content;
    chatBody.appendChild(div);
    chatBody.scrollTop = chatBody.scrollHeight;
    return div;
}

function sendMessage(event){
    if (event) event.preventDefault();
    const input = document.getElementById('userInput');
    const message = input.value.trim();
    if (!message) return;
    addChatMessage('user-message', message);
    input.value = '';
    try {
        const reply = getBotReply(message);
        const div = addChatMessage('bot-message', reply.text);
        if (reply.books) {
            reply.books.forEach(book => {
                const line = document.createElement('div');
                const link = document.createElement('a');
                link.href = 'read_book.php?id=' + encodeURIComponent(String(book.id));
                link.textContent = String(book.title || 'Untitled');
                line.append('📖 ', link, ' — ' + String(book.author || 'Unknown author'));
                div.appendChild(line);
            });
            const more = document.createElement('a');
            more.href = 'books.php';
            more.textContent = 'Browse all books';
            div.appendChild(more);
        }
        div.parentElement.scrollTop = div.parentElement.scrollHeight;
    } catch (error) {
        addChatMessage('bot-message', 'Sorry, I could not answer that. Please try again or visit the Books page.');
    }
}

function askLibraryQuestion(question){
    const input = document.getElementById('userInput');
    input.value = question;
    sendMessage();
}

function getBotReply(message){
    const raw = String(message || '').trim();
    const text = raw.toLocaleLowerCase().replace(/[’‘]/g, "'").replace(/\s+/g, ' ');
    if (!text) return {text: 'Please type a question. Aap kitab ka naam, author ya apna sawal likhein.'};

    const has = pattern => pattern.test(text);
    // Focused support knowledge for this library website only.
    const faqs = [
        {test:/\b(hi|hello|hey|salam|assalam ?o ?alaikum|assalamu ?alaikum|aoa)\b/,answer:'👋 Wa Alaikum Assalam! Main Book’s Library Assistant hoon. Books, accounts, reading, downloads, bookmarks, reviews aur website features ke bare mein pooch sakte hain.'},
        {test:/\b(about (the )?(website|library)|website kya|library kya|what is this website)\b/,answer:'📚 Book’s Library ek digital library hai. Yahan books search aur categories se browse, online read, available files download, favorites/bookmarks save aur book requests submit kar sakte hain.'},
        {test:/\b(search|find|look for|talash|dhoond|dhund|book name)\b/,answer:'🔎 Book ka title, author ya subject likhein. Main library catalogue mein matching books dhoond kar Read links dikha sakta hoon. Books page ka search/filter bhi use karein.'},
        {test:/\b(download|epub|pdf|download nahi)\b/,answer:'⬇ Books page par selected book ka Download button use karein. Uploaded PDF direct download hoti hai; catalogue books EPUB link de sakti hain. Button unavailable ho ya download fail ho to us book ki file/link filhaal available nahi ho sakti. Contact page par title ke sath report karein.'},
        {test:/\b(read|reading|parhna|parhun|parhney|online book|reader|open book)\b/,answer:'📖 Books page par book dhoond kar Read Book dabayein. Reader mein reading progress save karne ka option ho sakta hai. Reader na khule to book ka title Contact page par report karein.'},
        {test:/\b(login|log in|sign in|password|forgot password)\b/,answer:'🔐 Login page par registered email aur password enter karein. Password bhool gaye hain to Forgot Password use karein, email inbox aur Spam/Junk folder check karein.'},
        {test:/\b(register|registration|sign ?up|account banao|new account)\b/,answer:'📝 Register page par name, email aur password fill karein. Registration ke baad email OTP verify karein; phir Login page se sign in karein.'},
        {test:/\b(otp|verification|verify email|email verify|code nahi)\b/,answer:'📨 Registration ya password reset ka OTP email inbox aur Spam/Junk folder mein check karein. OTP na aaye ya expire ho to Resend OTP option use karein.'},
        {test:/\b(profile|my account|account details|meri profile|mera account)\b/,answer:'👤 Login ke baad Profile page open karein. Wahan account details aur available reading activity, favorites/bookmarks aur progress manage kar sakte hain.'},
        {test:/\b(progress|continue reading|kahan se parh|last page|reading history|recently viewed|history)\b/,answer:'📚 Login ke baad reader mein reading progress save karein. Profile page par available progress/recently viewed activity se continue kar sakte hain.'},
        {test:/\b(favou?rite|favourite|pasandida|bookmark|save book|saved book)\b/,answer:'❤️ Login karein, phir book ke Favorite ya Bookmark option se save karein. Apni saved books Profile page par check karein.'},
        {test:/\b(review|rating|rate|stars|feedback)\b/,answer:'⭐ Book listing par rating select karein aur review likh kar Submit Review dabayein. Review dene ke liye login zaroori hai.'},
        {test:/\b(request|mangwa|mangwana|book chahiye|suggest a book|new book)\b/,answer:'📩 Jo book library mein nahi mil rahi, us ke liye Book Request option/page par title aur category submit karein. Admin request review karega; addition guaranteed nahi hoti.'},
        {test:/\b(category|categories|subject|subjects|kis qisam|genre)\b/,answer:'🗂 Books page par category filter use karein, ya home page ke Browse by Subject section se available categories explore karein.'},
        {test:/\b(contact|rabta|message|support|help|complain|report problem)\b/,answer:'✉️ Website team se rabta karne ke liye Contact page open karein aur apna naam, email aur issue detail submit karein. Book issue report karte waqt title bhi likhein.'},
        {test:/\b(admin|administrator|admin panel)\b/,answer:'🛡 Admin Panel sirf authorized admin account ke liye hai. Admin books, users, categories, requests, reviews, messages, notifications aur student materials manage kar sakta hai.'},
        {test:/\b(student material|notes|lecture|assignment|upload material|resource)\b/,answer:'📑 Student Materials section mein available resources browse karein. Upload option enabled ho to material submit karein; admin approval ke baad publish ho sakta hai.'},
        {test:/\b(dark mode|light mode|theme|night mode)\b/,answer:'🌙 Navigation bar ka Dark/Light control use karke website theme change karein. Preference browser mein save hoti hai.'},
        {test:/\b(mobile|phone|android|responsive)\b/,answer:'📱 Website mobile browser par bhi use ki ja sakti hai. Menu button se navigation open karein. Updated Chrome browser use karna behtar hai.'},
        {test:/\b(privacy|data|account delete|delete account|security)\b/,answer:'🔒 Apna password kisi ke sath share na karein. Account ya personal-data request ke liye Contact page se library team ko message karein.'},
        {test:/\b(thank|thanks|shukriya|jazak)\b/,answer:'😊 Khushi hui ke main madad kar saka! Library ke kisi feature ya book ke bare mein aur sawal pooch lein.'}
    ];
    for (const item of faqs) if (item.test.test(text)) return {text: item.answer};

    const categories = {
        horror: ['horror','vampire','dracula','frankenstein','ghost','gothic'],
        novel: ['novel','fiction'], science: ['science','scientist','physics','chemistry','biology'],
        adventure: ['adventure','treasure','pirate'], detective: ['detective','sherlock','holmes','crime','mystery'],
        history: ['history','historical'], children: ['children','child','kids'],
        fantasy: ['fantasy','magic'], poetry: ['poetry','poem','poems'],
        romance: ['romance','love story'], programming: ['programming','coding','computer','software','python','java'],
        islamic: ['islamic','islam'], urdu: ['urdu']
    };
    const stop = new Set(['the','and','for','with','about','tell','what','which','who','can','you','please','find','show','some','give','book','books','author','writer','mujhe','mujhay','koi','kisi','batao','batain','bata','hai','hain','kya','wala','wali','chahiye','par','aur','kay','mein','mere','mera','kiye','ki','ka','ke','ko','se','say','karo','chahye','kitab','kitaben','kitaab','suggest','recommend','recommendation','dikhao','do','hain']);
    const words = (text.match(/[\p{L}\p{N}]+/gu) || []).filter(word => word.length > 1 && !stop.has(word));
    const wantsBooks = /\b(book|books|kitab|kitaben|kitaab|recommend|suggest|novel)\b/.test(text);
    if (wantsBooks && words.length === 0) {
        return bookData.length ? {text: '📚 Library mein ye books available hain:', books: bookData.slice(0, 5)} : {text: 'Abhi library mein books available nahi hain.'};
    }

    const selectedCategory = Object.keys(categories).find(key =>
        categories[key].some(term => text.includes(term)) || text.includes(key)
    );
    const searchTerms = selectedCategory ? categories[selectedCategory] : words;
    const scored = bookData.map(book => {
        const title = String(book.title || '').toLocaleLowerCase();
        const author = String(book.author || '').toLocaleLowerCase();
        const category = String(book.category || '').toLocaleLowerCase();
        const description = String(book.description || '').toLocaleLowerCase();
        let score = 0;
        if (title && text.includes(title)) score += 20;
        if (author && text.includes(author)) score += 14;
        for (const term of searchTerms) {
            if (term.length < 2) continue;
            if (title.includes(term)) score += 7;
            if (author.includes(term)) score += 5;
            if (category.includes(term)) score += 4;
            if (description.includes(term)) score += 1;
        }
        if (selectedCategory && (category.includes(selectedCategory) || categories[selectedCategory].some(t => category.includes(t)))) score += 6;
        return {book, score};
    }).filter(item => item.score > 0).sort((a, b) => b.score - a.score || String(a.book.title).localeCompare(String(b.book.title)));

    if (!scored.length) {
        const websiteTerms = /\b(book|library|website|account|login|register|password|download|read|reader|pdf|epub|profile|bookmark|favorite|review|rating|request|category|admin|student|material|otp|email|contact|progress|history|kitab|parhna|masla|problem)\b/;
        if (websiteTerms.test(text)) return {text:'🤝 Main Book’s Library website ke features aur catalogue ke bare mein guide karta hoon. Apna sawal thora detail se likhein, misal: “password reset kaise karun?” ya “PDF download nahi ho rahi”. Book ka exact title/author bhi likh sakte hain.'};
        return {text:'Main Book’s Library ka website assistant hoon, general-purpose ChatGPT nahi. Main sirf is library ke books, authors, categories, accounts, reading, downloads, bookmarks, reviews, requests aur website support ke sawalat mein madad karta hoon.'};
    }
    const results = scored.slice(0, 5).map(item => item.book);
    const exact = scored[0].score >= 20;
    const intro = selectedCategory ? '📚 ' + selectedCategory.charAt(0).toUpperCase() + selectedCategory.slice(1) + ' category se matching books:' :
        (exact ? '📖 Aapki search se ye book mili:' : '📚 Aapke sawal se milti-julti books:');
    return {text: intro, books: results};
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