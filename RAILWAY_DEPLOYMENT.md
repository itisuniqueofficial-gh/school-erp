# Railway Deployment

Railway deploys this repo from the bundled `Dockerfile`.

## Steps

1. **New Project → Deploy from GitHub repo** → select this repo. Railway detects
   the `Dockerfile`.
2. **Add a MySQL plugin** (or use an external MySQL). Railway exposes connection
   variables you can reference.
3. **Variables** (service → Variables):

   | Key | Value |
   |-----|-------|
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_KEY` | `base64:...` |
   | `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
   | `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
   | `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
   | `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
   | `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
   | `DB_CONNECT_RETRIES` | `10` |
   | `SESSION_SECURE_COOKIE` | `true` |

   Leave `APP_URL` unset (auto-detected; honours `X-Forwarded-Proto`).
4. **Networking → Generate Domain**, and set the **Healthcheck Path** to
   `/health`.
5. **Deploy.** The entrypoint runs a safe `upgrade` on boot. Seed the first admin
   once from the service shell: `php bin/console seed`.
6. **Persistent uploads:** attach a Railway **Volume** mounted at
   `/var/www/html/storage/uploads`.

## Notes
- Railway binds the container to `$PORT`; the entrypoint handles this.
- Upgrades are non-destructive; `migrate:fresh` is blocked in production.
- See `ENVIRONMENT_VARIABLES.md` and `DOCKER_DEPLOYMENT.md`.
