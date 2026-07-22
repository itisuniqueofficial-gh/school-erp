# DEVELOPER_GUIDE.md — Developer Guide

## Architecture

Core PHP 8.3, no framework, MVC + PSR-4, front-controller pattern. A small
custom kernel lives in `core/`; application code lives in `app/`.

```
Request → public/index.php → bootstrap/app.php (container, env, config, session)
        → Router → middleware pipeline → Controller → Model/Service → View/JSON → Response
```

## Folder structure
```
app/
  Controllers/  Auth, Admin, Portal, Api\V1
  Models/       ActiveRecord-style models (extend Core\Database\Model)
  Services/     Business logic (Stats, Report, Backup, Mail, CodeGenerator, …)
  Middleware/   HTTP middleware (auth, csrf, rbac, throttle, maintenance, …)
  Policies/     Record-level authorization
core/           Framework kernel (Http, Routing, Database, View, Auth, Security, Validation, Support, Config)
config/         app, database, session, security, mail, auth, paths
database/       migrations (*.sql), seeds, Migrator, schema.sql
resources/views layouts, partials, module views (native PHP templates)
routes/         web.php, api.php
public/         index.php (front controller), install.php, assets/
storage/        logs, cache, sessions, uploads, backups (writable)
bin/console     CLI (migrate, migrate:fresh, migrate:status, upgrade, health,
                seed, install, key:generate, backup, reminders, cleanup:*)
tests/          Integration harnesses (tests/integration_*.php) + PHPUnit unit tests
```

## Routing
Defined in `routes/web.php` and `routes/api.php` using the `$router`:
```php
$router->group(['prefix' => 'admin', 'middleware' => ['role:school_admin']], function ($router) {
    $router->get('/students', StudentController::class . '@index')->middleware('can:students.view');
    $router->put('/students/{id}', StudentController::class . '@update');
});
```
- `{param}` / `{param?}` placeholders; method spoofing via `_method` hidden field.
- Middleware aliases registered in `public/index.php`: `auth, guest, csrf, can,
  role, security, throttle, maintenance, module, api.auth`.
  - `can:permission` — RBAC permission gate; `role:a,b` — role gate;
    `module:fees` — blocks a route (403) when that optional module is disabled
    in ERP Configuration (`InstitutionService`).

## Controllers
Extend `Core\Routing\Controller`. Helpers: `view($tpl,$data)`, `json($data)`,
`redirect($path,$with)`, `back($errors,$input)`, `validate($request,$rules)`,
`success($data,$msg,$status)`, `error($msg,$status,$errors)`.

## Models
Extend `Core\Database\Model`. Set `$table`, `$fillable`, `$softDeletes`.
Static helpers: `find`, `findBy`, `all`, `create`, `updateById`, `deleteById`,
`paginate($page,$perPage,$query)`, `query()`. Add domain helpers like
`filtered(array $filters): QueryBuilder` and `profile(int $id): ?array`.

## Query builder
```php
QueryBuilder::table('students')
    ->select(['students.*', 'classes.name AS class_name'])
    ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
    ->where('status', 'active')
    ->whereRaw('(`first_name` LIKE ? OR `last_name` LIKE ?)', [$t, $t])
    ->orderBy('id', 'DESC')->forPage(1, 15)->get();
```
All values are bound (prepared statements). `whereRaw` is for grouped OR clauses.

## Services
Stateless classes in `app/Services` encapsulate business logic:
- `ReportService`, `BackupService`, `CodeGenerator`, `LoginThrottle`,
  `ApiTokenService`, `MailService`, `AttendanceService`, `AcademicYearService`,
  `DependencyService` (dependency-safe delete), `MarksWorkflowService`,
  `AttendanceLockService`, `NotificationService`.
- `IdSequenceService` — universal 12-digit `uid` + prefixed public IDs
  (row-locked, concurrency-safe, never reused).
- `AvatarService` — secure profile-picture pipeline (real-MIME validation +
  GD re-encode to strip payloads + thumbnail).
- `PaymentGatewayService` — gateway config encrypted at rest (`Core\Security\Crypto`),
  enabled-only checkout listing, server-side Razorpay signature + webhook HMAC
  verification, idempotent transactions ledger.
- `InstitutionService` — module enable/disable, terminology, institution profile
  & DB-backed branding (settings-backed, request-cached).
Prefer services over fat controllers.

## Middleware
Implement `Core\Middleware\Middleware::handle(Request $r, callable $next, ?string $arg)`.
Return a `Response` or call `$next($r)`. Register an alias in `public/index.php`.

## Helpers
`app(), config(), env(), view(), e(), auth(), session(), request(),
csrf_field(), method_field(), url(), asset(), base_url(), detect_base_url(),
redirect(), abort(), money(), slugify(), logger()`.

`base_url()` returns `APP_URL` when set, else auto-detects scheme/host/subdir
from the request (proxy-aware via `X-Forwarded-*`); `detect_base_url($server,
$configured)` is the pure, unit-tested resolver. `url()`/`asset()` build on it,
so the app is portable across any domain/subdirectory/HTTPS with no config edits.

## Coding standards
- `declare(strict_types=1)` in every PHP file; typed signatures.
- PSR-4 (`App\`→`app/`, `Core\`→`core/`, `Database\`→`database/`).
- Escape all output with `e()`; never interpolate user input into SQL.
- Small, single-responsibility methods; services for logic.

## Database & migrations
- Add `database/migrations/NNN_description.sql` (plain MySQL DDL). **19** applied
  migrations today (`001…019`), **77** tables.
- Migrations must be **additive** (add columns/tables/indexes) — never
  drop/truncate/delete production data. `migrate` runs only pending files.
- Deploy with `php bin/console upgrade` (auto backup + pending migrations only);
  `migrate:fresh` is guarded against `APP_ENV=production` (requires `--force`).
- Status via `migrate:status`; health via `php bin/console health` or `GET /health`.
- Keep `database/schema.sql` in sync for one-shot imports.

## Creating a new module (recipe)
1. **Migration**: add the table(s) in `database/migrations`.
2. **Model**: `app/Models/Thing.php` with `$table`, `$fillable`, `filtered()`.
3. **Controller**: `app/Controllers/Admin/ThingController.php` (index/create/store/…).
4. **Routes**: add to `routes/web.php` (and `routes/api.php` if needed) with `can:` guards.
5. **Permissions**: add `thing.view/create/...` in the seeder + assign to roles.
6. **Views**: `resources/views/admin/things/{index,create,edit,show}.php`
   extending `layouts.app`, `section('main')`, include `partials.pagination`.
7. **Sidebar**: add a permission-gated link in `resources/views/partials/sidebar.php`.
8. **Test**: replicate a `/tmp/test_*.php`-style harness (inject a PDO via
   `Connection::setPdoForTesting`) and assert model + view behaviour.

## Extending existing modules
Add methods to the model/controller, new routes, new permissions, and views.
Reuse the shared partials and design tokens (`public/assets/css/app.css`).

## Frontend
Tailwind (CDN) + design tokens in `app.css`; jQuery + GSAP + Chart.js. JS hooks:
`data-sidebar-toggle, data-dropdown-toggle, data-confirm, data-loading,
data-table-search, data-qr`, and automatic client-side form validation.

## Testing
- Integration harnesses in `tests/integration_*.php` (run with the static PHP +
  a live MySQL, e.g. `php tests/integration_academic.php`) cover academic,
  attendance, marks, IDs, payments, institution config, portability, audit,
  safe-deploy, avatars, and more — 300+ assertions.
- PHPUnit unit tests under `tests/Unit`; a PDO can be injected via
  `Core\Database\Connection::setPdoForTesting($pdo)`.

## Deployment workflow
1. `APP_ENV=production`, `APP_DEBUG=false`, generate `APP_KEY`; `APP_URL` blank = auto-detect.
2. `php bin/console upgrade` (backup + pending migrations) — or the web installer for a fresh install.
3. Point the web root at `public/`; make `storage/` writable (auto-created on boot).
4. Configure cron (reminders/backup/cleanup) and SSL; verify `GET /health` returns 200.
5. Remove `public/install.php`.
Container/PaaS: use the bundled `Dockerfile` + `koyeb.yaml` (see
`KOYEB_DEPLOYMENT.md`). Server configs: `docs/DEPLOYMENT.md`,
`deploy/nginx.conf.example`, `deploy/Caddyfile.example`,
`CPANEL_DEPLOYMENT.md` / `VPS_DEPLOYMENT.md`.
