-- ===========================================================================
-- 029 - Per-scope configuration overrides (Global + Local settings).
--
-- Lets a class or section override selected global settings (working week,
-- times, period structure) WITHOUT affecting other classes or the global
-- default. New table only — additive & data-safe. Absence of a row means the
-- scope inherits the global value, so existing behaviour is unchanged.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `scope_settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope_type` VARCHAR(20)  NOT NULL,          -- 'class' | 'section'
  `scope_id`   INT UNSIGNED NOT NULL,
  `key`        VARCHAR(80)  NOT NULL,
  `value`      TEXT         NULL,
  `created_at` DATETIME     NULL,
  `updated_at` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_scope_key` (`scope_type`, `scope_id`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
