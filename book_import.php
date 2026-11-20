<?php
// Functions only; the caller enforces admin access and CSRF.
function import_catalog_book($conn, $book){
    $read = resolve_read_link($book['read']);
    $download = resolve_download_link($book['download']);
    if ($read === '') return 'failed';
    $legacy = ctype_digit((string)$book['read']) ? 'https://www.gutenberg.org/ebooks/' . $book['read'] : $read;
    if (db_row($conn, 'SELECT id FROM books WHERE (title=? AND author=?) OR read_link IN (?, ?, ?)', 'sssss',
        [$book['title'], $book['author'], $read, (string)$book['read'], $legacy])) return 'duplicate';
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
    $html = '';
    foreach ($formats as $mime => $url) {
        if (str_starts_with($mime, 'text/html') && is_string($url) && safe_url($url) !== '') { $html = $url; break; }
    }
    if ($html === '') return null;
    $authors = [];
    foreach ($row['authors'] as $author) if (is_array($author) && is_string($author['name'] ?? null)) $authors[] = $author['name'];
    $subjects = array_filter($row['subjects'], 'is_string');
    return ['title' => mb_substr($row['title'], 0, 255), 'author' => mb_substr(implode(', ', $authors) ?: 'Unknown author', 0, 255),
        'category' => mb_substr(implode(', ', $subjects), 0, 255), 'description' => implode('; ', $subjects),
        'cover' => is_string($formats['image/jpeg'] ?? null) ? $formats['image/jpeg'] : '',
        'read' => (string)(int)$row['id'], 'download' => is_string($formats['application/epub+zip'] ?? null) ? $formats['application/epub+zip'] : '',
        'source' => 'Project Gutenberg'];
}
