-- ===========================================================================
-- 021 - Report card identity, versioning & verification.
--
-- Additive & data-safe. `report_cards` keeps ONE current row per (exam, student)
-- (its existing UNIQUE + FK are untouched) and gains a pointer to the current
-- version. Immutable version history + unique public ids live in a new
-- `report_card_versions` table, which backs the public QR verification page.
-- Regenerating inserts a new version row (history preserved) and updates the
-- current pointer.
-- ===========================================================================

ALTER TABLE `report_cards`
  ADD COLUMN `rc_uid`       VARCHAR(24) DEFAULT NULL AFTER `id`,
  ADD COLUMN `version`      INT UNSIGNED NOT NULL DEFAULT 1 AFTER `student_id`,
  ADD COLUMN `generated_by` BIGINT UNSIGNED DEFAULT NULL AFTER `result`,
  ADD COLUMN `reason`       VARCHAR(255) DEFAULT NULL AFTER `generated_by`;

CREATE TABLE IF NOT EXISTS `report_card_versions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rc_uid`         VARCHAR(24)  NOT NULL,
  `exam_id`        BIGINT UNSIGNED NOT NULL,
  `student_id`     BIGINT UNSIGNED NOT NULL,
  `version`        INT UNSIGNED NOT NULL DEFAULT 1,
  `total_marks`    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `obtained_marks` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `percentage`     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `grade_id`       INT UNSIGNED DEFAULT NULL,
  `result`         ENUM('pass','fail','pending') NOT NULL DEFAULT 'pending',
  `generated_by`   BIGINT UNSIGNED DEFAULT NULL,
  `reason`         VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rcv_uid` (`rc_uid`),
  UNIQUE KEY `uq_rcv_exam_student_version` (`exam_id`, `student_id`, `version`),
  KEY `idx_rcv_student` (`student_id`),
  CONSTRAINT `fk_rcv_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
