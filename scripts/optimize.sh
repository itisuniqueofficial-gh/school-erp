#!/usr/bin/env bash
# Production optimization: optimized autoloader (if Composer present) and clear
# transient caches/sessions. Non-destructive to application data.
set -euo pipefail
cd "$(dirname "$0")/.."

if command -v composer >/dev/null 2>&1 && [ -f composer.json ]; then
  echo "==> composer install --no-dev --optimize-autoloader"
  composer install --no-dev --optimize-autoloader --no-interaction || true
  composer dump-autoload -o --no-interaction || true
else
  echo "(Composer not found — using the built-in PSR-4 autoloader)"
fi

echo "==> Clearing caches & expired sessions..."
rm -rf storage/cache/* 2>/dev/null || true
php bin/console cleanup:sessions >/dev/null 2>&1 || true
echo "Optimization complete."
