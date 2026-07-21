-- ===========================================================================
-- 004 - Students: students, guardians, documents, promotion history
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `students` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED DEFAULT NULL,
    `admission_no`     VARCHAR(40) NOT NULL,
    `roll_no`          VARCHAR(30) DEFAULT NULL,
    `first_name`       VARCHAR(80) NOT NULL,
    `last_name`        VARCHAR(80) NOT NULL,
    `gender`           ENUM('male','female','other') NOT NULL DEFAULT 'male',
    `dob`              DATE DEFAULT NULL,
    `blood_group`      VARCHAR(5) DEFAULT NULL,
    `email`            VARCHAR(190) DEFAULT NULL,
    `phone`            VARCHAR(20) DEFAULT NULL,
    `address`          VARCHAR(255) DEFAULT NULL,
    `city`             VARCHAR(80) DEFAULT NULL,
    `state`            VARCHAR(80) DEFAULT NULL,
    `postal_code`      VARCHAR(20) DEFAULT NULL,
    `nationality`      VARCHAR(60) DEFAULT NULL,
    `religion`         VARCHAR(60) DEFAULT NULL,
    `category`         VARCHAR(60) DEFAULT NULL,
    `class_id`         INT UNSIGNED DEFAULT NULL,
    `section_id`       INT UNSIGNED DEFAULT NULL,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `admission_date`   DATE DEFAULT NULL,
    `photo`            VARCHAR(255) DEFAULT NULL,
    `medical_notes`    TEXT DEFAULT NULL,
    `status`           ENUM('active','inactive','graduated','transferred','alumni') NOT NULL DEFAULT 'active',
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    `deleted_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_admission` (`admission_no`),
    KEY `idx_student_user` (`user_id`),
    KEY `idx_student_class` (`class_id`),
    KEY `idx_student_section` (`section_id`),
    KEY `idx_student_year` (`academic_year_id`),
    KEY `idx_student_status` (`status`),
    KEY `idx_student_deleted` (`deleted_at`),
    CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_student_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_student_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_student_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guardians / parents.
CREATE TABLE IF NOT EXISTS `guardians` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED DEFAULT NULL,
    `name`         VARCHAR(150) NOT NULL,
    `relation`     ENUM('father','mother','guardian','other') NOT NULL DEFAULT 'guardian',
    `occupation`   VARCHAR(120) DEFAULT NULL,
    `email`        VARCHAR(190) DEFAULT NULL,
    `phone`        VARCHAR(20) DEFAULT NULL,
    `address`      VARCHAR(255) DEFAULT NULL,
    `created_at`   DATETIME DEFAULT NULL,
    `updated_at`   DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_guardian_user` (`user_id`),
    CONSTRAINT `fk_guardian_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student <-> guardian (many-to-many; a guardian may have several children).
CREATE TABLE IF NOT EXISTS `student_guardians` (
    `student_id`  BIGINT UNSIGNED NOT NULL,
    `guardian_id` BIGINT UNSIGNED NOT NULL,
    `is_primary`  TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`student_id`, `guardian_id`),
    KEY `idx_sg_guardian` (`guardian_id`),
    CONSTRAINT `fk_sg_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sg_guardian` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student uploaded documents (certificates, ID proofs, etc.).
CREATE TABLE IF NOT EXISTS `student_documents` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`  BIGINT UNSIGNED NOT NULL,
    `title`       VARCHAR(150) NOT NULL,
    `type`        VARCHAR(60) DEFAULT NULL,
    `file_path`   VARCHAR(255) NOT NULL,
    `mime_type`   VARCHAR(100) DEFAULT NULL,
    `file_size`   INT UNSIGNED DEFAULT NULL,
    `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sd_student` (`student_id`),
    CONSTRAINT `fk_sd_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sd_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promotion / transfer history.
CREATE TABLE IF NOT EXISTS `student_promotions` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`        BIGINT UNSIGNED NOT NULL,
    `from_class_id`     INT UNSIGNED DEFAULT NULL,
    `to_class_id`       INT UNSIGNED DEFAULT NULL,
    `from_section_id`   INT UNSIGNED DEFAULT NULL,
    `to_section_id`     INT UNSIGNED DEFAULT NULL,
    `academic_year_id`  INT UNSIGNED DEFAULT NULL,
    `result`            ENUM('promoted','retained','graduated') NOT NULL DEFAULT 'promoted',
    `remarks`           VARCHAR(255) DEFAULT NULL,
    `promoted_by`       BIGINT UNSIGNED DEFAULT NULL,
    `created_at`        DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sp_student` (`student_id`),
    CONSTRAINT `fk_sp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
