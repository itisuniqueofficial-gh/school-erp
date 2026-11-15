-- PostgreSQL-native triggers for migration 031 (the MySQL triggers are skipped on PostgreSQL).
-- PRN is immutable once issued; prn_registry is append-only. Unlike MySQL under binary
-- logging, PostgreSQL lets the table owner create these without extra privileges.
DELIMITER //
CREATE OR REPLACE FUNCTION erp_students_prn_immutable() RETURNS trigger AS $$
BEGIN
    IF OLD."prn" IS NOT NULL AND NEW."prn" IS DISTINCT FROM OLD."prn" THEN
        RAISE EXCEPTION 'PRN is permanent and cannot be changed' USING ERRCODE = '23000';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql//
DROP TRIGGER IF EXISTS trg_students_prn_immutable ON "students"//
CREATE TRIGGER trg_students_prn_immutable BEFORE UPDATE ON "students" FOR EACH ROW EXECUTE FUNCTION erp_students_prn_immutable()//
CREATE OR REPLACE FUNCTION erp_prn_registry_append_only() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'prn_registry is append-only' USING ERRCODE = '23000';
END;
$$ LANGUAGE plpgsql//
DROP TRIGGER IF EXISTS trg_prn_registry_no_update ON "prn_registry"//
CREATE TRIGGER trg_prn_registry_no_update BEFORE UPDATE ON "prn_registry" FOR EACH ROW EXECUTE FUNCTION erp_prn_registry_append_only()//
DROP TRIGGER IF EXISTS trg_prn_registry_no_delete ON "prn_registry"//
CREATE TRIGGER trg_prn_registry_no_delete BEFORE DELETE ON "prn_registry" FOR EACH ROW EXECUTE FUNCTION erp_prn_registry_append_only()//
DELIMITER ;
