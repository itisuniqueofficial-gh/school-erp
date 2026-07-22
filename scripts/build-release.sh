#!/usr/bin/env bash
# ==========================================================================
# build-release.sh — generate production-ready deployment packages for every
# supported hosting platform from a single codebase.
#
#   scripts/build-release.sh [VERSION]      # VERSION defaults to $GITHUB_REF_NAME or "dev"
#
# Output: release/<platform>.(zip|tar.gz) + SHA256SUMS.txt
# Never packages: .env, .git, CI, tests, runtime storage, backups, secrets.
# ==========================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

VERSION="${1:-${GITHUB_REF_NAME:-dev}}"
OUT="release"
STAGE="$OUT/.stage/app"

echo "==> Building School ERP deployment packages ($VERSION)"
rm -rf "$OUT"
mkdir -p "$STAGE"

# ---- 1. Clean staging copy (no secrets / VCS / CI / tests / runtime data) --
rsync -a \
  --exclude '.git' --exclude '.github' --exclude 'release' \
  --exclude 'tests' --exclude '.playwright-mcp' --exclude 'node_modules' \
  --exclude '.env' --exclude '.env.local' \
  --exclude 'storage/logs/*'     --exclude 'storage/cache/*' \
  --exclude 'storage/sessions/*' --exclude 'storage/uploads/*' \
  --exclude 'storage/backups/*'  --exclude 'storage/temp/*' \
  ./ "$STAGE/"

# Keep empty runtime dirs present (with .gitkeep) so the app boots read-to-write.
for d in logs cache sessions backups temp uploads/avatars uploads/documents \
         uploads/student uploads/teacher uploads/school; do
  mkdir -p "$STAGE/storage/$d"; : > "$STAGE/storage/$d/.gitkeep"
done

# Safety assertion: the staging copy must not contain a .env or obvious secrets.
if [ -f "$STAGE/.env" ]; then echo "ERROR: .env leaked into package"; exit 1; fi

# ---- 2. Package helper ----------------------------------------------------
# make_pkg <name> <zip|tar> <guide-doc> <remove-path...>
make_pkg() {
  local name="$1" fmt="$2" guide="$3"; shift 3
  local d="$OUT/.pkg-$name"
  rm -rf "$d"; cp -a "$STAGE" "$d"
  for p in "$@"; do rm -rf "${d:?}/$p"; done
  printf 'School ERP %s — %s package\nSee %s and ENVIRONMENT_VARIABLES.md.\nUpgrade safely with: php bin/console upgrade\n' \
    "$VERSION" "$name" "$guide" > "$d/DEPLOY.txt"
  if [ "$fmt" = "zip" ]; then
    ( cd "$d" && zip -rqX "../${name}.zip" . )
  else
    tar czf "$OUT/${name}.tar.gz" -C "$d" .
  fi
  rm -rf "$d"
  echo "    - ${name}.${fmt/zip/zip}"
}

PAAS_ONLY="koyeb.yaml render.yaml railway.json"
CONTAINER="Dockerfile docker docker-compose.yml .dockerignore"

# ---- 3. Per-platform packages --------------------------------------------
# Full source (deployable) as zip + tarball.
make_pkg source     zip "docs/DEPLOYMENT.md"
make_pkg source     tar "docs/DEPLOYMENT.md"

# cPanel / shared hosting: no containers, no PaaS configs, no VPS server confs.
make_pkg cpanel     zip "CPANEL_DEPLOYMENT.md"       $CONTAINER $PAAS_ONLY deploy

# VPS / dedicated: keep deploy/ server configs + scripts; drop PaaS/container.
make_pkg vps        tar "VPS_DEPLOYMENT.md"          $PAAS_ONLY

# Docker & Docker Compose: keep container files; drop PaaS configs + deploy/.
make_pkg docker         zip "DOCKER_DEPLOYMENT.md"   $PAAS_ONLY deploy
make_pkg docker-compose zip "DOCKER_DEPLOYMENT.md"   $PAAS_ONLY deploy

# Managed PaaS: keep only the relevant platform config.
make_pkg koyeb      zip "KOYEB_DEPLOYMENT.md"        render.yaml railway.json deploy
make_pkg render     zip "RENDER_DEPLOYMENT.md"       koyeb.yaml railway.json deploy
make_pkg railway    zip "RAILWAY_DEPLOYMENT.md"      koyeb.yaml render.yaml deploy

# Local development bundle.
make_pkg local      zip "docs/INSTALL.md"            $PAAS_ONLY

# ---- 4. Docs-only bundle --------------------------------------------------
docs_dir="$OUT/.pkg-docs/school-erp-docs"
mkdir -p "$docs_dir"
cp -a docs "$docs_dir/" 2>/dev/null || true
cp -a ./*.md "$docs_dir/" 2>/dev/null || true
( cd "$OUT/.pkg-docs" && zip -rqX "../docs.zip" . )
rm -rf "$OUT/.pkg-docs"
echo "    - docs.zip"

# ---- 5. Checksums & cleanup ----------------------------------------------
rm -rf "$OUT/.stage"
( cd "$OUT" && sha256sum ./*.zip ./*.tar.gz > SHA256SUMS.txt )

echo "==> Done. Artifacts in $OUT/:"
( cd "$OUT" && ls -1 ) | sed 's/^/    /'
