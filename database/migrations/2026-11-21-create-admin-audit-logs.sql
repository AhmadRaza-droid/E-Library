-- Admin audit trail: actor, action, Pakistan-local timestamp, source path, IP and browser.
-- Run once in phpMyAdmin on the E-Library database.
CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT NOT NULL DEFAULT 0,
    admin_name VARCHAR(150) NOT NULL DEFAULT 'Admin',
    action VARCHAR(120) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT 'unknown',
    source VARCHAR(500) NOT NULL DEFAULT '/',
    http_method VARCHAR(10) NOT NULL DEFAULT 'GET',
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_admin (admin_id),
    KEY idx_audit_action (action),
    KEY idx_audit_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
