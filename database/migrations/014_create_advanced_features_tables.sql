-- ===========================================================================
-- 014 - Advanced features: subject-wise attendance, encrypted government IDs,
--       and class/subject archive + metadata columns.
-- ===========================================================================

-- Subject / period-wise attendance. Uniqueness is enforced at the database
-- level so duplicate attendance for the same session is impossible even under
-- concurrent submissions.
CREATE TABLE IF NOT EXISTS `subject_attendance` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT UNSIGNED NOT NULL,
    `class_id`         INT UNSIGNED NOT NULL,
    `section_id`       INT UNSIGNED NOT NULL,
    `subject_id`       INT UNSIGNED NOT NULL,
    `student_id`       BIGINT UNSIGNED NOT NULL,
    `date`             DATE NOT NULL,
    `period`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status`           ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    `remarks`          VARCHAR(255) DEFAULT NULL,
    `marked_by`        BIGINT UNSIGNED DEFAULT NULL,
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subject_attendance` (`academic_year_id`, `class_id`, `section_id`, `subject_id`, `date`, `period`, `student_id`),
    KEY `idx_subatt_ctx` (`class_id`, `section_id`, `subject_id`, `date`),
    KEY `idx_subatt_student` (`student_id`),
    CONSTRAINT `fk_subatt_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subatt_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subatt_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subatt_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subatt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subatt_marker` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Government / academic identifiers. Aadhaar is stored encrypted at rest
-- (AES-256-GCM); the others are less sensitive but still permission-gated in UI.
ALTER TABLE `students` ADD COLUMN `aadhaar_enc` TEXT DEFAULT NULL AFTER `category`;
ALTER TABLE `students` ADD COLUMN `aadhaar_last4` CHAR(4) DEFAULT NULL AFTER `aadhaar_enc`;
ALTER TABLE `students` ADD COLUMN `abc_id`   VARCHAR(40) DEFAULT NULL AFTER `aadhaar_last4`;
ALTER TABLE `students` ADD COLUMN `apaar_id` VARCHAR(40) DEFAULT NULL AFTER `abc_id`;
ALTER TABLE `students` ADD COLUMN `pen_id`   VARCHAR(40) DEFAULT NULL AFTER `apaar_id`;
ALTER TABLE `students` ADD COLUMN `udise_id` VARCHAR(40) DEFAULT NULL AFTER `pen_id`;

-- Class archive + metadata (history-safe: archive instead of delete).
ALTER TABLE `classes` ADD COLUMN `code` VARCHAR(30) DEFAULT NULL AFTER `name`;
ALTER TABLE `classes` ADD COLUMN `description` VARCHAR(255) DEFAULT NULL AFTER `numeric_level`;
ALTER TABLE `classes` ADD COLUMN `status` ENUM('active','archived') NOT NULL DEFAULT 'active' AFTER `description`;

-- Subject archive + metadata.
ALTER TABLE `subjects` ADD COLUMN `max_marks` INT NOT NULL DEFAULT 100 AFTER `type`;
ALTER TABLE `subjects` ADD COLUMN `pass_marks` INT NOT NULL DEFAULT 33 AFTER `max_marks`;
ALTER TABLE `subjects` ADD COLUMN `description` VARCHAR(255) DEFAULT NULL AFTER `pass_marks`;
ALTER TABLE `subjects` ADD COLUMN `status` ENUM('active','archived') NOT NULL DEFAULT 'active' AFTER `description`;
