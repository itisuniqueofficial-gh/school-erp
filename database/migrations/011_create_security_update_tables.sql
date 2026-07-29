-- ===========================================================================
-- 011 - Security & Update tracking: login attempts, update logs
-- ===========================================================================

-- Records login attempts for brute-force protection & login history.
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier`  VARCHAR(190) NOT NULL,          -- email/username tried
    `ip_address`  VARCHAR(45) NOT NULL,
    `successful`  TINYINT(1) NOT NULL DEFAULT 0,
    `user_agent`  VARCHAR(255) DEFAULT NULL,
    `attempted_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_login_identifier` (`identifier`),
    KEY `idx_login_ip` (`ip_address`),
    KEY `idx_login_time` (`attempted_at`),
    KEY `idx_login_success` (`successful`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Tracks applied application updates (for the update system).
CREATE TABLE IF NOT EXISTS `update_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `from_version` VARCHAR(20) DEFAULT NULL,
    `to_version`   VARCHAR(20) NOT NULL,
    `notes`        VARCHAR(500) DEFAULT NULL,
    `applied_by`   BIGINT UNSIGNED DEFAULT NULL,
    `created_at`   DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_update_version` (`to_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
