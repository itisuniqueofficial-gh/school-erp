#!/usr/bin/env bash
# Restore the database from a SQL dump. USAGE: scripts/restore.sh <dump.sql>
# DESTRUCTIVE for the current schema data — always take a fresh backup first.
set -euo pipefail
cd "$(dirname "$0")/.."

FILE="${1:-}"
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
  echo "Usage: scripts/restore.sh <path-to-dump.sql>"; exit 1
fi

read -r -p "This will overwrite current database data with '$FILE'. Type 'yes' to continue: " ans
[ "$ans" = "yes" ] || { echo "Aborted."; exit 1; }

# Pull DB_* from .env if present.
if [ -f .env ]; then set -a; . <(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' .env); set +a; fi

mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" \
      -u "${DB_USERNAME:-root}" ${DB_PASSWORD:+-p"$DB_PASSWORD"} \
      "${DB_DATABASE:-school_erp}" < "$FILE"
echo "Restore complete from $FILE"
