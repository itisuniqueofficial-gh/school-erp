-- ===========================================================================
-- 010 - Communication & Settings
-- ===========================================================================

-- School profile / general settings (key-value store).
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group`      VARCHAR(60) NOT NULL DEFAULT 'general',
    `key`        VARCHAR(120) NOT NULL,
    `value`      TEXT DEFAULT NULL,
    `type`       VARCHAR(30) NOT NULL DEFAULT 'string',
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_setting_key` (`key`),
    KEY `idx_setting_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notices (public/board announcements).
CREATE TABLE IF NOT EXISTS `notices` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(200) NOT NULL,
    `body`        TEXT NOT NULL,
    `audience`    ENUM('all','students','parents','teachers','staff') NOT NULL DEFAULT 'all',
    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
    `published_at` DATETIME DEFAULT NULL,
    `expires_at`  DATETIME DEFAULT NULL,
    `created_by`  BIGINT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_notice_audience` (`audience`),
    KEY `idx_notice_published` (`is_published`),
    CONSTRAINT `fk_notice_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Internal messages (one-to-one/broadcast).
CREATE TABLE IF NOT EXISTS `messages` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_id`   BIGINT UNSIGNED DEFAULT NULL,
    `subject`     VARCHAR(200) DEFAULT NULL,
    `body`        TEXT NOT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_message_sender` (`sender_id`),
    CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `message_recipients` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_id` BIGINT UNSIGNED NOT NULL,
    `recipient_id` BIGINT UNSIGNED NOT NULL,
    `read_at`    DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_msg_recipient` (`message_id`, `recipient_id`),
    KEY `idx_mr_recipient` (`recipient_id`),
    CONSTRAINT `fk_mr_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mr_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications (in-app), pluggable channel log for SMS/email.
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `type`       VARCHAR(80) NOT NULL DEFAULT 'info',
    `title`      VARCHAR(200) NOT NULL,
    `body`       VARCHAR(500) DEFAULT NULL,
    `link`       VARCHAR(255) DEFAULT NULL,
    `read_at`    DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`),
    KEY `idx_notif_read` (`read_at`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outbound communication log (email/SMS providers pluggable).
CREATE TABLE IF NOT EXISTS `communication_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel`    ENUM('email','sms','push') NOT NULL DEFAULT 'email',
    `recipient`  VARCHAR(190) NOT NULL,
    `subject`    VARCHAR(200) DEFAULT NULL,
    `body`       TEXT DEFAULT NULL,
    `status`     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    `error`      VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_commlog_channel` (`channel`),
    KEY `idx_commlog_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
