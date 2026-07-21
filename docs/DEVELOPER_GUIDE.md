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
bin/console     CLI (migrate, seed, install, backup, reminders, cleanup:*)
tests/          PHPUnit unit tests
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
  role, security, throttle, maintenance, api.auth`.

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
Stateless classes in `app/Services` encapsulate business logic (e.g.
`ReportService`, `BackupService`, `CodeGenerator`, `LoginThrottle`,
`ApiTokenService`, `MailService`). Prefer services over fat controllers.

## Middleware
Implement `Core\Middleware\Middleware::handle(Request $r, callable $next, ?string $arg)`.
Return a `Response` or call `$next($r)`. Register an alias in `public/index.php`.

## Helpers
`app(), config(), env(), view(), e(), auth(), session(), request(),
csrf_field(), method_field(), url(), asset(), redirect(), abort(), money(),
slugify(), logger()`.

## Coding standards
- `declare(strict_types=1)` in every PHP file; typed signatures.
- PSR-4 (`App\`→`app/`, `Core\`→`core/`, `Database\`→`database/`).
- Escape all output with `e()`; never interpolate user input into SQL.
- Small, single-responsibility methods; services for logic.

## Database & migrations
- Add `database/migrations/NNN_description.sql` (plain MySQL DDL).
- Run `php bin/console migrate`; status via `migrate:status`.
- Keep `database/schema.sql` in sync for one-shot imports (concatenation of migrations).

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
- PHPUnit config in `phpunit.xml`; unit tests in `tests/Unit`.
- The codebase is verifiable against SQLite by injecting a PDO:
  `Core\Database\Connection::setPdoForTesting($pdo)`.

## Deployment workflow
1. `APP_ENV=production`, `APP_DEBUG=false`, generate `APP_KEY`.
2. `php bin/console migrate && php bin/console seed` (or the web installer).
3. Point the web root at `public/`; make `storage/` writable.
4. Configure cron (reminders/backup/cleanup) and SSL.
5. Remove `public/install.php`.
See `CPANEL_DEPLOYMENT.md` / `VPS_DEPLOYMENT.md`.
