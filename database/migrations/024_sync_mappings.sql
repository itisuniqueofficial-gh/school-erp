-- ===========================================================================
-- 024 - Government sync: external ID mapping + sync job tracking.
--
-- Backbone for duplicate-safe synchronisation with official government systems.
-- `external_mappings` links an internal ERP record to its external government
-- identifier (one-to-one per provider + entity type, enforced by UNIQUE keys) so
-- re-syncing updates the mapped record instead of creating duplicates.
-- `sync_jobs` records each batch run with created/updated/skipped/failed counts.
-- Additive & data-safe.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `external_mappings` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`    VARCHAR(40)  NOT NULL,
  `entity_type` VARCHAR(40)  NOT NULL,
  `entity_id`   BIGINT UNSIGNED NOT NULL,
  `external_id` VARCHAR(191) NOT NULL,
  `created_at`  DATETIME DEFAULT NULL,
  `updated_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_extmap_provider_type_external` (`provider`, `entity_type`, `external_id`),
  UNIQUE KEY `uq_extmap_provider_type_entity` (`provider`, `entity_type`, `entity_id`),
  KEY `idx_extmap_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `sync_jobs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`    VARCHAR(40)  NOT NULL,
  `operation`   VARCHAR(60)  NOT NULL,
  `direction`   ENUM('pull','push') NOT NULL DEFAULT 'pull',
  `status`      ENUM('pending','running','completed','partial','failed') NOT NULL DEFAULT 'pending',
  `total`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `message`     VARCHAR(500) DEFAULT NULL,
  `run_by`      BIGINT UNSIGNED DEFAULT NULL,
  `started_at`  DATETIME DEFAULT NULL,
  `finished_at` DATETIME DEFAULT NULL,
  `created_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sync_jobs_provider` (`provider`),
  KEY `idx_sync_jobs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
