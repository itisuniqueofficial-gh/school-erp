-- ===========================================================================
-- 026 - Class-subject structure: typing, mandatory flag & ordering.
--
-- Lets each class have a differentiated subject structure (core / optional /
-- elective / extra / practical / language) with a mandatory flag and display
-- order. Additive & data-safe: existing allocations default to a mandatory core
-- subject, preserving current behaviour. The existing UNIQUE(class_id,subject_id)
-- still prevents duplicate allocations.
-- ===========================================================================

ALTER TABLE `class_subjects`
  ADD COLUMN `subject_type` ENUM('core','optional','elective','extra','practical','language') NOT NULL DEFAULT 'core' AFTER `subject_id`,
  ADD COLUMN `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1 AFTER `subject_type`,
  ADD COLUMN `sort_order`   INT NOT NULL DEFAULT 0 AFTER `is_mandatory`;

CREATE INDEX `idx_cs_class_order` ON `class_subjects` (`class_id`, `sort_order`);
