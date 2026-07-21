-- ===========================================================================
-- 012 - Advanced academic system: teacher assignments, student enrollments,
--       class teachers, public IDs, and duplicate-prevention constraints.
-- ===========================================================================

-- Teacher <-> (year, class, section, subject) assignment (subject/assistant).
CREATE TABLE IF NOT EXISTS `teacher_assignments` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT UNSIGNED NOT NULL,
    `class_id`         INT UNSIGNED NOT NULL,
    `section_id`       INT UNSIGNED NOT NULL,
    `subject_id`       INT UNSIGNED NOT NULL,
    `staff_id`         BIGINT UNSIGNED NOT NULL,
    `role`             ENUM('subject_teacher','assistant_teacher') NOT NULL DEFAULT 'subject_teacher',
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_teacher_assignment` (`academic_year_id`, `class_id`, `section_id`, `subject_id`, `staff_id`),
    KEY `idx_ta_staff` (`staff_id`),
    KEY `idx_ta_ctx` (`academic_year_id`, `class_id`, `section_id`),
    CONSTRAINT `fk_ta2_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta2_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta2_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta2_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta2_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exactly one class teacher per (year, class, section).
CREATE TABLE IF NOT EXISTS `class_teachers` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT UNSIGNED NOT NULL,
    `class_id`         INT UNSIGNED NOT NULL,
    `section_id`       INT UNSIGNED NOT NULL,
    `staff_id`         BIGINT UNSIGNED NOT NULL,
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_teacher` (`academic_year_id`, `class_id`, `section_id`),
    KEY `idx_ct_staff` (`staff_id`),
    CONSTRAINT `fk_ct_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ct_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ct_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ct_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historical student academic enrollment (one row per year); roll number is
-- unique within (year, class, section); one enrollment per student per year.
CREATE TABLE IF NOT EXISTS `student_enrollments` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`       BIGINT UNSIGNED NOT NULL,
    `academic_year_id` INT UNSIGNED NOT NULL,
    `class_id`         INT UNSIGNED NOT NULL,
    `section_id`       INT UNSIGNED NOT NULL,
    `roll_no`          INT UNSIGNED NOT NULL,
    `status`           ENUM('active','promoted','transferred','graduated','left') NOT NULL DEFAULT 'active',
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_enroll_rollno` (`academic_year_id`, `class_id`, `section_id`, `roll_no`),
    UNIQUE KEY `uq_enroll_student_year` (`academic_year_id`, `student_id`),
    KEY `idx_enroll_student` (`student_id`),
    CONSTRAINT `fk_enr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enr_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enr_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enr_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permanent public identifiers (never reused). admission_no/employee_code stay
-- as domain codes; public_id is the universal STU-/TCH-/PAR-/STF- identifier.
ALTER TABLE `users`     ADD COLUMN `public_id` VARCHAR(30) DEFAULT NULL;
ALTER TABLE `students`  ADD COLUMN `public_id` VARCHAR(30) DEFAULT NULL;
ALTER TABLE `staff`     ADD COLUMN `public_id` VARCHAR(30) DEFAULT NULL;
ALTER TABLE `guardians` ADD COLUMN `public_id` VARCHAR(30) DEFAULT NULL;

CREATE UNIQUE INDEX `uq_users_public_id`     ON `users` (`public_id`);
CREATE UNIQUE INDEX `uq_students_public_id`  ON `students` (`public_id`);
CREATE UNIQUE INDEX `uq_staff_public_id`     ON `staff` (`public_id`);
CREATE UNIQUE INDEX `uq_guardians_public_id` ON `guardians` (`public_id`);

-- Reserve issued public IDs even after deletion (never reuse).
CREATE TABLE IF NOT EXISTS `id_sequences` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `prefix`     VARCHAR(20) NOT NULL,
    `year`       SMALLINT UNSIGNED NOT NULL,
    `next_value` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_idseq_prefix_year` (`prefix`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
