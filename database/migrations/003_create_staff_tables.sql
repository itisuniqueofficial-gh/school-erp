-- ===========================================================================
-- 003 - Staff & Teachers: departments, staff, qualifications, allocations
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `departments` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `code`       VARCHAR(30) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_department_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff/employees: teaching and non-teaching.
CREATE TABLE IF NOT EXISTS `staff` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        BIGINT UNSIGNED DEFAULT NULL,
    `department_id`  INT UNSIGNED DEFAULT NULL,
    `employee_code`  VARCHAR(40) NOT NULL,
    `first_name`     VARCHAR(80) NOT NULL,
    `last_name`      VARCHAR(80) NOT NULL,
    `staff_type`     ENUM('teaching','non_teaching') NOT NULL DEFAULT 'teaching',
    `designation`    VARCHAR(120) DEFAULT NULL,
    `gender`         ENUM('male','female','other') DEFAULT NULL,
    `dob`            DATE DEFAULT NULL,
    `email`          VARCHAR(190) DEFAULT NULL,
    `phone`          VARCHAR(20) DEFAULT NULL,
    `address`        VARCHAR(255) DEFAULT NULL,
    `joining_date`   DATE DEFAULT NULL,
    `qualification`  VARCHAR(200) DEFAULT NULL,
    `experience_years` DECIMAL(4,1) NOT NULL DEFAULT 0,
    `basic_salary`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `photo`          VARCHAR(255) DEFAULT NULL,
    `status`         ENUM('active','inactive','on_leave','resigned') NOT NULL DEFAULT 'active',
    `created_at`     DATETIME DEFAULT NULL,
    `updated_at`     DATETIME DEFAULT NULL,
    `deleted_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_staff_code` (`employee_code`),
    KEY `idx_staff_user` (`user_id`),
    KEY `idx_staff_department` (`department_id`),
    KEY `idx_staff_type` (`staff_type`),
    KEY `idx_staff_status` (`status`),
    CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_staff_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher qualifications (one-to-many).
CREATE TABLE IF NOT EXISTS `teacher_qualifications` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id`    BIGINT UNSIGNED NOT NULL,
    `degree`      VARCHAR(150) NOT NULL,
    `institution` VARCHAR(200) DEFAULT NULL,
    `year`        SMALLINT UNSIGNED DEFAULT NULL,
    `grade`       VARCHAR(30) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tq_staff` (`staff_id`),
    CONSTRAINT `fk_tq_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher <-> subject/class/section allocation.
CREATE TABLE IF NOT EXISTS `teacher_subjects` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id`   BIGINT UNSIGNED NOT NULL,
    `subject_id` INT UNSIGNED NOT NULL,
    `class_id`   INT UNSIGNED DEFAULT NULL,
    `section_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ts_staff` (`staff_id`),
    KEY `idx_ts_subject` (`subject_id`),
    KEY `idx_ts_class` (`class_id`),
    KEY `idx_ts_section` (`section_id`),
    CONSTRAINT `fk_ts_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ts_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ts_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ts_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
