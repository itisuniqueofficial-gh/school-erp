-- ===========================================================================
-- 030 - Student Personal File: configurable document requirements + per-student
--       "not applicable" exclusions.
--
-- Replaces the hardcoded DocumentService::REQUIRED checklist with a configurable
-- table (admins manage the checklist from Settings). `student_documents` (with
-- its verification_status) remains the store for actual uploaded files, matched
-- to a requirement by `type` = requirement `code`. Additive & data-safe.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `document_requirements` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`               VARCHAR(60)  NOT NULL,
  `name`               VARCHAR(150) NOT NULL,
  `category`           VARCHAR(60)  NOT NULL DEFAULT 'general',
  `is_required`        TINYINT(1)   NOT NULL DEFAULT 1,
  `signature_required` TINYINT(1)   NOT NULL DEFAULT 0,
  `physical_required`  TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`         INT          NOT NULL DEFAULT 0,
  `active`             TINYINT(1)   NOT NULL DEFAULT 1,
  `description`        VARCHAR(255) DEFAULT NULL,
  `created_at`         DATETIME     DEFAULT NULL,
  `updated_at`         DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_docreq_code` (`code`),
  KEY `idx_docreq_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `student_document_exclusions` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       BIGINT UNSIGNED NOT NULL,
  `requirement_code` VARCHAR(60)  NOT NULL,
  `reason`           VARCHAR(255) DEFAULT NULL,
  `excluded_by`      BIGINT UNSIGNED DEFAULT NULL,
  `created_at`       DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sde_student_code` (`student_id`, `requirement_code`),
  KEY `idx_sde_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
