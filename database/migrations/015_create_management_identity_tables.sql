-- ===========================================================================
-- 015 - Management & identification upgrade: human-friendly public IDs and
--       statuses for academic entities, plus teacher assignment history.
-- ===========================================================================

-- Human-friendly, stable public identifiers (never change after creation).
ALTER TABLE `academic_years` ADD COLUMN `public_id` VARCHAR(20) DEFAULT NULL AFTER `id`;
ALTER TABLE `academic_years` ADD COLUMN `status` ENUM('active','archived') NOT NULL DEFAULT 'active';
CREATE UNIQUE INDEX `uq_ay_public_id` ON `academic_years` (`public_id`);

ALTER TABLE `classes` ADD COLUMN `public_id` VARCHAR(20) DEFAULT NULL AFTER `id`;
CREATE UNIQUE INDEX `uq_class_public_id` ON `classes` (`public_id`);

ALTER TABLE `sections` ADD COLUMN `public_id` VARCHAR(20) DEFAULT NULL AFTER `id`;
ALTER TABLE `sections` ADD COLUMN `status` ENUM('active','archived') NOT NULL DEFAULT 'active';
CREATE UNIQUE INDEX `uq_section_public_id` ON `sections` (`public_id`);

ALTER TABLE `subjects` ADD COLUMN `public_id` VARCHAR(20) DEFAULT NULL AFTER `id`;
CREATE UNIQUE INDEX `uq_subject_public_id` ON `subjects` (`public_id`);

ALTER TABLE `class_subjects` ADD COLUMN `public_id` VARCHAR(20) DEFAULT NULL AFTER `id`;
ALTER TABLE `class_subjects` ADD COLUMN `status` ENUM('active','archived') NOT NULL DEFAULT 'active';
CREATE UNIQUE INDEX `uq_cs_public_id` ON `class_subjects` (`public_id`);

-- Teacher assignment ownership timeline. The teacher_assignments row remains
-- the single "current" record (preserving its UNIQUE key); each period of
-- ownership is recorded here so reassignments keep full history + effective
-- dates without ever destroying data.
CREATE TABLE IF NOT EXISTS `teacher_assignment_history` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assignment_id` BIGINT UNSIGNED NOT NULL,
    `staff_id`      BIGINT UNSIGNED NOT NULL,
    `role`          ENUM('subject_teacher','assistant_teacher') NOT NULL DEFAULT 'subject_teacher',
    `start_date`    DATE NOT NULL,
    `end_date`      DATE DEFAULT NULL,
    `reason`        VARCHAR(255) DEFAULT NULL,
    `status`        ENUM('active','ended') NOT NULL DEFAULT 'active',
    `changed_by`    BIGINT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tah_assignment` (`assignment_id`),
    KEY `idx_tah_staff` (`staff_id`),
    CONSTRAINT `fk_tah_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tah_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
