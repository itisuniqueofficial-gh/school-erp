-- ===========================================================================
-- 018 - Exam public ID (`uid`).
--
-- Exams are a major transactional entity and must carry an immutable, unique,
-- never-reused 12-digit public identifier like every other core entity
-- (see migration 017 / IdSequenceService::numeric). Leading digit 9 = exam.
--
-- This migration is additive and preserves existing data: the column is added
-- nullable + UNIQUE, any pre-existing exams are backfilled with sequential ids,
-- and the shared UID9 generator counter is advanced so future generation can
-- never collide with backfilled values.
-- ===========================================================================

ALTER TABLE `exams` ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`;
CREATE UNIQUE INDEX `uq_exams_uid` ON `exams` (`uid`);

-- Backfill existing exams with sequential 12-digit ids ('9' + 11-digit seq).
-- The derived table lets us reference `exams` while updating it.
UPDATE `exams` e
SET e.`uid` = CONCAT('9', LPAD((SELECT 1 + COUNT(*) FROM (SELECT `id` FROM `exams`) x WHERE x.`id` < e.`id`), 11, '0'))
WHERE e.`uid` IS NULL;

-- Advance the UID9 sequence counter to max(existing) + 1 so newly generated
-- exam ids never collide with the backfilled ones.
INSERT INTO `id_sequences` (`prefix`, `year`, `next_value`)
SELECT 'UID9', 0, COALESCE(MAX(CAST(SUBSTRING(`uid`, 2) AS UNSIGNED)), 0) + 1 FROM `exams`
ON DUPLICATE KEY UPDATE `next_value` = GREATEST(`id_sequences`.`next_value`, VALUES(`next_value`));
