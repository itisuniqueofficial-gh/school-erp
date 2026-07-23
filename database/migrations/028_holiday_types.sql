-- ===========================================================================
-- 028 - Holiday categorisation.
--
-- Adds a category/type to holidays (national, festival, vacation, exam,
-- emergency, institute, general, ...) so the calendar can colour-code and
-- report on them. Additive & data-safe: existing holidays default to 'general'
-- and continue to block attendance exactly as before.
-- ===========================================================================

ALTER TABLE `holidays`
  ADD COLUMN `type` VARCHAR(40) NOT NULL DEFAULT 'general' AFTER `name`;

CREATE INDEX `idx_holidays_date` ON `holidays` (`date`);
