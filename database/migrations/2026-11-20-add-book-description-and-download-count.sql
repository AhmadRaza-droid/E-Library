-- Safe, repeatable migration for the two missing books columns reported
-- by the deployed database logs. Compatible with MySQL 5.7 / MariaDB.
-- Run this in phpMyAdmin after taking a database backup.

SET @has_description = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'books'
      AND COLUMN_NAME = 'description'
);
SET @sql_description = IF(
    @has_description = 0,
    'ALTER TABLE books ADD COLUMN description TEXT NULL',
    'SELECT ''books.description already exists'' AS migration_status'
);
PREPARE stmt_description FROM @sql_description;
EXECUTE stmt_description;
DEALLOCATE PREPARE stmt_description;

SET @has_download_count = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'books'
      AND COLUMN_NAME = 'download_count'
);
SET @sql_download_count = IF(
    @has_download_count = 0,
    'ALTER TABLE books ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0',
    'SELECT ''books.download_count already exists'' AS migration_status'
);
PREPARE stmt_download_count FROM @sql_download_count;
EXECUTE stmt_download_count;
DEALLOCATE PREPARE stmt_download_count;
