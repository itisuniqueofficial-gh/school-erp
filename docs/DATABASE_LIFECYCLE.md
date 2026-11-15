# Database lifecycle: install, update, repair, back up

Nobody imports SQL files by hand. Configure the connection once (installer or `.env`); the application creates
the schema, applies updates, verifies itself and backs itself up. Engines: MySQL, MariaDB, PostgreSQL.

## Install
`install.php` (browser) or `php bin/console install` creates the database if the user may, runs every migration,
seeds roles/permissions, creates the first administrator and locks the installer. A database that already has
users is never installed over. An empty or partially initialised database is completed additively (see Repair).

## Add or change a module (developers)
Every structural change is a numbered migration in `database/migrations/`, applied once, in order, recorded with a
SHA-256 checksum. Two formats, same result on every engine:

**Portable builder (`NNN_name.php`), recommended for new modules**
```php
return [
  'up' => function (Schema $s) {
    $s->create('library_fines', function (Blueprint $t) {
        $t->id();
        $t->foreignId('book_issue_id')->constrained('book_issues')->onDelete('restrict');
        $t->decimal('amount', 10, 2)->default(0);
        $t->enum('status', ['pending', 'paid', 'waived'])->default('pending');
        $t->timestamps();
        $t->unique(['book_issue_id', 'status']);
    });
  },
  'down' => function (Schema $s) { $s->dropIfEmpty('library_fines'); },   // refused while rows exist
];
```
The builder emits MySQL-flavoured DDL; PostgreSQL gets it through `PostgresTranslator` (identity columns, CHECK for
enums, STORED generated columns, schema-unique index names). `CREATE TABLE IF NOT EXISTS` means a module can never
create a duplicate table. Foreign keys default to `RESTRICT`.

**SQL (`NNN_name.sql`)** MySQL-flavoured SQL, translated automatically for PostgreSQL. Put native PostgreSQL
overrides in `database/migrations_pgsql/` when a statement cannot be translated.

After adding a migration: `php bin/console migrate && php bin/console db:schema:dump` and commit
`database/schema-manifest.json` (the expected structure used for validation). Never edit a migration that has been
released; add a new one (the checksum check reports edits).

## Deploy a new version
```bash
php bin/console upgrade            # preflight -> verified backup -> migrate under lock -> schema check
php bin/console upgrade --pretend  # show what would run, change nothing
```
Or open **Admin > Database**: it lists pending migrations and applies them after a verified backup (type APPLY).
While updates are pending the site shows a 503 "Database update required" page, except sign-in and Admin > Database.
Set `DB_AUTO_MIGRATE=true` to apply them automatically on the first request after a deploy (same backup policy).

| Setting | Meaning |
|---|---|
| `BACKUP_BEFORE_MIGRATE=required` (default) | no verified backup, no upgrade. `optional` continues with a warning; `off` skips |
| `DB_AUTO_MIGRATE=false` (default) | apply updates only on request |
| `BACKUP_RETENTION_COUNT=14` | newest manual/scheduled backups kept (0 = keep all); pre-migration backups: newest 5 |
| `BACKUP_PASSPHRASE=...` | encrypt every backup the application makes (AES-256-GCM) |

Concurrent deploys are safe: a database lock (`GET_LOCK` / `pg_advisory_lock`) lets one process migrate while the
others wait and then find nothing left to do.

## Failures
PostgreSQL: each migration is one transaction; a failure leaves the database unchanged. MySQL/MariaDB cannot roll
back DDL: the migration stays pending, nothing is recorded, completed statements are skipped on the next run, so the
fix is "correct the cause, run `upgrade` again". Details: `migration_log` table, `storage/logs/install.log`, the
dashboard. The verified pre-upgrade backup is the recovery point.

## Rollback
`php bin/console migrate:rollback` reverses the last batch, only where a script exists (`database/rollbacks/NNN_name.sql`
or a PHP `down`). Each script lists guards (`-- @guard: SELECT ...` must return 0) so a rollback that would discard data
is refused. Most migrations are intentionally irreversible; the safe-deletion foreign keys are never loosened.
`--allow-data-loss` exists for explicit, backed-up decisions only.

## Validate and repair
`php bin/console db:schema:validate` compares the live schema with `schema-manifest.json` (tables, columns,
nullability, unique keys, foreign keys with delete rules). `php bin/console migrate:repair` (or the dashboard)
re-applies all migrations idempotently after a verified backup: missing objects are created, existing ones skipped,
nothing removed. Also the path for databases that were created by hand or lost their history table.

## Deleting data
Foreign keys from students, staff, classes, sections, academic years, exams and invoices to fee, payment, marks,
attendance, report-card, certificate, enrollment, promotion and payroll records are `RESTRICT`: the database refuses
a delete that would erase history. The application soft-deletes students and staff. Only pure link rows (guardian
links, roles, tokens) cascade.

## Backups
```bash
php bin/console backup:run                      # verified backup, registered in database_backups
php bin/console backup:run --if-older-than=24   # cron: only if the newest verified backup is older than 24 h
php bin/console backup:list | backup:check <file> | backup:prune
php bin/console backup:test <file> --scratch-database=erp_restore_check   # prove it restores, in an isolated database
```
Cron example: `0 2 * * * cd /var/www/school-erp && php bin/console backup:run --if-older-than=20 >> storage/logs/backup.log 2>&1`.
Restoring never overwrites a database: create a new empty database, `php bin/console migrate`, then
`php bin/console db:import <file>`, check the report, and switch with `php bin/console config:database`.

## Dashboard (Admin > Database, permission `database.manage`)
Engine and version, connection status/latency, database name (never the password), schema version, tables, size,
pending migrations, migration history, health, backup status and register, storage use, recent database errors.
Actions: test connection, validate schema, create/verify backup, apply updates, additive repair. There is no SQL
console and no restore-over-live.
