-- PostgreSQL-native version of 018 (the MySQL file uses a multi-table UPDATE alias,
-- LPAD on an integer, CAST ... AS UNSIGNED and ON DUPLICATE KEY, none of which exist here).
ALTER TABLE "exams" ADD COLUMN IF NOT EXISTS "uid" CHAR(12) DEFAULT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS "exams_uq_exams_uid" ON "exams" ("uid");

UPDATE "exams" e
SET "uid" = '9' || LPAD(((SELECT 1 + COUNT(*) FROM "exams" x WHERE x."id" < e."id"))::text, 11, '0')
WHERE e."uid" IS NULL;

INSERT INTO "id_sequences" ("prefix", "year", "next_value")
SELECT 'UID9', 0, COALESCE(MAX(CAST(SUBSTRING("uid" FROM 2) AS BIGINT)), 0) + 1 FROM "exams"
ON CONFLICT ("prefix", "year") DO UPDATE SET "next_value" = GREATEST("id_sequences"."next_value", EXCLUDED."next_value");
