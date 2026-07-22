-- ===========================================================================
-- 016 - Section-optional architecture.
--
-- A class may or may not use sections. `section_id` becomes NULLable across the
-- relationship tables, and a STORED generated column `section_key` =
-- COALESCE(section_id,0) preserves uniqueness even when section is NULL
-- (MySQL treats NULLs as distinct in UNIQUE indexes, which would otherwise
-- allow duplicate roll numbers / assignments for a no-section class).
-- ===========================================================================

-- Per-class flag: does this class use sections?
ALTER TABLE `classes` ADD COLUMN `uses_sections` TINYINT(1) NOT NULL DEFAULT 1 AFTER `numeric_level`;

-- --- Student enrollments -----------------------------------------------------
ALTER TABLE `student_enrollments` DROP INDEX `uq_enroll_rollno`;
ALTER TABLE `student_enrollments` MODIFY `section_id` INT UNSIGNED NULL;
ALTER TABLE `student_enrollments` ADD COLUMN `section_key` INT UNSIGNED AS (COALESCE(`section_id`, 0)) VIRTUAL;
ALTER TABLE `student_enrollments` ADD UNIQUE KEY `uq_enroll_rollno` (`academic_year_id`, `class_id`, `section_key`, `roll_no`);

-- --- Teacher assignments -----------------------------------------------------
ALTER TABLE `teacher_assignments` DROP INDEX `uq_teacher_assignment`;
ALTER TABLE `teacher_assignments` MODIFY `section_id` INT UNSIGNED NULL;
ALTER TABLE `teacher_assignments` ADD COLUMN `section_key` INT UNSIGNED AS (COALESCE(`section_id`, 0)) VIRTUAL;
ALTER TABLE `teacher_assignments` ADD UNIQUE KEY `uq_teacher_assignment` (`academic_year_id`, `class_id`, `section_key`, `subject_id`, `staff_id`);

-- --- Class teachers ----------------------------------------------------------
-- Add FK-backing indexes first: the unique index is the only index backing the
-- year/class/section foreign keys, so it cannot be dropped until replacements exist.
ALTER TABLE `class_teachers`
    ADD KEY `idx_ct_year` (`academic_year_id`),
    ADD KEY `idx_ct_class` (`class_id`),
    ADD KEY `idx_ct_section` (`section_id`);
ALTER TABLE `class_teachers` DROP INDEX `uq_class_teacher`;
ALTER TABLE `class_teachers` MODIFY `section_id` INT UNSIGNED NULL;
ALTER TABLE `class_teachers` ADD COLUMN `section_key` INT UNSIGNED AS (COALESCE(`section_id`, 0)) VIRTUAL;
ALTER TABLE `class_teachers` ADD UNIQUE KEY `uq_class_teacher` (`academic_year_id`, `class_id`, `section_key`);

-- --- Subject attendance ------------------------------------------------------
ALTER TABLE `subject_attendance`
    ADD KEY `idx_subatt_year` (`academic_year_id`),
    ADD KEY `idx_subatt_section` (`section_id`);
ALTER TABLE `subject_attendance` DROP INDEX `uq_subject_attendance`;
ALTER TABLE `subject_attendance` MODIFY `section_id` INT UNSIGNED NULL;
ALTER TABLE `subject_attendance` ADD COLUMN `section_key` INT UNSIGNED AS (COALESCE(`section_id`, 0)) VIRTUAL;
ALTER TABLE `subject_attendance` ADD UNIQUE KEY `uq_subject_attendance` (`academic_year_id`, `class_id`, `section_key`, `subject_id`, `date`, `period`, `student_id`);
