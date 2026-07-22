-- ===========================================================================
-- 020 - Government integration framework + student digital-identity status.
--
-- Additive & data-safe: a sync-log ledger for future government API adapters
-- (UDISE+/APAAR/ABC/DigiLocker/NAD/EMIS/Scholarship) and per-student verification
-- status + extra optional national identifiers. No external APIs are hardcoded;
-- adapters read encrypted config from `settings` (group='integration').
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `government_sync_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`     VARCHAR(40)  NOT NULL,                 -- udise | apaar | abc | digilocker | nad | emis | scholarship
  `student_id`   BIGINT UNSIGNED DEFAULT NULL,
  `action`       VARCHAR(60)  NOT NULL,                 -- verify | push | pull | sync ...
  `status`       ENUM('pending','success','failed','retry') NOT NULL DEFAULT 'pending',
  `request_ref`  VARCHAR(120) DEFAULT NULL,             -- external request/txn id
  `message`      VARCHAR(500) DEFAULT NULL,
  `created_at`   DATETIME DEFAULT NULL,
  `updated_at`   DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gsl_provider` (`provider`),
  KEY `idx_gsl_student`  (`student_id`),
  KEY `idx_gsl_status`   (`status`),
  CONSTRAINT `fk_gsl_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-student digital-identity verification status (default 'pending').
ALTER TABLE `students`
  ADD COLUMN `apaar_status`         ENUM('pending','verified','rejected','expired','awaiting') NOT NULL DEFAULT 'pending' AFTER `apaar_id`,
  ADD COLUMN `abc_status`           ENUM('pending','verified','rejected','expired','awaiting') NOT NULL DEFAULT 'pending' AFTER `abc_id`,
  ADD COLUMN `digilocker_status`    ENUM('pending','verified','rejected','expired','awaiting') NOT NULL DEFAULT 'pending' AFTER `udise_id`,
  ADD COLUMN `state_student_id`     VARCHAR(60) DEFAULT NULL AFTER `udise_id`,
  ADD COLUMN `samagra_id`           VARCHAR(60) DEFAULT NULL AFTER `state_student_id`,
  ADD COLUMN `emis_number`          VARCHAR(60) DEFAULT NULL AFTER `samagra_id`,
  ADD COLUMN `board_registration_no` VARCHAR(60) DEFAULT NULL AFTER `emis_number`,
  ADD COLUMN `digilocker_ref`       VARCHAR(120) DEFAULT NULL AFTER `board_registration_no`;
