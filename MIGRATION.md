# Server Migration & Portability

**The ERP is three independent layers:** application code, database, and file
storage. You migrate the *application* between environments (Koyeb ⇄ VPS ⇄ cPanel
⇄ Docker ⇄ localhost) by pointing a new server at the **same** database and the
**same** persistent file storage — via environment variables only. No code change.

> SERVER CHANGE ≠ DATA MIGRATION.  CODE DEPLOYMENT ≠ DATABASE RESET.

## Environment-driven configuration (no hardcoding)

All connection details come from the environment (`.env` / platform secrets):

```
DB_DRIVER=mysql          # or pgsql (PostgreSQL supported)
DB_HOST=...              DB_PORT=3306
DB_DATABASE=...          DB_USERNAME=...   DB_PASSWORD=...
APP_URL=                 # blank = auto-detect; set to your domain in production
STORAGE_DRIVER=local     # local persistent storage (see STORAGE note below)
```

`APP_URL` drives every QR / verification URL, so links keep working after a
domain change. Never commit `.env`, credentials, or secrets.

## Moving the application to a new server (zero data loss)

1. **On the OLD server**, capture an integrity snapshot and a backup:
   ```bash
   php bin/console snapshot        # writes storage/backups/snapshots/snapshot_*.json
   php bin/console backup          # database dump -> storage/backups/
   ```
   Copy the snapshot JSON to the new server.
2. **Deploy the application code** to the NEW server (git pull / release artifact).
   Do **not** run `migrate:fresh`, `seed`, or any reset step.
3. **Point the new server at the existing production database + storage** by
   setting the same `DB_*` and storage env vars.
4. **Apply only pending schema changes** (safe/additive; backs up first):
   ```bash
   php bin/console upgrade
   ```
5. **Verify no data was lost** by comparing the source snapshot to the new server:
   ```bash
   php bin/console snapshot:compare /path/to/snapshot_from_old_server.json
   ```
   - `SAFE: no records were lost.` → integrity verified, proceed.
   - `UNSAFE: record loss detected` (exit 1) → **do not switch traffic**; investigate.
6. Confirm via **Admin → System Status** (`/admin/system/status`): connection,
   migration status, and record counts.
7. **Only now** switch DNS / reverse proxy to the new server. Keep the old server
   running for rollback until verification is complete.

## Rollback

- Application-only migration: point DNS back to the old server (database untouched).
- Never restore a database backup automatically — restore only on explicit,
  authorised action (`scripts/restore.sh` / Admin → Backups).

## Files / persistent storage

- Uploads, photos and generated PDFs live under `storage/` with paths recorded in
  the database. Keep `storage/` on a **persistent volume/disk** (VPS/cPanel) or a
  mounted volume (Docker). On ephemeral platforms (Koyeb containers) use an
  attached persistent volume or S3-compatible object storage and copy
  `storage/uploads` across during migration.

## IDs are preserved

Migration never regenerates IDs. User IDs, Student/Teacher/Staff IDs, admission
and roll numbers, certificate/report-card/hall-ticket/payment IDs all remain
exactly as stored — they are persisted in the database, not derived from the
server.

See also: `DATA_PERSISTENCE.md`, `DEPLOYMENT.md`, `TROUBLESHOOTING.md`.
