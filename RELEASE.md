# Release Process

Releases are automated by `.github/workflows/release.yml`, triggered when a
semver tag `vMAJOR.MINOR.PATCH` is pushed.

## Versioning (SemVer)

- **MAJOR** — breaking API/schema/behaviour change (`feat!:` / `BREAKING CHANGE:`).
- **MINOR** — new backward-compatible feature (`feat:`).
- **PATCH** — backward-compatible bug/security fix (`fix:`).

The version also appears in the app footer, `GET /health` (`version`), and
`config/app.php` (`version`). Keep `config/app.php` in sync when tagging.

## Cutting a release

```bash
# 1. Ensure main is green (CI passed) and CHANGELOG.md has an entry.
git checkout main && git pull

# 2. Bump the version in config/app.php + CHANGELOG.md, commit.
#    e.g. 'chore(release): v1.2.0'

# 3. Tag and push.
git tag v1.2.0
git push origin v1.2.0
```

The Release workflow then:
1. Lints all PHP sources (fails the release on any syntax error).
2. Builds versioned artifacts into `dist/`:
   - `school-erp-vX.Y.Z.tar.gz` and `.zip` — production bundle (excludes
     `.git`, `.github`, `tests`, `.env`, and runtime `storage/*`).
   - `school-erp-vX.Y.Z.env.example` — environment template.
   - `school-erp-vX.Y.Z-CHANGELOG.md` — changelog snapshot.
   - `school-erp-vX.Y.Z.sha256` — checksums.
3. Generates release notes from the commit log since the previous tag, plus
   database/upgrade notes.
4. Publishes a **GitHub Release** with the artifacts attached (pre-release if the
   tag contains a hyphen, e.g. `v1.3.0-rc.1`).

## Upgrading a deployment (zero data loss)

1. **Back up** the database and `storage/uploads` (or rely on the automatic
   backup that `upgrade` takes).
2. Deploy the new code — do **not** overwrite `.env` or `storage/`.
3. Run `php bin/console upgrade` — creates a backup, then applies **only pending
   (additive)** migrations. It never drops, truncates, or reseeds data.
4. Verify `GET /health` returns `200`.

## Rollback

- Restore the pre-upgrade database backup (Admin → Backups, or
  `BackupService::restore()` / `mysql < dump.sql`).
- Redeploy the previous release artifact.
- Uploaded files are untouched by upgrades (kept on the persistent
  `storage/uploads` volume).
