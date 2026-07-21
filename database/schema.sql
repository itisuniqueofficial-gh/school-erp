-- ===========================================================================
-- School ERP - Consolidated Database Schema (MySQL 8+)
-- Generated from database/migrations. Import into an empty utf8mb4 database.
-- ===========================================================================
SET FOREIGN_KEY_CHECKS=0;

-- ===========================================================================
-- 001 - Authentication, RBAC & Audit tables
-- ===========================================================================

-- Users: every person who can authenticate (staff, students, parents).
CREATE TABLE IF NOT EXISTS `users` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(150) NOT NULL,
    `email`          VARCHAR(190) NOT NULL,
    `username`       VARCHAR(100) DEFAULT NULL,
    `phone`          VARCHAR(20) DEFAULT NULL,
    `password`       VARCHAR(255) NOT NULL,
    `avatar`         VARCHAR(255) DEFAULT NULL,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified_at` DATETIME DEFAULT NULL,
    `last_login_at`  DATETIME DEFAULT NULL,
    `last_login_ip`  VARCHAR(45) DEFAULT NULL,
    `created_at`     DATETIME DEFAULT NULL,
    `updated_at`     DATETIME DEFAULT NULL,
    `deleted_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    UNIQUE KEY `uq_users_username` (`username`),
    KEY `idx_users_active` (`is_active`),
    KEY `idx_users_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles (RBAC).
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(80) NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_system`   TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions (RBAC).
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(100) NOT NULL,
    `name`        VARCHAR(150) NOT NULL,
    `module`      VARCHAR(80) NOT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_slug` (`slug`),
    KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role <-> Permission (many-to-many).
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `idx_rp_permission` (`permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User <-> Role (many-to-many).
CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `idx_ur_role` (`role_id`),
    CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remember-me tokens (selector/validator split).
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `selector`   VARCHAR(24) NOT NULL,
    `token`      VARCHAR(128) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_remember_selector` (`selector`),
    KEY `idx_remember_user` (`user_id`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens.
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(190) NOT NULL,
    `token`      VARCHAR(128) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_pwreset_email` (`email`),
    KEY `idx_pwreset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API access tokens (Bearer) for the REST API.
CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `name`        VARCHAR(120) NOT NULL DEFAULT 'api',
    `token`       VARCHAR(128) NOT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `expires_at`  DATETIME DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_api_token` (`token`),
    KEY `idx_api_user` (`user_id`),
    CONSTRAINT `fk_api_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log (security & activity trail).
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED DEFAULT NULL,
    `action`      VARCHAR(120) NOT NULL,
    `entity_type` VARCHAR(120) DEFAULT NULL,
    `entity_id`   BIGINT UNSIGNED DEFAULT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `ip_address`  VARCHAR(45) DEFAULT NULL,
    `user_agent`  VARCHAR(255) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user` (`user_id`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- 007 - Fees: categories, structures, invoices, payments, discounts
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `fee_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120) NOT NULL,         -- Tuition, Transport, Exam, etc.
    `description` VARCHAR(255) DEFAULT NULL,
    `is_recurring` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fee_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fee structure per class + academic year.
CREATE TABLE IF NOT EXISTS `fee_structures` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(150) NOT NULL,
    `class_id`         INT UNSIGNED DEFAULT NULL,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `frequency`        ENUM('monthly','quarterly','half_yearly','yearly','one_time') NOT NULL DEFAULT 'yearly',
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fs_class` (`class_id`),
    KEY `idx_fs_year` (`academic_year_id`),
    CONSTRAINT `fk_fs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fs_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Line items within a fee structure.
CREATE TABLE IF NOT EXISTS `fee_structure_items` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fee_structure_id` INT UNSIGNED NOT NULL,
    `fee_category_id`  INT UNSIGNED NOT NULL,
    `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
    `due_date`         DATE DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fsi_structure` (`fee_structure_id`),
    KEY `idx_fsi_category` (`fee_category_id`),
    CONSTRAINT `fk_fsi_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fsi_category` FOREIGN KEY (`fee_category_id`) REFERENCES `fee_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scholarships / discounts.
CREATE TABLE IF NOT EXISTS `fee_discounts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `type`        ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    `value`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student fee invoices.
CREATE TABLE IF NOT EXISTS `fee_invoices` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_no`       VARCHAR(40) NOT NULL,
    `student_id`       BIGINT UNSIGNED NOT NULL,
    `fee_structure_id` INT UNSIGNED DEFAULT NULL,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `total_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
    `discount_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `fine_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
    `paid_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
    `due_date`         DATE DEFAULT NULL,
    `status`           ENUM('unpaid','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoice_no` (`invoice_no`),
    KEY `idx_inv_student` (`student_id`),
    KEY `idx_inv_status` (`status`),
    CONSTRAINT `fk_inv_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inv_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice line items.
CREATE TABLE IF NOT EXISTS `fee_invoice_items` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fee_invoice_id`  BIGINT UNSIGNED NOT NULL,
    `fee_category_id` INT UNSIGNED DEFAULT NULL,
    `description`     VARCHAR(200) NOT NULL,
    `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_fii_invoice` (`fee_invoice_id`),
    CONSTRAINT `fk_fii_invoice` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fii_category` FOREIGN KEY (`fee_category_id`) REFERENCES `fee_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments against invoices (supports installments).
CREATE TABLE IF NOT EXISTS `fee_payments` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `receipt_no`     VARCHAR(40) NOT NULL,
    `fee_invoice_id` BIGINT UNSIGNED NOT NULL,
    `student_id`     BIGINT UNSIGNED NOT NULL,
    `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0,
    `payment_date`   DATE NOT NULL,
    `method`         ENUM('cash','card','bank_transfer','cheque','online','upi') NOT NULL DEFAULT 'cash',
    `reference`      VARCHAR(120) DEFAULT NULL,
    `transaction_id` VARCHAR(120) DEFAULT NULL,
    `status`         ENUM('success','pending','failed','refunded') NOT NULL DEFAULT 'success',
    `collected_by`   BIGINT UNSIGNED DEFAULT NULL,
    `remarks`        VARCHAR(255) DEFAULT NULL,
    `created_at`     DATETIME DEFAULT NULL,
    `updated_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_receipt_no` (`receipt_no`),
    KEY `idx_pay_invoice` (`fee_invoice_id`),
    KEY `idx_pay_student` (`student_id`),
    KEY `idx_pay_date` (`payment_date`),
    CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pay_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pay_collector` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- 008 - Library, Transport & Hostel
-- ===========================================================================

-- ---- Library --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `book_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bookcat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `books` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `book_category_id` INT UNSIGNED DEFAULT NULL,
    `title`            VARCHAR(255) NOT NULL,
    `author`           VARCHAR(200) DEFAULT NULL,
    `publisher`        VARCHAR(200) DEFAULT NULL,
    `isbn`             VARCHAR(20) DEFAULT NULL,
    `edition`          VARCHAR(60) DEFAULT NULL,
    `rack_no`          VARCHAR(40) DEFAULT NULL,
    `total_copies`     INT UNSIGNED NOT NULL DEFAULT 1,
    `available_copies` INT UNSIGNED NOT NULL DEFAULT 1,
    `price`            DECIMAL(10,2) NOT NULL DEFAULT 0,
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_book_category` (`book_category_id`),
    KEY `idx_book_isbn` (`isbn`),
    KEY `idx_book_title` (`title`),
    CONSTRAINT `fk_book_category` FOREIGN KEY (`book_category_id`) REFERENCES `book_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_issues` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `book_id`     BIGINT UNSIGNED NOT NULL,
    `student_id`  BIGINT UNSIGNED DEFAULT NULL,
    `staff_id`    BIGINT UNSIGNED DEFAULT NULL,
    `issue_date`  DATE NOT NULL,
    `due_date`    DATE NOT NULL,
    `return_date` DATE DEFAULT NULL,
    `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `status`      ENUM('issued','returned','lost','overdue') NOT NULL DEFAULT 'issued',
    `issued_by`   BIGINT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_issue_book` (`book_id`),
    KEY `idx_issue_student` (`student_id`),
    KEY `idx_issue_status` (`status`),
    CONSTRAINT `fk_issue_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_issue_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_issue_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Transport ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transport_routes` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `start_point` VARCHAR(150) DEFAULT NULL,
    `end_point`   VARCHAR(150) DEFAULT NULL,
    `distance_km` DECIMAL(6,2) DEFAULT NULL,
    `fare`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_route_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicles` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `registration_no` VARCHAR(40) NOT NULL,
    `model`          VARCHAR(120) DEFAULT NULL,
    `capacity`       INT UNSIGNED NOT NULL DEFAULT 40,
    `driver_name`    VARCHAR(120) DEFAULT NULL,
    `driver_phone`   VARCHAR(20) DEFAULT NULL,
    `driver_license` VARCHAR(60) DEFAULT NULL,
    `route_id`       INT UNSIGNED DEFAULT NULL,
    `gps_device_id`  VARCHAR(80) DEFAULT NULL,
    `status`         ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    `created_at`     DATETIME DEFAULT NULL,
    `updated_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vehicle_reg` (`registration_no`),
    KEY `idx_vehicle_route` (`route_id`),
    CONSTRAINT `fk_vehicle_route` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transport_assignments` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`  BIGINT UNSIGNED NOT NULL,
    `route_id`    INT UNSIGNED NOT NULL,
    `vehicle_id`  INT UNSIGNED DEFAULT NULL,
    `pickup_point` VARCHAR(150) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transport_student` (`student_id`),
    KEY `idx_ta_route` (`route_id`),
    CONSTRAINT `fk_ta_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta_route` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Hostel ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hostels` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `type`       ENUM('boys','girls','mixed') NOT NULL DEFAULT 'boys',
    `warden_name` VARCHAR(120) DEFAULT NULL,
    `address`    VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hostel_rooms` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hostel_id`  INT UNSIGNED NOT NULL,
    `room_no`    VARCHAR(40) NOT NULL,
    `bed_count`  INT UNSIGNED NOT NULL DEFAULT 1,
    `occupied`   INT UNSIGNED NOT NULL DEFAULT 0,
    `fee`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hostel_room` (`hostel_id`, `room_no`),
    CONSTRAINT `fk_hroom_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hostel_allocations` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`  BIGINT UNSIGNED NOT NULL,
    `hostel_room_id` INT UNSIGNED NOT NULL,
    `allocated_date` DATE DEFAULT NULL,
    `vacated_date`   DATE DEFAULT NULL,
    `status`      ENUM('active','vacated') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_halloc_student` (`student_id`),
    KEY `idx_halloc_room` (`hostel_room_id`),
    CONSTRAINT `fk_halloc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_halloc_room` FOREIGN KEY (`hostel_room_id`) REFERENCES `hostel_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- 009 - Inventory & HR/Payroll
-- ===========================================================================

-- ---- Inventory ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `item_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_itemcat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vendors` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(120) DEFAULT NULL,
    `phone`      VARCHAR(20) DEFAULT NULL,
    `email`      VARCHAR(190) DEFAULT NULL,
    `address`    VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_category_id` INT UNSIGNED DEFAULT NULL,
    `name`             VARCHAR(150) NOT NULL,
    `sku`              VARCHAR(60) DEFAULT NULL,
    `unit`             VARCHAR(30) DEFAULT NULL,
    `quantity`         INT NOT NULL DEFAULT 0,
    `reorder_level`    INT NOT NULL DEFAULT 0,
    `unit_price`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `is_asset`         TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_item_category` (`item_category_id`),
    CONSTRAINT `fk_item_category` FOREIGN KEY (`item_category_id`) REFERENCES `item_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchases` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`   INT UNSIGNED DEFAULT NULL,
    `invoice_no`  VARCHAR(60) DEFAULT NULL,
    `purchase_date` DATE NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status`      ENUM('ordered','received','cancelled') NOT NULL DEFAULT 'received',
    `created_by`  BIGINT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_purchase_vendor` (`vendor_id`),
    CONSTRAINT `fk_purchase_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `inventory_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `description` VARCHAR(200) NOT NULL,
    `quantity`    INT NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_pi_purchase` (`purchase_id`),
    CONSTRAINT `fk_pi_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pi_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- HR / Payroll ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_types` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120) NOT NULL,
    `days_allowed` INT NOT NULL DEFAULT 0,
    `is_paid`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_leavetype_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leaves` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id`     BIGINT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED DEFAULT NULL,
    `start_date`   DATE NOT NULL,
    `end_date`     DATE NOT NULL,
    `days`         INT NOT NULL DEFAULT 1,
    `reason`       VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `approved_by`  BIGINT UNSIGNED DEFAULT NULL,
    `created_at`   DATETIME DEFAULT NULL,
    `updated_at`   DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_leave_staff` (`staff_id`),
    KEY `idx_leave_status` (`status`),
    CONSTRAINT `fk_leave_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `holidays` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `date`       DATE NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_holiday_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payrolls` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id`    BIGINT UNSIGNED NOT NULL,
    `month`       TINYINT UNSIGNED NOT NULL,
    `year`        SMALLINT UNSIGNED NOT NULL,
    `basic_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `allowances`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `deductions`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `net_salary`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status`      ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    `paid_at`     DATETIME DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payroll_period` (`staff_id`, `month`, `year`),
    KEY `idx_payroll_staff` (`staff_id`),
    CONSTRAINT `fk_payroll_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

SET FOREIGN_KEY_CHECKS=1;
