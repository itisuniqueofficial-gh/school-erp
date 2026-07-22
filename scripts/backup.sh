#!/usr/bin/env bash
# Database backup — writes a timestamped SQL dump to storage/backups.
# Reads DB_* from .env (or the environment). Safe & read-only on data.
set -euo pipefail
cd "$(dirname "$0")/.."
exec php bin/console backup
