# Troubleshooting

Run `php bin/console health` (or `GET /health`) first — it reports database,
storage, cache, migrations, and environment status.

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| **503 "Unable to connect to the database"** | DB down/unreachable or wrong `DB_*` | Verify `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`; ensure the DB accepts remote connections; raise `DB_CONNECT_RETRIES`. The 503 page is expected behaviour (no stack trace leaked). |
| **Blank page / HTTP 500** | `storage/` not writable, or `APP_DEBUG=false` hiding detail | Make `storage/` writable; check `storage/logs` and platform logs. Temporarily set `APP_DEBUG=true` in a non-prod env. |
| **Routes return 404 (Apache)** | `mod_rewrite` off / `AllowOverride` not `All` | `a2enmod rewrite`; set `AllowOverride All`; docroot must be `public/`. |
| **Assets 404 / CSP blocks assets** | Docroot not `public/`, or wrong `APP_URL` | Point docroot at `public/`. Leave `APP_URL` blank to auto-detect (assets become same-origin). |
| **Login rejected for a valid User ID** | (fixed) numeric login validator | Ensure you're on ≥ v1.2.0; login accepts 12-digit User ID, email, or username. |
| **"Too many failed attempts"** | Login lockout after 5 fails | Wait for the window, or clear the user's `login_attempts` rows. |
| **Uploads/photos disappear after redeploy** | No persistent volume | Mount a volume at `storage/uploads` (Docker/Koyeb/Render/Railway). |
| **Encrypted fields error / payment creds unreadable** | `APP_KEY` changed between deploys | Keep a **stable** `APP_KEY`; do not regenerate it each boot. |
| **Payment gateway page shows a PHP warning** | (fixed) unconfigured gateway | Ensure ≥ v1.2.0; config is normalized and mode always defaults to `test`. |
| **Installer keeps reappearing** | `storage/installed.lock` missing/unreadable | Recreate the lock (or complete the wizard); it disables itself after install. |
| **Migration failed mid-deploy** | Bad/partial migration | Restore the pre-upgrade backup (Admin → Backups); `upgrade` takes one automatically. Migrations are additive-only. |
| **`migrate:fresh` refused** | Guard in production | Intentional — it drops all tables. Use `php bin/console upgrade` instead, or pass `--force` only in a throwaway env. |
| **CI fails on "possible secret"** | A secret/`.env` was committed | Remove it from the tree and history; use env vars / GitHub Secrets. |
| **Emails not sent** | `MAIL_*` unset | Configure SMTP in env; check `communication_logs`. |
| **Slow pages** | OPcache off / missing indexes | Enable OPcache in prod; the schema indexes all FKs/lookup columns. |

## Where to look
- App logs: `storage/logs/erp-YYYY-MM-DD.log`
- Audit trail: **Admin → Audit Log** (`/admin/audit`)
- Health: `GET /health` and `php bin/console health`
- Migration status: `php bin/console migrate:status`

See also `docs/DEPLOYMENT.md`, `KOYEB_DEPLOYMENT.md`, `DOCKER_DEPLOYMENT.md`,
`SECURITY.md`, and `RELEASE.md`.
