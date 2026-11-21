<?php
// Internal helpers for moderated student materials. Files are never served directly.
function resources_ready($conn){
    $result = $conn->query("SHOW TABLES LIKE 'student\\_resources'");
    return $result instanceof mysqli_result && $result->num_rows === 1;
}

function resources_storage_prepare(){
    $uploadRoot = __DIR__ . '/uploads';
    $dir = $uploadRoot . '/resources';
    if (is_link($uploadRoot) || is_link($dir)) return false;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return false;

    // Uploaded study materials must never be reachable through a public URL.
    $htaccess = $dir . '/.htaccess';
    if (is_link($htaccess)) return false;
    $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
    if (!is_file($htaccess)) {
        if (@file_put_contents($htaccess, $rules, LOCK_EX) === false) return false;
        @chmod($htaccess, 0644);
    } else {
        $current = @file_get_contents($htaccess);
        if (!is_string($current)) return false;
        if (!preg_match('/^\\s*(?:Require\\s+all\\s+denied|Deny\\s+from\\s+all)\\s*$/mi', $current)) {
            if (@file_put_contents($htaccess, "\n" . $rules, FILE_APPEND | LOCK_EX) === false) return false;
        }
    }
    return is_file($htaccess) && is_readable($htaccess);
}

function resources_install($conn){
    if (!resources_storage_prepare()) return false;
    $sql = file_get_contents(__DIR__ . '/student_resources.sql');
    return $sql !== false && $conn->query($sql) === true;
}

function resources_pending($conn){
    return resources_ready($conn) ? (int)db_value($conn,
        "SELECT COUNT(*) FROM student_resources WHERE status='pending'", '', [], 0) : 0;
}

function resource_formats(){
    return ['pdf' => 'PDF', 'doc' => 'Word', 'docx' => 'Word', 'ppt' => 'PowerPoint',
        'pptx' => 'PowerPoint', 'xls' => 'Excel', 'xlsx' => 'Excel', 'txt' => 'Text',
        'jpg' => 'Image', 'jpeg' => 'Image', 'png' => 'Image'];
}

function resource_ini_bytes($value){
    $value = trim((string)$value);
    if ($value === '' || $value === '0' || $value === '-1') return PHP_INT_MAX;
    $number = (float)$value;
    return (int)min(PHP_INT_MAX, $number * (1024 ** (['k'=>1, 'm'=>2, 'g'=>3][strtolower(substr($value, -1))] ?? 0)));
}

function resource_max_bytes(){
    $limit = filter_var(cfg('resource_max_bytes', 9 * 1024 * 1024), FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1024, 'max_range' => 10 * 1024 * 1024]]);
    // Reserve room for metadata and multipart headers within PHP's POST limit.
    return $limit === false ? 0 : max(0, min($limit,
        resource_ini_bytes(ini_get('upload_max_filesize')),
        resource_ini_bytes(ini_get('post_max_size')) - 65536));
}

function resource_size($bytes){
    if ($bytes < 1024) return (int)$bytes . ' bytes';
    return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';
}

/** Content and extension checks; Office ZIPs are inspected, never extracted. */
function resource_validate_file($path, $extension){
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $head = (string)file_get_contents($path, false, null, 0, 8);
    if ($extension === 'pdf') return $mime === 'application/pdf' && str_starts_with($head, '%PDF-');
    if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        $expected = $extension === 'png' ? 'image/png' : 'image/jpeg';
        return $mime === $expected && @getimagesize($path) !== false;
    }
    if ($extension === 'txt') {
        $text = (string)file_get_contents($path);
        return $mime === 'text/plain' && !str_contains($text, "\0") && !str_contains(strtolower($text), '<?php');
    }
    if (in_array($extension, ['doc', 'ppt', 'xls'], true)) {
        $mimes = ['application/msword', 'application/vnd.ms-powerpoint', 'application/vnd.ms-excel',
            'application/x-ole-storage', 'application/CDFV2', 'application/x-cdf'];
        if ($head !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" || !in_array($mime, $mimes, true)) return false;
        $data = (string)file_get_contents($path);
        $streams = ['doc' => ['WordDocument'], 'ppt' => ['PowerPoint Document'], 'xls' => ['Workbook', 'Book']];
        foreach ($streams[$extension] as $stream) {
            if (str_contains($data, mb_convert_encoding($stream, 'UTF-16LE', 'UTF-8'))) return true;
        }
        return false;
    }
    $parts = ['docx' => ['word/document.xml', 'wordprocessingml.document.main+xml'],
        'pptx' => ['ppt/presentation.xml', 'presentationml.presentation.main+xml'],
        'xlsx' => ['xl/workbook.xml', 'spreadsheetml.sheet.main+xml']];
    if (!isset($parts[$extension]) || !class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) return false;
    try {
        if ($zip->numFiles > 4096) return false;
        $expanded = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            if (!$entry || ($entry['encryption_method'] ?? 0) !== 0) return false;
            $expanded += $entry['size'];
            if ($expanded > 80 * 1024 * 1024 || preg_match('~vbaProject|macroEnabled|(^|/)\.\.(/|$)~i', $entry['name'])) return false;
        }
        [$part, $type] = $parts[$extension];
        $info = $zip->statName('[Content_Types].xml');
        if (!$info || $info['size'] > 65536 || $zip->locateName($part) === false) return false;
        $types = $zip->getFromName('[Content_Types].xml');
        return is_string($types) && str_contains($types, $type) && !preg_match('/macroEnabled|vbaProject/i', $types);
    } finally {
        $zip->close();
    }
}

function resource_storage_path($key){
    if (!is_string($key) || !preg_match('/\A[a-f0-9]{48}\.bin\z/D', $key)) return null;
    $uploadRoot = __DIR__ . '/uploads';
    $dir = $uploadRoot . '/resources';
    if (is_link($uploadRoot) || is_link($dir) || !is_dir($dir) || is_link($dir . '/' . $key)) return null;
    return $dir . '/' . $key;
}

function resource_save_upload($file){
    if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) return [false, 'Choose a file to upload.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return [false, 'Upload failed. Choose a file within the displayed size limit and try again.'];
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) return [false, 'Invalid upload.'];
    $size = filesize($tmp);
    if (!resource_max_bytes() || !$size || $size > resource_max_bytes()) return [false, 'The file is empty or exceeds the upload size limit.'];
    $name = $file['name'] ?? '';
    if (!is_string($name)) return [false, 'Invalid filename.'];
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name)));
    if (!$name || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 180) return [false, 'Use a filename of at most 180 characters.'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!isset(resource_formats()[$ext])) return [false, 'Use PDF, Word, PowerPoint, Excel, TXT, JPG or PNG.'];
    if (in_array($ext, ['docx', 'pptx', 'xlsx'], true) && !class_exists('ZipArchive')) {
        return [false, 'Office uploads require the PHP ZIP extension. Please ask the administrator or upload a PDF.'];
    }
    if (!resource_validate_file($tmp, $ext)) return [false, 'The file content does not match its format, is encrypted, or is not supported. Export a fresh PDF or Office file.'];
    // Prepare and verify private storage even on older installs where the table already exists.
    if (!resources_storage_prepare()) {
        return [false, 'Upload storage is unavailable. Ask the administrator to check folder permissions.'];
    }
    // Refuse uploads unless the private storage protection is present.
    $key = bin2hex(random_bytes(24)) . '.bin';
    $path = resource_storage_path($key);
    if (!$path || !is_file(dirname($path) . '/.htaccess') || !is_writable(dirname($path))) {
        return [false, 'Upload storage is unavailable. Ask the administrator to check uploads/resources and its .htaccess file.'];
    }
    if (!move_uploaded_file($tmp, $path)) return [false, 'Could not store the file. Please try again later.'];
    @chmod($path, 0600);
    return [true, ['storage_key' => $key, 'original_name' => $name, 'file_ext' => $ext, 'size_bytes' => $size]];
}

/** Only pending files can be published. A second decision cannot overwrite an earlier one. */
function resource_moderate($conn, $id, $adminId, $decision, $reason){
    if (!in_array($decision, ['approve', 'reject'], true)) return false;
    if ($decision === 'approve') {
        $row = db_row($conn, 'SELECT storage_key FROM student_resources WHERE id=?', 'i', [$id]);
        $path = $row ? resource_storage_path($row['storage_key']) : null;
        if (!$path || !is_file($path) || !is_readable($path)) return false;
    }
    $status = $decision === 'approve' ? 'approved' : 'rejected';
    $allowed = $decision === 'approve' ? "status='pending'" : "status IN ('pending','approved')";
    return db_exec($conn, "UPDATE student_resources SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW()
        WHERE id=? AND $allowed", 'ssii', [$status, $reason, $adminId, $id]) === 1;
}
