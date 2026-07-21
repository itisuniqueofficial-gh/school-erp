-- ===========================================================================
-- 013 - Workflow states: marks approval/lock, attendance unlock requests,
--       and student document verification.
-- ===========================================================================

-- Marks workflow status on each marks row (batch-transitioned per schedule).
ALTER TABLE `exam_marks`
    ADD COLUMN `status` ENUM('draft','submitted','under_review','correction_required','approved','published','locked')
    NOT NULL DEFAULT 'draft';

-- Attendance unlock requests (teacher asks admin to reopen a locked date).
CREATE TABLE IF NOT EXISTS `attendance_unlock_requests` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `class_id`         INT UNSIGNED NOT NULL,
    `section_id`       INT UNSIGNED NOT NULL,
    `date`             DATE NOT NULL,
    `requested_by`     BIGINT UNSIGNED NOT NULL,
    `reason`           VARCHAR(255) NOT NULL,
    `status`           ENUM('pending','approved','rejected','used') NOT NULL DEFAULT 'pending',
    `reviewed_by`      BIGINT UNSIGNED DEFAULT NULL,
    `reviewed_at`      DATETIME DEFAULT NULL,
    `expires_at`       DATETIME DEFAULT NULL,
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_aur_ctx` (`class_id`, `section_id`, `date`),
    KEY `idx_aur_status` (`status`),
    CONSTRAINT `fk_aur_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document verification workflow columns.
ALTER TABLE `student_documents`
    ADD COLUMN `verification_status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending';
ALTER TABLE `student_documents` ADD COLUMN `verified_by` BIGINT UNSIGNED DEFAULT NULL;
ALTER TABLE `student_documents` ADD COLUMN `verified_at` DATETIME DEFAULT NULL;
