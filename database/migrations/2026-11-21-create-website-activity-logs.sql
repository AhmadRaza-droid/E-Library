-- Website activity history for registered users, admins and guest visitors.
-- Run once in phpMyAdmin. This is separate from admin_audit_logs.
CREATE TABLE IF NOT EXISTS website_activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL DEFAULT 0,
    user_name VARCHAR(150) NOT NULL DEFAULT 'Guest',
    actor_type ENUM('guest','user','admin') NOT NULL DEFAULT 'guest',
    action VARCHAR(120) NOT NULL,
    details VARCHAR(1500) NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT 'unknown',
    page VARCHAR(190) NOT NULL DEFAULT '',
    http_method VARCHAR(10) NOT NULL DEFAULT 'GET',
    user_agent VARCHAR(500) NULL,
    session_key CHAR(64) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_created (created_at),
    KEY idx_activity_user (user_id),
    KEY idx_activity_actor (actor_type),
    KEY idx_activity_ip (ip_address),
    KEY idx_activity_session (session_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
