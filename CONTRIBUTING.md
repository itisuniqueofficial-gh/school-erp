# Contributing

Thanks for improving School ERP. This project uses a simple, protected-`main`
workflow with automated CI and releases.

## Branch strategy

- **`main`** — always deployable. Protected: no direct force-push; merges require
  a passing CI run (and PR review where enforced).
- **`feat/*`, `fix/*`, `chore/*`** — short-lived feature/fix branches off `main`.
- **`release/*`** — optional stabilisation branches for larger releases.

Open a Pull Request into `main`. CI (`.github/workflows/ci.yml`) runs on every
push and PR.

## Conventional commits

Commit messages drive the changelog and version bumps:

| Prefix | Example | Version impact |
|--------|---------|----------------|
| `feat:` | `feat: add scholarship module` | **minor** |
| `fix:` | `fix: correct grade rounding` | **patch** |
| `feat!:` / `BREAKING CHANGE:` | breaking API/schema change | **major** |
| `docs:`, `chore:`, `refactor:`, `test:`, `ci:` | housekeeping | none |

## Local development

```bash
cp .env.example .env
php bin/console key:generate
# set DB_* in .env (leave APP_URL blank to auto-detect)
php bin/console migrate && php bin/console seed
php -S localhost:8000 -t public
```

Run the checks CI runs:

```bash
# lint
find app core config database routes public bootstrap bin tests -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
# health + integration suites (needs a MySQL configured in .env)
php bin/console health
for f in tests/integration_*.php; do php bin/console migrate:fresh && php bin/console seed && php "$f"; done
```

## Rules

- `declare(strict_types=1)` in every PHP file; escape output with `e()`; use
  prepared statements only.
- **Never commit secrets** or a `.env` file. CI fails on committed secrets.
- **Migrations must be additive** (add columns/tables/indexes). Never
  drop/truncate/delete production data. Deploys use `php bin/console upgrade`.
- Add/adjust an integration test under `tests/integration_*.php` for behaviour
  changes; it must `exit(1)` on failure so CI catches it.

## Releasing

Maintainers cut releases by tagging `main` with a semver tag (`vMAJOR.MINOR.PATCH`).
The Release workflow builds artifacts and publishes a GitHub Release. See
`RELEASE.md`.
