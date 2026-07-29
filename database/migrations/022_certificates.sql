-- ===========================================================================
-- 022 - Digital certificate system.
--
-- Replaces paper Bonafide / Transfer / Leaving / Character / Study / custom
-- certificates with issued digital records. Each certificate has a unique public
-- id (CERT-YYYY-NNNNN), an immutable snapshot of the populated data, and backs a
-- public QR verification page. Additive & data-safe.
--
-- Certificate permissions (certificates.view / certificates.manage) are added in
-- the seeder alongside all other permissions.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `certificates` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cert_uid`         VARCHAR(24)  NOT NULL,
  `type`             VARCHAR(40)  NOT NULL,
  `title`            VARCHAR(150) NOT NULL,
  `student_id`       BIGINT UNSIGNED NOT NULL,
  `academic_year_id` BIGINT UNSIGNED DEFAULT NULL,
  `reference_no`     VARCHAR(60)  DEFAULT NULL,
  `body`             TEXT         DEFAULT NULL,
  `data_json`        TEXT         DEFAULT NULL,
  `status`           ENUM('issued','revoked') NOT NULL DEFAULT 'issued',
  `issued_by`        BIGINT UNSIGNED DEFAULT NULL,
  `issued_on`        DATE         NOT NULL,
  `revoked_reason`   VARCHAR(255) DEFAULT NULL,
  `created_at`       DATETIME DEFAULT NULL,
  `updated_at`       DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificates_uid` (`cert_uid`),
  KEY `idx_certificates_student` (`student_id`),
  KEY `idx_certificates_type` (`type`),
  CONSTRAINT `fk_certificates_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
