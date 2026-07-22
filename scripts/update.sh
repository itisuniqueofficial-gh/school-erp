#!/usr/bin/env bash
# Safe update: pull latest code (if a git checkout), then run the zero-data-loss
# upgrade (automatic backup + pending additive migrations only) and health check.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -d .git ]; then
  echo "==> Pulling latest code..."
  git pull --ff-only || echo "(skip git pull — resolve manually if needed)"
fi

echo "==> Running safe upgrade (backup + pending migrations only)..."
php bin/console upgrade

echo "==> Health check..."
php bin/console health
echo "Update complete."
