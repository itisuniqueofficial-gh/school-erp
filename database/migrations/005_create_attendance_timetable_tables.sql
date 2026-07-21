-- ===========================================================================
-- 005 - Attendance & Timetable
-- ===========================================================================

-- Daily student attendance.
CREATE TABLE IF NOT EXISTS `student_attendance` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`  BIGINT UNSIGNED NOT NULL,
    `class_id`    INT UNSIGNED DEFAULT NULL,
    `section_id`  INT UNSIGNED DEFAULT NULL,
    `date`        DATE NOT NULL,
    `status`      ENUM('present','absent','late','half_day','leave','holiday') NOT NULL DEFAULT 'present',
    `remarks`     VARCHAR(255) DEFAULT NULL,
    `marked_by`   BIGINT UNSIGNED DEFAULT NULL,
    `method`      ENUM('manual','biometric','qr') NOT NULL DEFAULT 'manual',
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attendance_student_date` (`student_id`, `date`),
    KEY `idx_att_date` (`date`),
    KEY `idx_att_class_section` (`class_id`, `section_id`),
    KEY `idx_att_status` (`status`),
    CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_att_marker` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff attendance.
CREATE TABLE IF NOT EXISTS `staff_attendance` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id`   BIGINT UNSIGNED NOT NULL,
    `date`       DATE NOT NULL,
    `status`     ENUM('present','absent','late','half_day','leave','holiday') NOT NULL DEFAULT 'present',
    `check_in`   TIME DEFAULT NULL,
    `check_out`  TIME DEFAULT NULL,
    `remarks`    VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_staff_attendance_date` (`staff_id`, `date`),
    KEY `idx_satt_date` (`date`),
    CONSTRAINT `fk_satt_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms (for timetable allocation).
CREATE TABLE IF NOT EXISTS `rooms` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80) NOT NULL,
    `capacity`   INT UNSIGNED NOT NULL DEFAULT 40,
    `type`       VARCHAR(60) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_room_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timetable periods/slots.
CREATE TABLE IF NOT EXISTS `timetable_slots` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(60) NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time`   TIME NOT NULL,
    `is_break`   TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timetable entries.
CREATE TABLE IF NOT EXISTS `timetables` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id`   INT UNSIGNED NOT NULL,
    `section_id` INT UNSIGNED NOT NULL,
    `subject_id` INT UNSIGNED DEFAULT NULL,
    `staff_id`   BIGINT UNSIGNED DEFAULT NULL,
    `room_id`    INT UNSIGNED DEFAULT NULL,
    `slot_id`    INT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL,   -- 1=Mon ... 7=Sun
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tt_slot` (`section_id`, `slot_id`, `day_of_week`),
    KEY `idx_tt_class` (`class_id`),
    KEY `idx_tt_staff` (`staff_id`),
    KEY `idx_tt_subject` (`subject_id`),
    CONSTRAINT `fk_tt_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tt_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tt_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tt_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tt_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tt_slot` FOREIGN KEY (`slot_id`) REFERENCES `timetable_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
