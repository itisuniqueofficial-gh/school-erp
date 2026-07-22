# Koyeb Deployment Guide — School ERP

This app ships a production `Dockerfile` (PHP 8.3 + Apache, document root `public/`)
and a `koyeb.yaml` service definition. Configuration is entirely via environment
variables — **no `.env` file is baked into the image**.

---

## 1. Prerequisites

- A Koyeb account and the [Koyeb CLI](https://www.koyeb.com/docs/cli/installation) (optional).
- A MySQL 8.0+ / MariaDB 10.6+ database reachable from Koyeb (e.g. Koyeb Managed
  Postgres is not applicable — use a managed MySQL such as PlanetScale, Aiven,
  Railway, or your own).

## 2. Create the database

Create an empty database and a user with full privileges on it:

```sql
CREATE DATABASE school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'%' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON school_erp.* TO 'erp'@'%';
FLUSH PRIVILEGES;
```

## 3. Generate an APP_KEY

`APP_KEY` encrypts sensitive data (gov IDs, payment credentials). Generate one locally:

```bash
php bin/console key:generate      # writes to .env; copy the value
# or:
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

## 4. Deploy

### Option A — Git-driven (recommended)
1. Push this repo to GitHub/GitLab.
2. In the Koyeb dashboard: **Create Service → GitHub →** select the repo.
3. Builder: **Dockerfile** (auto-detected).
4. Add the environment variables from section 5 (mark secrets as *Secret*).
5. Set the health check to **HTTP `/health`** on the service port.
6. Deploy. Auto-deploy on push is enabled via `koyeb.yaml`.

### Option B — CLI
```bash
koyeb app init school-erp \
  --docker . \
  --ports 8000:http \
  --routes /:8000 \
  --env APP_ENV=production --env APP_DEBUG=false \
  --env DB_HOST=@erp-db-host --env DB_DATABASE=@erp-db-name \
  --env DB_USERNAME=@erp-db-user --env DB_PASSWORD=@erp-db-pass \
  --env APP_KEY=@erp-app-key --env APP_URL=https://your-domain
```

## 5. Environment variables

See `ENVIRONMENT_VARIABLES.md` for the full list. Minimum required:

| Key | Example | Notes |
|-----|---------|-------|
| `APP_ENV` | `production` | Disables debug output |
| `APP_DEBUG` | `false` | **Must be false in production** |
| `APP_KEY` | `base64:...` | Secret — encryption key |
| `APP_URL` | `https://erp.example.com` | Used for links/cookies |
| `DB_HOST` | `mysql.example.com` | |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `school_erp` | |
| `DB_USERNAME` | `erp` | Secret |
| `DB_PASSWORD` | `••••` | Secret |
| `DB_CONNECT_RETRIES` | `10` | Retries on cold DB at boot |

## 6. Startup behaviour

On every boot the entrypoint (`docker/entrypoint.sh`):
1. Binds Apache to Koyeb's `$PORT`.
2. Ensures `storage/{logs,cache,sessions,uploads,backups}` exist and are writable.
3. Generates `APP_KEY` if none is provided (prefer setting it explicitly).
4. Runs pending migrations (`php bin/console migrate`) with DB retry.

The first deploy runs migrations automatically. Seed the baseline admin/roles once:

```bash
# one-off, from the Koyeb service shell:
php bin/console seed
```

## 7. Persistent storage

Uploaded files (photos, documents, receipts, logos) live under
`storage/uploads`. Attach a Koyeb **Volume** mounted at
`/var/www/html/storage/uploads` (declared in `koyeb.yaml`) so files survive
redeploys and restarts.

## 8. Custom domain & SSL

Add your domain under the service's **Domains** tab; Koyeb provisions a
Let's Encrypt certificate automatically. Set `APP_URL` to the `https://` domain
and `SESSION_SECURE_COOKIE=true`.

## 9. Health, logs, restart, scaling

- **Health:** `GET /health` returns `200` when DB + storage + cache are OK, `503`
  otherwise (JSON body includes per-check status, version, PHP, memory, disk,
  response time). Koyeb uses it for zero-downtime deploys and auto-restart.
- **Logs:** view in the Koyeb dashboard (stdout/stderr) plus app logs in
  `storage/logs`.
- **Restart:** the app is stateless apart from the uploads volume; it survives
  restarts. Koyeb restarts unhealthy instances automatically (`restart_limit`).
- **Scaling:** `koyeb.yaml` sets `min: 1, max: 3`. Sessions are file-based by
  default — for >1 instance, use sticky sessions or move sessions to the DB.

## 10. Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| 503 "Unable to connect to the database" | Check `DB_*` vars & that the DB accepts remote connections; raise `DB_CONNECT_RETRIES`. |
| Assets 404 | Ensure the build used the Dockerfile (docroot is `public/`). |
| "APP_KEY" errors on encrypted fields | Set a stable `APP_KEY`; do not let it regenerate each boot. |
| Uploads disappear after redeploy | Attach the persistent volume at `storage/uploads`. |
| Blank page / 500 | `APP_DEBUG=false` hides details; check Koyeb logs + `storage/logs`. |
