#!/usr/bin/env bash
# System health check (DB, storage, cache, migrations, env). Exits non-zero if
# any critical check fails — suitable for cron/monitoring.
set -euo pipefail
cd "$(dirname "$0")/.."
exec php bin/console health
