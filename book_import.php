<?php
// Functions only; the caller enforces admin access and CSRF.
function import_catalog_book($conn, $book){
    $read = resolve_read_link($book['read']);
    $download = resolve_download_link($book['download']);
    if ($read === '') return 'failed';
    $legacy = ctype_digit((string)$book['read']) ? 'https://www.gutenberg.org/ebooks/' . $book['read'] : $read;
    $duplicateResult = db_query($conn, 'SELECT id FROM books WHERE (title=? AND author=?) OR read_link IN (?, ?, ?) LIMIT 1', 'sssss',
        [$book['title'], $book['author'], $read, (string)$book['read'], $legacy]);
    if ($duplicateResult === false) return 'failed';
    if ($duplicateResult instanceof mysqli_result) {
        $duplicate = $duplicateResult->fetch_assoc();
        $duplicateResult->free();
        if ($duplicate) return 'duplicate';
    }
    $ok = db_exec($conn,
        "INSERT INTO books (title, author, category, description, cover_image_url, read_link, download_epub_link, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'ssssssss', [$book['title'], $book['author'], $book['category'], $book['description'], safe_url($book['cover']), $read, $download, $book['source'] ?? 'Local Import']);
    return $ok === 1 ? 'imported' : 'failed';
}

function fetch_import_catalog($keyword, $page = 1){
    if (!function_exists('curl_init')) throw new RuntimeException('Online import requires the PHP cURL extension. Use the starter list or add a book manually.');
    $url = 'https://gutendex.com/books/?' . http_build_query(['search' => $keyword, 'page' => $page, 'copyright' => 'false']);
    $curl = curl_init($url);
    $body = '';
    curl_setopt_array($curl, [CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'E-Library book importer', CURLOPT_WRITEFUNCTION => function($handle, $chunk) use (&$body){
            if (strlen($body) + strlen($chunk) > 2000000) return 0;
            $body .= $chunk;
            return strlen($chunk);
        }]);
    $ok = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $data = json_decode($body, true);
    if ($ok === false || $status !== 200 || !is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
        throw new RuntimeException('The online book provider is unavailable or blocked this request. Please try later, use the starter list, or add a book manually.');
    }
    return $data;
}

function normalize_import_book($row){
    if (!is_array($row) || !isset($row['id'], $row['title']) || !is_numeric($row['id']) || (int)$row['id'] <= 0 || !is_string($row['title']) || trim($row['title']) === '' || ($row['copyright'] ?? true) !== false) return null;
    $formats = $row['formats'] ?? [];
    if (!is_array($formats) || !is_array($row['authors'] ?? null) || !is_array($row['subjects'] ?? null)) return null;
    // Prefer the formatted HTML reader, but accept Gutenberg plain text when
    // a title has no HTML edition. Never import a search page as a book file.
    $reader = '';
    foreach (['text/html; charset=utf-8', 'text/html', 'text/plain; charset=utf-8', 'text/plain'] as $mime) {
        $url = $formats[$mime] ?? '';
        if (is_string($url) && safe_url($url) !== '') { $reader = $url; break; }
    }
    if ($reader === '') {
        foreach ($formats as $mime => $url) {
            if (str_starts_with(strtolower((string)$mime), 'text/html') || str_starts_with(strtolower((string)$mime), 'text/plain')) {
                if (is_string($url) && safe_url($url) !== '') { $reader = $url; break; }
            }
        }
    }
    if ($reader === '') return null;
    $authors = [];
    foreach ($row['authors'] as $author) if (is_array($author) && is_string($author['name'] ?? null)) $authors[] = $author['name'];
    $subjects = array_filter($row['subjects'], 'is_string');
    return ['title' => mb_substr($row['title'], 0, 255), 'author' => mb_substr(implode(', ', $authors) ?: 'Unknown author', 0, 255),
        'category' => mb_substr(implode(', ', $subjects), 0, 255), 'description' => implode('; ', $subjects),
        'cover' => is_string($formats['image/jpeg'] ?? null) ? $formats['image/jpeg'] : '',
        'read' => $reader,
        // Gutenberg's stable ebook ID lets the download endpoint construct its EPUB URL
        // when Gutendex does not list an EPUB MIME format for this title.
        'download' => is_string($formats['application/epub+zip'] ?? null) && safe_url($formats['application/epub+zip']) !== ''
            ? $formats['application/epub+zip'] : (string)(int)$row['id'],
        'source' => 'Project Gutenberg'];
}
