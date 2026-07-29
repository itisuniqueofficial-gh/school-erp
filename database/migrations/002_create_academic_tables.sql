-- ===========================================================================
-- 002 - Academic structure: years, classes, sections, subjects, syllabus
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `academic_years` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(50) NOT NULL,          -- e.g. "2025-2026"
    `start_date` DATE NOT NULL,
    `end_date`   DATE NOT NULL,
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_year_name` (`name`),
    KEY `idx_year_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Classes / grades (e.g. Grade 1, Class X).
CREATE TABLE IF NOT EXISTS `classes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80) NOT NULL,
    `numeric_level` INT NOT NULL DEFAULT 0,     -- ordering / promotion
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_name` (`name`),
    KEY `idx_class_level` (`numeric_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Sections within a class (e.g. A, B, C).
CREATE TABLE IF NOT EXISTS `sections` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id`    INT UNSIGNED NOT NULL,
    `name`        VARCHAR(50) NOT NULL,
    `capacity`    INT UNSIGNED NOT NULL DEFAULT 40,
    `class_teacher_id` BIGINT UNSIGNED DEFAULT NULL,  -- references staff.id
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_section_class_name` (`class_id`, `name`),
    KEY `idx_section_class` (`class_id`),
    CONSTRAINT `fk_section_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Subjects.
CREATE TABLE IF NOT EXISTS `subjects` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `code`       VARCHAR(30) NOT NULL,
    `type`       ENUM('theory','practical','both') NOT NULL DEFAULT 'theory',
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subject_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Subjects allocated to a class.
CREATE TABLE IF NOT EXISTS `class_subjects` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id`   INT UNSIGNED NOT NULL,
    `subject_id` INT UNSIGNED NOT NULL,
    `full_marks` INT NOT NULL DEFAULT 100,
    `pass_marks` INT NOT NULL DEFAULT 33,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_subject` (`class_id`, `subject_id`),
    KEY `idx_cs_subject` (`subject_id`),
    CONSTRAINT `fk_cs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Syllabus / curriculum documents per class-subject.
CREATE TABLE IF NOT EXISTS `syllabi` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id`    INT UNSIGNED NOT NULL,
    `subject_id`  INT UNSIGNED NOT NULL,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `title`       VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `file_path`   VARCHAR(255) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_syllabus_class` (`class_id`),
    KEY `idx_syllabus_subject` (`subject_id`),
    CONSTRAINT `fk_syllabus_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_syllabus_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_syllabus_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
