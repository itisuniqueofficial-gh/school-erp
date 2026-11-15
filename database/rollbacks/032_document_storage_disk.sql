-- Rollback of 032: drops student_documents.storage_disk.
-- Refused while any document lives on object storage: dropping the column would lose where those files are.
-- @guard: SELECT COUNT(*) FROM `student_documents` WHERE `storage_disk` <> 'local'
ALTER TABLE `student_documents` DROP COLUMN `storage_disk`;
