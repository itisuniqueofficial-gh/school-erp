-- ===========================================================================
-- 027 - Historical branding snapshot on issued certificates.
--
-- Official-document integrity: a certificate issued today must keep the school
-- branding (name/address/contacts/board/logo/stamp/signature references) that
-- was in effect when it was issued, even if the school later changes its logo
-- or details. Additive & data-safe: the column is nullable, so every existing
-- certificate keeps working and simply falls back to the current branding until
-- it is (optionally) re-snapshotted.
-- ===========================================================================

ALTER TABLE `certificates`
  ADD COLUMN `branding_snapshot` TEXT NULL AFTER `data_json`;
