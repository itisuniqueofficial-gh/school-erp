-- ===========================================================================
-- 032 - Remember which storage disk holds each student document.
--
-- Uploads can live on the local filesystem or in S3-compatible object storage
-- (AWS S3, Cloudflare R2, ...). Each document records its own disk, so changing
-- FILESYSTEM_DISK later never orphans files uploaded earlier. Existing rows
-- default to 'local' (where they already are). Additive; no data is changed.
-- ===========================================================================

ALTER TABLE `student_documents` ADD COLUMN `storage_disk` VARCHAR(20) NOT NULL DEFAULT 'local';
