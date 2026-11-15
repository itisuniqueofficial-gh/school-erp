-- Rollback of 033: removes only the dashboard permission. The RESTRICT foreign keys are deliberately
-- NOT reverted: switching back to CASCADE would re-open the door to silent loss of financial/academic history.
-- @guard: SELECT 0
DELETE FROM `role_permissions` WHERE `permission_id` IN (SELECT `id` FROM `permissions` WHERE `slug` = 'database.manage');
DELETE FROM `permissions` WHERE `slug` = 'database.manage';
