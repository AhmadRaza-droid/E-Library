<?php
include 'session_timeout.php';
include 'config.php';

require_admin($conn);

$allBooks = [

["title"=>"The Republic","author"=>"Plato","category"=>"philosophy history education politics","description"=>"A famous philosophical work by Plato.","cover"=>"https://www.gutenberg.org/cache/epub/1497/pg1497.cover.medium.jpg","read"=>"1497","download"=>"1497"],

["title"=>"The Time Machine","author"=>"H. G. Wells","category"=>"science fiction technology adventure","description"=>"A science fiction novel about time travel.","cover"=>"https://www.gutenberg.org/cache/epub/35/pg35.cover.medium.jpg","read"=>"35","download"=>"35"],

["title"=>"Dracula","author"=>"Bram Stoker","category"=>"horror gothic novel fiction","description"=>"A horror novel about Dracula.","cover"=>"https://www.gutenberg.org/cache/epub/345/pg345.cover.medium.jpg","read"=>"345","download"=>"345"],

["title"=>"The Adventures of Sherlock Holmes","author"=>"Arthur Conan Doyle","category"=>"detective mystery crime novel","description"=>"Detective stories featuring Sherlock Holmes.","cover"=>"https://www.gutenberg.org/cache/epub/1661/pg1661.cover.medium.jpg","read"=>"1661","download"=>"1661"],

["title"=>"Pride and Prejudice","author"=>"Jane Austen","category"=>"romance novel fiction classic","description"=>"A classic novel about love, society, and manners.","cover"=>"https://www.gutenberg.org/cache/epub/1342/pg1342.cover.medium.jpg","read"=>"1342","download"=>"1342"],

["title"=>"Moby Dick","author"=>"Herman Melville","category"=>"adventure sea novel classic","description"=>"A classic adventure novel about Captain Ahab and the white whale.","cover"=>"https://www.gutenberg.org/cache/epub/2701/pg2701.cover.medium.jpg","read"=>"2701","download"=>"2701"],

["title"=>"Alice's Adventures in Wonderland","author"=>"Lewis Carroll","category"=>"children fantasy story adventure","description"=>"A fantasy story about Alice and Wonderland.","cover"=>"https://www.gutenberg.org/cache/epub/11/pg11.cover.medium.jpg","read"=>"11","download"=>"11"],

["title"=>"A Tale of Two Cities","author"=>"Charles Dickens","category"=>"history novel revolution classic","description"=>"A historical novel set in London and Paris.","cover"=>"https://www.gutenberg.org/cache/epub/98/pg98.cover.medium.jpg","read"=>"98","download"=>"98"],

["title"=>"The War of the Worlds","author"=>"H. G. Wells","category"=>"science fiction alien movie adventure","description"=>"A science fiction novel about alien invasion.","cover"=>"https://www.gutenberg.org/cache/epub/36/pg36.cover.medium.jpg","read"=>"36","download"=>"36"],

["title"=>"The Jungle Book","author"=>"Rudyard Kipling","category"=>"children adventure jungle story","description"=>"A collection of adventure stories about animals and jungle life.","cover"=>"https://www.gutenberg.org/cache/epub/236/pg236.cover.medium.jpg","read"=>"236","download"=>"236"],

["title"=>"Frankenstein","author"=>"Mary Shelley","category"=>"science fiction horror novel","description"=>"A gothic science fiction novel about creation and responsibility.","cover"=>"https://www.gutenberg.org/cache/epub/84/pg84.cover.medium.jpg","read"=>"84","download"=>"84"],

["title"=>"The Art of War","author"=>"Sun Tzu","category"=>"war strategy business history","description"=>"A classic book about strategy and leadership.","cover"=>"https://www.gutenberg.org/cache/epub/132/pg132.cover.medium.jpg","read"=>"132","download"=>"132"],

["title"=>"Think and Grow Rich","author"=>"Napoleon Hill","category"=>"business finance money success selfhelp","description"=>"A motivational book about success and personal achievement.","cover"=>"https://www.gutenberg.org/cache/epub/610/pg610.cover.medium.jpg","read"=>"610","download"=>"610"],

["title"=>"The Prophet","author"=>"Kahlil Gibran","category"=>"poetry philosophy literature","description"=>"A poetic and philosophical book by Kahlil Gibran.","cover"=>"https://www.gutenberg.org/cache/epub/58585/pg58585.cover.medium.jpg","read"=>"58585","download"=>"58585"],



];

require_once __DIR__ . '/book_import.php';

if (isset($_POST['scrape'])) {
    $keyword = trim((string)($_POST['keyword'] ?? ''));
    $quantity = clamp_int($_POST['quantity'] ?? 5, 1, 20);
    $source = $_POST['source'] ?? 'starter';
    if (mb_strlen($keyword) > 100 || !in_array($source, ['starter', 'online'], true)) {
        js_alert_redirect('Enter a search of up to 100 characters and select a valid source.', 'auto_scrape_books.php');
    }
    $counts = ['imported' => 0, 'duplicate' => 0, 'failed' => 0];
    try {
        $pages = $source === 'online' ? 2 : 1;
        for ($page = 1; $page <= $pages && $counts['imported'] < $quantity; $page++) {
            if ($source === 'online') {
                $response = fetch_import_catalog($keyword, $page);
                $candidates = array_filter(array_map('normalize_import_book', $response['results']));
            } else {
                $words = preg_split('/\s+/u', mb_strtolower($keyword), -1, PREG_SPLIT_NO_EMPTY);
                $candidates = array_filter($allBooks, function($book) use ($words){
                    $text = mb_strtolower(implode(' ', [$book['title'], $book['author'], $book['category'], $book['description']]));
                    foreach ($words as $word) if (mb_strpos($text, $word) !== false) return true;
                    return !$words;
                });
            }
            foreach ($candidates as $book) {
                if ($counts['imported'] >= $quantity) break;
                $counts[import_catalog_book($conn, $book)]++;
            }
            if ($source !== 'online' || empty($response['next'])) break;
        }
    } catch (RuntimeException $error) {
        js_alert_redirect($counts['imported'] . ' books imported. ' . $error->getMessage(), 'auto_scrape_books.php');
    }
    $message = $counts['imported'] > 0 ? $counts['imported'] . ' books imported successfully.' : 'No new matching books found.';
    $message .= ' ' . $counts['duplicate'] . ' duplicates skipped. ' . $counts['failed'] . ' failed.';
    if ($counts['imported'] === 0 && $counts['duplicate'] > 0) $message .= ' Matching books are already in your library. Try Online search for new books.';
    js_alert_redirect($message, 'auto_scrape_books.php');
}

?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Import Books</title>
    <link rel="stylesheet" href="style.css?v=2402">
</head>

<body class="auth-page">
<?php echo notification_markup(); ?>

<nav>
    <div class="logo">📖 Auto Import Books</div>
    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="admin_logout.php">Logout</a></li>
    </ul>
</nav>

<section class="page-header">
    <h1>Auto Import Books</h1>
    <p>Starter list contains Project Gutenberg classics with online reading and EPUB links. Online search finds more public-domain titles.</p>
    <p>Starter categories: science fiction, adventure, history, horror, detective, classic literature, philosophy, poetry.</p>
</section>

<section class="form-section">
<div class="form-box">

<h2>Import Books</h2>

<form method="POST">
<?php echo csrf_field(); ?>

<label for="import-source">Book source</label>
<select id="import-source" name="source"><option value="starter">Starter list (offline)</option><option value="online">Online search (Gutendex)</option></select>

<input type="text" maxlength="100" name="keyword" placeholder="Enter category/title e.g. science, programming, movie">

<input type="number" name="quantity" placeholder="How many books?" value="5" min="1" max="20" required>

<button type="submit" name="scrape">Import Books</button>

</form>

</div>
</section>

</body>
</html>
