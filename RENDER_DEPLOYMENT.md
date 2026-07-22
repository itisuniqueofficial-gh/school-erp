# Render Deployment

Render can deploy this repo as a **Docker web service** using the bundled
`Dockerfile` (no source changes needed).

## Steps

1. **Database** — create a managed MySQL (Render, PlanetScale, Aiven, etc.) and
   note its host/port/name/user/password.
2. **New → Web Service** → connect this GitHub repo.
3. **Runtime:** Docker (auto-detected from `Dockerfile`).
4. **Health check path:** `/health`.
5. **Environment variables** (Settings → Environment):

   | Key | Value |
   |-----|-------|
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_KEY` | `base64:...` (secret) |
   | `DB_HOST` / `DB_PORT` / `DB_DATABASE` | your MySQL |
   | `DB_USERNAME` / `DB_PASSWORD` | secret |
   | `DB_CONNECT_RETRIES` | `10` |
   | `SESSION_SECURE_COOKIE` | `true` |

   Leave `APP_URL` unset — the app auto-detects the Render URL (it honours
   `X-Forwarded-Proto`).
6. **Deploy.** The entrypoint runs a safe `upgrade` (backup + pending migrations)
   on boot. Seed the first admin once from the service **Shell**:
   `php bin/console seed`.
7. **Persistent uploads:** add a Render **Disk** mounted at
   `/var/www/html/storage/uploads`.

## Notes
- Auto-deploy on push is Render's default; every commit to the connected branch
  redeploys with zero-downtime health checks against `/health`.
- Custom domains + TLS are managed in Render; set `SESSION_SECURE_COOKIE=true`.
- See `ENVIRONMENT_VARIABLES.md` and `DOCKER_DEPLOYMENT.md`.
