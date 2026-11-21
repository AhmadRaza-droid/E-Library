-- Run this migration in phpMyAdmin after taking a full database backup.
-- Project Gutenberg SQL imports may provide only id, image, author, title and category.
-- This adds the optional fields used by the E-Library reader, importer and PDF uploader.
-- Every column check is idempotent, so existing installations keep their data.

SET @has_cover_image_url = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'cover_image_url'
);
SET @sql_cover_image_url = IF(@has_cover_image_url = 0,
    'ALTER TABLE books ADD COLUMN cover_image_url TEXT NULL',
    'SELECT ''books.cover_image_url already exists'' AS migration_status');
PREPARE stmt_cover_image_url FROM @sql_cover_image_url;
EXECUTE stmt_cover_image_url;
DEALLOCATE PREPARE stmt_cover_image_url;

SET @has_read_link = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'read_link'
);
SET @sql_read_link = IF(@has_read_link = 0,
    'ALTER TABLE books ADD COLUMN read_link TEXT NULL',
    'SELECT ''books.read_link already exists'' AS migration_status');
PREPARE stmt_read_link FROM @sql_read_link;
EXECUTE stmt_read_link;
DEALLOCATE PREPARE stmt_read_link;

SET @has_download_epub_link = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'download_epub_link'
);
SET @sql_download_epub_link = IF(@has_download_epub_link = 0,
    'ALTER TABLE books ADD COLUMN download_epub_link TEXT NULL',
    'SELECT ''books.download_epub_link already exists'' AS migration_status');
PREPARE stmt_download_epub_link FROM @sql_download_epub_link;
EXECUTE stmt_download_epub_link;
DEALLOCATE PREPARE stmt_download_epub_link;

SET @has_pdf_file = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'pdf_file'
);
SET @sql_pdf_file = IF(@has_pdf_file = 0,
    'ALTER TABLE books ADD COLUMN pdf_file VARCHAR(255) NULL',
    'SELECT ''books.pdf_file already exists'' AS migration_status');
PREPARE stmt_pdf_file FROM @sql_pdf_file;
EXECUTE stmt_pdf_file;
DEALLOCATE PREPARE stmt_pdf_file;

SET @has_cover_file = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'cover_file'
);
SET @sql_cover_file = IF(@has_cover_file = 0,
    'ALTER TABLE books ADD COLUMN cover_file VARCHAR(255) NULL',
    'SELECT ''books.cover_file already exists'' AS migration_status');
PREPARE stmt_cover_file FROM @sql_cover_file;
EXECUTE stmt_cover_file;
DEALLOCATE PREPARE stmt_cover_file;

SET @has_source = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'source'
);
SET @sql_source = IF(@has_source = 0,
    'ALTER TABLE books ADD COLUMN source VARCHAR(100) NOT NULL DEFAULT ''Manual''',
    'SELECT ''books.source already exists'' AS migration_status');
PREPARE stmt_source FROM @sql_source;
EXECUTE stmt_source;
DEALLOCATE PREPARE stmt_source;

-- Preserve cover URLs from the older Project Gutenberg import's image field.
SET @has_legacy_image = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'image'
);
SET @sql_copy_legacy_image = IF(@has_legacy_image > 0,
    'UPDATE books SET cover_image_url = image WHERE (cover_image_url IS NULL OR cover_image_url = '''') AND image IS NOT NULL',
    'SELECT ''No legacy image column to copy'' AS migration_status');
PREPARE stmt_copy_legacy_image FROM @sql_copy_legacy_image;
EXECUTE stmt_copy_legacy_image;
DEALLOCATE PREPARE stmt_copy_legacy_image;
