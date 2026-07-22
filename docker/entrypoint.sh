#!/usr/bin/env bash
# ==========================================================================
# Container startup for the School ERP.
#  - binds Apache to Koyeb's $PORT
#  - ensures runtime storage folders exist and are writable
#  - generates APP_KEY if none is configured
#  - runs pending migrations (best-effort; the app shows a friendly 503 if the
#    database is briefly unavailable, and Connection retries on first use)
# ==========================================================================
set -euo pipefail
cd /var/www/html

PORT="${PORT:-8000}"

# --- Bind Apache to the platform port -------------------------------------
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s!<VirtualHost \*:[0-9]+>!<VirtualHost *:${PORT}>!" /etc/apache2/sites-available/000-default.conf

# --- Ensure writable runtime storage --------------------------------------
mkdir -p storage/logs storage/cache storage/sessions \
         storage/uploads/avatars storage/uploads/documents storage/backups
chown -R www-data:www-data storage || true
chmod -R 775 storage || true

# --- Application key (only if neither env nor .env provides one) -----------
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=.+' .env 2>/dev/null; then
    echo "[entrypoint] Generating APP_KEY..."
    php bin/console key:generate || echo "[entrypoint] key:generate failed (continuing)"
fi

# --- Database migrations (best-effort) ------------------------------------
# Retry the DB a few times so a cold managed database has time to accept
# connections before we give up on migrating this boot.
export DB_CONNECT_RETRIES="${DB_CONNECT_RETRIES:-10}"
if php bin/console upgrade; then
    echo "[entrypoint] Safe upgrade complete (backup + pending migrations only)."
else
    echo "[entrypoint] Upgrade skipped/failed; the app will surface a 503 until the DB is reachable."
fi

echo "[entrypoint] Starting Apache on port ${PORT}..."
exec apache2-foreground
