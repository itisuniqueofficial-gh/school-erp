#!/usr/bin/env bash
# Ensure runtime storage is present and writable by the web-server user.
# USAGE: scripts/permission-fix.sh [web_user]   (default: www-data)
set -euo pipefail
cd "$(dirname "$0")/.."

WEB_USER="${1:-www-data}"

mkdir -p storage/logs storage/cache storage/sessions storage/backups storage/temp \
         storage/uploads/avatars storage/uploads/documents \
         storage/uploads/student storage/uploads/teacher storage/uploads/school

chmod -R 775 storage
if id "$WEB_USER" >/dev/null 2>&1; then
  chown -R "$WEB_USER":"$WEB_USER" storage 2>/dev/null || \
    echo "(could not chown to $WEB_USER — run as root/sudo if needed)"
fi
echo "Storage directories ensured and permissions set (775, owner: $WEB_USER)."
