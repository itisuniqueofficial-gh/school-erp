-- ===========================================================================
-- 006 - Examination: exam types, exams, schedules, grades, marks, report cards
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `exam_types` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,          -- e.g. Midterm, Final, Unit Test
    `weightage`  DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_exam_type_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exams` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_type_id`     INT UNSIGNED NOT NULL,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `name`             VARCHAR(150) NOT NULL,
    `start_date`       DATE DEFAULT NULL,
    `end_date`         DATE DEFAULT NULL,
    `status`           ENUM('scheduled','ongoing','completed','published') NOT NULL DEFAULT 'scheduled',
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_exam_type` (`exam_type_id`),
    KEY `idx_exam_year` (`academic_year_id`),
    CONSTRAINT `fk_exam_type` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_exam_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Per class-subject schedule within an exam.
CREATE TABLE IF NOT EXISTS `exam_schedules` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_id`    BIGINT UNSIGNED NOT NULL,
    `class_id`   INT UNSIGNED NOT NULL,
    `subject_id` INT UNSIGNED NOT NULL,
    `exam_date`  DATE DEFAULT NULL,
    `start_time` TIME DEFAULT NULL,
    `end_time`   TIME DEFAULT NULL,
    `full_marks` INT NOT NULL DEFAULT 100,
    `pass_marks` INT NOT NULL DEFAULT 33,
    `room_id`    INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_exam_schedule` (`exam_id`, `class_id`, `subject_id`),
    KEY `idx_es_class` (`class_id`),
    KEY `idx_es_subject` (`subject_id`),
    CONSTRAINT `fk_es_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_es_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_es_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_es_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Grading scale.
CREATE TABLE IF NOT EXISTS `grades` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(10) NOT NULL,          -- A+, A, B ...
    `min_percent` DECIMAL(5,2) NOT NULL,
    `max_percent` DECIMAL(5,2) NOT NULL,
    `gpa`         DECIMAL(4,2) NOT NULL DEFAULT 0,
    `remark`      VARCHAR(60) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_grade_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Individual student marks per exam-subject.
CREATE TABLE IF NOT EXISTS `exam_marks` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_schedule_id`  BIGINT UNSIGNED NOT NULL,
    `student_id`        BIGINT UNSIGNED NOT NULL,
    `marks_obtained`    DECIMAL(6,2) NOT NULL DEFAULT 0,
    `is_absent`         TINYINT(1) NOT NULL DEFAULT 0,
    `grade_id`          INT UNSIGNED DEFAULT NULL,
    `remarks`           VARCHAR(255) DEFAULT NULL,
    `entered_by`        BIGINT UNSIGNED DEFAULT NULL,
    `created_at`        DATETIME DEFAULT NULL,
    `updated_at`        DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mark_schedule_student` (`exam_schedule_id`, `student_id`),
    KEY `idx_mark_student` (`student_id`),
    KEY `idx_mark_grade` (`grade_id`),
    CONSTRAINT `fk_mark_schedule` FOREIGN KEY (`exam_schedule_id`) REFERENCES `exam_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mark_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mark_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Aggregated report cards per student per exam.
CREATE TABLE IF NOT EXISTS `report_cards` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_id`        BIGINT UNSIGNED NOT NULL,
    `student_id`     BIGINT UNSIGNED NOT NULL,
    `total_marks`    DECIMAL(8,2) NOT NULL DEFAULT 0,
    `obtained_marks` DECIMAL(8,2) NOT NULL DEFAULT 0,
    `percentage`     DECIMAL(5,2) NOT NULL DEFAULT 0,
    `gpa`            DECIMAL(4,2) NOT NULL DEFAULT 0,
    `grade_id`       INT UNSIGNED DEFAULT NULL,
    `rank`           INT UNSIGNED DEFAULT NULL,
    `result`         ENUM('pass','fail','pending') NOT NULL DEFAULT 'pending',
    `published_at`   DATETIME DEFAULT NULL,
    `created_at`     DATETIME DEFAULT NULL,
    `updated_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reportcard_exam_student` (`exam_id`, `student_id`),
    KEY `idx_rc_student` (`student_id`),
    CONSTRAINT `fk_rc_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rc_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
