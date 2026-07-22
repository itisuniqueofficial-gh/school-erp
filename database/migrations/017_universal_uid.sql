-- ===========================================================================
-- 017 - Universal 12-digit numeric public ID (`uid`).
--
-- Every core entity gets a globally-unique, immutable, never-reused 12-digit
-- numeric identifier. The leading digit(s) encode the entity type:
--   1=user 2=student 3=staff/teacher 4=class 5=section 6=subject
--   7=academic year 8=document. Uniqueness is enforced by a DB UNIQUE index;
--   generation is transaction + row-lock safe (see IdSequenceService::numeric).
-- ===========================================================================

ALTER TABLE `users`            ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;
ALTER TABLE `students`         ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;
ALTER TABLE `staff`            ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;
ALTER TABLE `classes`          ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;
ALTER TABLE `sections`         ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;
ALTER TABLE `subjects`         ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;
ALTER TABLE `academic_years`   ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;
ALTER TABLE `student_documents` ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;

CREATE UNIQUE INDEX `uq_users_uid`     ON `users` (`uid`);
CREATE UNIQUE INDEX `uq_students_uid`  ON `students` (`uid`);
CREATE UNIQUE INDEX `uq_staff_uid`     ON `staff` (`uid`);
CREATE UNIQUE INDEX `uq_classes_uid`   ON `classes` (`uid`);
CREATE UNIQUE INDEX `uq_sections_uid`  ON `sections` (`uid`);
CREATE UNIQUE INDEX `uq_subjects_uid`  ON `subjects` (`uid`);
CREATE UNIQUE INDEX `uq_years_uid`     ON `academic_years` (`uid`);
CREATE UNIQUE INDEX `uq_docs_uid`      ON `student_documents` (`uid`);
