# Data Persistence & Zero-Data-Loss Guarantee

**Rule: CODE DEPLOYMENT ≠ DATABASE RESET.** Updating, restarting, rebuilding, or
redeploying the ERP never deletes or resets production data.

## Root cause of earlier data loss (fixed)

The application's own deployment path was already safe:

- Migrations are **incremental and additive** (tracked in the `migrations` table; each runs once).
- `php bin/console install` **detects an existing installation** and refuses to reseed (see `InstallService`).
- `php bin/console upgrade` takes a **backup first**, then applies only pending migrations.
- Server restart / redeploy runs **no** reset logic.

The actual cause of records disappearing was **destructive test runs against the
live database**: `php bin/console migrate:fresh` (which DROPS ALL TABLES) was run
on the same `school_erp` database the site used, because the guard only blocked
`APP_ENV=production`.

### The fix (two layers)

1. **Test-database isolation.** Exporting `APP_TESTING=1` transparently switches
   the connection to `<DB_DATABASE>_test` (e.g. `school_erp_test`) and the
   `testing` environment. The automated test suite runs there and can **never**
   touch the real database.

   ```bash
   APP_TESTING=1 php bin/console migrate:fresh   # operates on school_erp_test
   APP_TESTING=1 php tests/integration_hallticket.php
   ```

2. **Hardened `migrate:fresh` guard.** It now refuses to drop tables when the
   target database is production **or** is a non-`_test` database that already
   contains records — unless `--force` is passed explicitly.

   ```
   REFUSED: migrate:fresh drops ALL tables and would destroy existing data.
   Target database: 'school_erp' (contains records).
   ```

## Safe operational commands

| Purpose | Command | Destructive? |
|---|---|---|
| Apply pending migrations | `php bin/console migrate` | No (additive) |
| Production upgrade (backup + migrate) | `php bin/console upgrade` | No |
| Manual backup | `php bin/console backup` | No |
| Install state / counts | `php bin/console install:status` | No |
| Health check | `php bin/console health` | No |
| Rebuild schema (**tests only**) | `APP_TESTING=1 php bin/console migrate:fresh` | Test DB only |

## Backups

- Backups are written to `storage/backups/` (`php bin/console backup`, or
  Admin → Backups). `bin/console upgrade` backs up automatically before migrating.
- Restore: Admin → Backups → Restore, or `scripts/restore.sh`.
- Keep `storage/` on **persistent** storage (volume/disk), never ephemeral
  container layers.

## Admin visibility

**Admin → System Status** (`/admin/system/status`) shows environment, driver,
database name/host, connection + migration status, record counts (for
post-deploy verification) and the last backup — **never** credentials or secrets.

## Deployment persistence checklist

- Use an external managed **MySQL/PostgreSQL** for containers (Koyeb/Docker); do
  not rely on ephemeral container storage for the database or `storage/uploads`.
- Deploy **code only**; never run `migrate:fresh`, `seed`, or DB-reset steps in
  deploy pipelines. Use `php bin/console upgrade`.
- After deploy, open **System Status** and confirm record counts are unchanged.
