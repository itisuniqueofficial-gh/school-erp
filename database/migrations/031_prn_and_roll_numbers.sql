-- ===========================================================================
-- 031 - Permanent Roll Number (PRN) + class-wise roll number infrastructure.
--
-- PRN  = permanent student identity: YYYYMMDD(admission date) + admission
--        sequence (e.g. 202611150001). Globally unique (UNIQUE index), issued
--        once, never changed, never reused (prn_registry keeps every issued
--        PRN even if the student row is later hard-deleted).
-- Roll = per academic-year / class / section serial (student_enrollments.roll_no,
--        unique per year+class+section_key). roll_counters serialises
--        concurrent allocations; student_roll_history keeps every change.
--
-- Additive and data-safe: existing students are untouched here. PRNs for
-- existing students are filled by StudentIdentifierService::backfill(), which
-- Migrator runs right after this migration (and which can be re-run any time
-- with `php bin/console students:backfill-identifiers`).
-- ===========================================================================

ALTER TABLE `students` ADD COLUMN `prn` VARCHAR(24) DEFAULT NULL AFTER `uid`;
CREATE UNIQUE INDEX `uq_students_prn` ON `students` (`prn`);

-- Append-only ledger of every PRN ever issued. No FK on purpose: it must
-- outlive the student row.
CREATE TABLE IF NOT EXISTS `prn_registry` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `prn`            VARCHAR(24) NOT NULL,
    `student_id`     BIGINT UNSIGNED DEFAULT NULL,
    `student_uid`    CHAR(12) DEFAULT NULL,
    `admission_no`   VARCHAR(40) DEFAULT NULL,
    `admission_date` DATE DEFAULT NULL,
    `source`         ENUM('admission','backfill') NOT NULL DEFAULT 'admission',
    `notes`          VARCHAR(255) DEFAULT NULL,
    `issued_by`      BIGINT UNSIGNED DEFAULT NULL,
    `issued_at`      DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_prn_registry_prn` (`prn`),
    UNIQUE KEY `uq_prn_registry_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Lock anchor + high-water mark for roll numbers per (year, class, section).
CREATE TABLE IF NOT EXISTS `roll_counters` (
    `academic_year_id` INT UNSIGNED NOT NULL,
    `class_id`         INT UNSIGNED NOT NULL,
    `section_key`      INT UNSIGNED NOT NULL DEFAULT 0,
    `last_roll`        INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`academic_year_id`, `class_id`, `section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Every roll-number assignment/change. Historical rows are never edited.
CREATE TABLE IF NOT EXISTS `student_roll_history` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`       BIGINT UNSIGNED NOT NULL,
    `prn`              VARCHAR(24) DEFAULT NULL,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `class_id`         INT UNSIGNED DEFAULT NULL,
    `section_id`       INT UNSIGNED DEFAULT NULL,
    `old_class_id`     INT UNSIGNED DEFAULT NULL,
    `old_section_id`   INT UNSIGNED DEFAULT NULL,
    `old_roll_no`      INT UNSIGNED DEFAULT NULL,
    `new_roll_no`      INT UNSIGNED DEFAULT NULL,
    `change_type`      ENUM('assigned','manual','transfer','promotion','withdrawn','reactivated','resequence','backfill') NOT NULL,
    `reason`           VARCHAR(255) DEFAULT NULL,
    `changed_by`       BIGINT UNSIGNED DEFAULT NULL,
    `created_at`       DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_srh_student` (`student_id`),
    KEY `idx_srh_year_class` (`academic_year_id`, `class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Permission to change roll numbers manually (granted to roles that can already
-- reveal government IDs: super admin / school admin / principal).
INSERT IGNORE INTO `permissions` (`slug`, `name`, `module`, `created_at`, `updated_at`)
VALUES ('students.manage_roll', 'Manage roll numbers', 'students', NOW(), NOW());

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT rp.`role_id`, np.`id`
FROM `role_permissions` rp
JOIN `permissions` op ON op.`id` = rp.`permission_id` AND op.`slug` = 'students.reveal_id'
JOIN `permissions` np ON np.`slug` = 'students.manage_roll';

-- Defence in depth (best effort: skipped with a log line when the DB user lacks
-- the TRIGGER privilege under binary logging; the application and the
-- IntegrityService "prn_registry_mismatch" check enforce the same rules).
DROP TRIGGER IF EXISTS `trg_students_prn_immutable`;
DROP TRIGGER IF EXISTS `trg_prn_registry_no_update`;
DROP TRIGGER IF EXISTS `trg_prn_registry_no_delete`;

DELIMITER //
CREATE TRIGGER `trg_students_prn_immutable` BEFORE UPDATE ON `students` FOR EACH ROW
BEGIN
    IF OLD.`prn` IS NOT NULL AND NOT (NEW.`prn` <=> OLD.`prn`) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRN is permanent and cannot be changed';
    END IF;
END//
CREATE TRIGGER `trg_prn_registry_no_update` BEFORE UPDATE ON `prn_registry` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'prn_registry is append-only';
END//
CREATE TRIGGER `trg_prn_registry_no_delete` BEFORE DELETE ON `prn_registry` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'prn_registry is append-only';
END//
DELIMITER ;
