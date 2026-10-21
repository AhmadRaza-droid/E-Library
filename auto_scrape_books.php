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

["title"=>"Python Programming Basics","author"=>"Library Team","category"=>"programming coding computer python technology","description"=>"A beginner friendly programming book for learning Python concepts.","cover"=>"https://upload.wikimedia.org/wikipedia/commons/c/c3/Python-logo-notext.svg","read"=>"https://docs.python.org/3/tutorial/","download"=>"https://docs.python.org/3/tutorial/"],

["title"=>"Web Development Guide","author"=>"Library Team","category"=>"programming web development html css javascript","description"=>"A beginner guide for learning web development basics.","cover"=>"https://upload.wikimedia.org/wikipedia/commons/6/61/HTML5_logo_and_wordmark.svg","read"=>"https://developer.mozilla.org/en-US/docs/Learn","download"=>"https://developer.mozilla.org/en-US/docs/Learn"],

["title"=>"Computer Networking Notes","author"=>"Library Team","category"=>"networking computer technology engineering","description"=>"Basic notes about computer networking concepts.","cover"=>"https://upload.wikimedia.org/wikipedia/commons/d/d2/Internet_map_1024.jpg","read"=>"https://www.geeksforgeeks.org/computer-network-tutorials/","download"=>"https://www.geeksforgeeks.org/computer-network-tutorials/"],
    [
"title"=>"Harry Potter and the Philosopher's Stone",
"author"=>"J. K. Rowling",
"category"=>"fantasy adventure movie novel",
"description"=>"First book of the Harry Potter series.",
"cover"=>"https://covers.openlibrary.org/b/isbn/9780747532699-L.jpg",
"read"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Philosopher%27s+Stone",
"download"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Philosopher%27s+Stone"
],

[
"title"=>"Harry Potter and the Chamber of Secrets",
"author"=>"J. K. Rowling",
"category"=>"fantasy adventure movie novel",
"description"=>"Second book of the Harry Potter series.",
"cover"=>"https://covers.openlibrary.org/b/isbn/9780747538493-L.jpg",
"read"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Chamber+of+Secrets",
"download"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Chamber+of+Secrets"
],

[
"title"=>"Harry Potter and the Prisoner of Azkaban",
"author"=>"J. K. Rowling",
"category"=>"fantasy adventure movie novel",
"description"=>"Third book of the Harry Potter series.",
"cover"=>"https://covers.openlibrary.org/b/isbn/9780747542155-L.jpg",
"read"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Prisoner+of+Azkaban",
"download"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Prisoner+of+Azkaban"
],

[
"title"=>"Harry Potter and the Goblet of Fire",
"author"=>"J. K. Rowling",
"category"=>"fantasy adventure movie novel",
"description"=>"Fourth book of the Harry Potter series.",
"cover"=>"https://covers.openlibrary.org/b/isbn/9780747546245-L.jpg",
"read"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Goblet+of+Fire",
"download"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Goblet+of+Fire"
],

[
"title"=>"Harry Potter and the Order of the Phoenix",
"author"=>"J. K. Rowling",
"category"=>"fantasy adventure movie novel",
"description"=>"Fifth book of the Harry Potter series.",
"cover"=>"https://covers.openlibrary.org/b/isbn/9780747551003-L.jpg",
"read"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Order+of+the+Phoenix",
"download"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Order+of+the+Phoenix"
],

[
"title"=>"Harry Potter and the Half-Blood Prince",
"author"=>"J. K. Rowling",
"category"=>"fantasy adventure movie novel",
"description"=>"Sixth book of the Harry Potter series.",
"cover"=>"https://covers.openlibrary.org/b/isbn/9780747581086-L.jpg",
"read"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Half-Blood+Prince",
"download"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Half-Blood+Prince"
],

[
"title"=>"Harry Potter and the Deathly Hallows",
"author"=>"J. K. Rowling",
"category"=>"fantasy adventure movie novel",
"description"=>"Seventh book of the Harry Potter series.",
"cover"=>"https://covers.openlibrary.org/b/isbn/9780747591054-L.jpg",
"read"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Deathly+Hallows",
"download"=>"https://openlibrary.org/search?q=Harry+Potter+and+the+Deathly+Hallows"
],

["title"=>"Islamic Studies Introduction","author"=>"Library Team","category"=>"islamic religion quran hadith urdu","description"=>"An introductory book for Islamic studies and basic concepts.","cover"=>"https://images.unsplash.com/photo-1542816417-0983670d7f62?q=80&w=800&auto=format&fit=crop","read"=>"https://quran.com/","download"=>"https://quran.com/"]

];

function addBook($conn, $book){

    if(is_numeric($book['read'])){
        $id = (int)$book['read'];
        $read_link     = "https://www.gutenberg.org/ebooks/$id";
        $download_link = "https://www.gutenberg.org/ebooks/$id.epub.images";
    } else {
        $read_link     = $book['read'];
        $download_link = $book['download'];
    }

    if(db_row($conn, "SELECT id FROM books WHERE title=? AND author=?", 'ss', [$book['title'], $book['author']])){
        return false;   // duplicate
    }

    $ok = db_exec($conn,
        "INSERT INTO books (title, author, category, description, cover_image_url, read_link, download_epub_link, source)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'Local Import')",
        'sssssss', [$book['title'], $book['author'], $book['category'], $book['description'], $book['cover'], $read_link, $download_link]);

    return $ok !== false;
}

if(isset($_POST['scrape'])){

    $keyword  = strtolower(trim($_POST['keyword'] ?? ''));
    $quantity = clamp_int($_POST['quantity'] ?? 5, 1, 20);

    $count = 0;
    $keywords = explode(" ", $keyword);

    foreach($allBooks as $book){

        if($count >= $quantity){
            break;
        }

        $searchText = strtolower($book['title']." ".$book['author']." ".$book['category']." ".$book['description']);
        $matched = empty($keyword);

        foreach($keywords as $word){
            $word = trim($word);
            if($word != "" && strpos($searchText, $word) !== false){
                $matched = true;
                break;
            }
        }

        if($matched && addBook($conn, $book)){
            $count++;
        }
    }

    if($count == 0){
        foreach($allBooks as $book){
            if($count >= $quantity){
                break;
            }

            if(addBook($conn, $book)){
                $count++;
            }
        }
    }

    js_alert_redirect("$count books imported successfully. Duplicate books skipped.", 'admin_dashboard.php');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Auto Import Books</title>
    <link rel="stylesheet" href="style.css?v=800">
</head>

<body>

<nav>
    <div class="logo">📖 Auto Import Books</div>
    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="admin_logout.php">Logout</a></li>
    </ul>
</nav>

<section class="page-header">
    <h1>Auto Import Books</h1>
    <p>Search by category: programming, science, adventure, movie, history, islamic, urdu, business, horror, detective, novel, philosophy.</p>
</section>

<section class="form-section">
<div class="form-box">

<h2>Import Books</h2>

<form method="POST">
<?php echo csrf_field(); ?>

<input type="text" name="keyword" placeholder="Enter category/title e.g. science, programming, movie">

<input type="number" name="quantity" placeholder="How many books?" min="1" max="20" required>

<button type="submit" name="scrape">Import Books</button>

</form>

</div>
</section>

</body>
</html>