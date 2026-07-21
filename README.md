# School ERP

A production-ready **School Enterprise Resource Planning** system built with **Core PHP 8.3+** (no framework) following an MVC architecture, PSR-4 autoloading, PDO prepared statements, RBAC, and a versioned REST API.

## Features

- **Multi-portal**: Super Admin, School Admin, Principal, Teacher, Accountant, Librarian, Transport Manager, Student, and Parent portals.
- **RBAC**: Roles, permissions, and server-side policy enforcement on every route.
- **Modules**: Students, Teachers, Staff, Academic (years/classes/sections/subjects), Attendance, Examinations, Fees, Library, Transport, Communication, Reports, Settings, HR.
- **REST API**: Versioned (`/api/v1`), Bearer-token authenticated, with pagination, filtering, and sorting.
- **Security**: CSRF protection, XSS output escaping, SQL-injection-safe prepared statements, bcrypt password hashing, secure sessions, rate limiting, CSP + security headers, and audit logging.
- **Frontend**: Tailwind CSS, Font Awesome, jQuery, GSAP. Light-mode, responsive, accessible (WCAG-aware).

## Requirements

- PHP **8.3+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`
- MySQL **8.0+**
- Apache (with `mod_rewrite`) or Nginx
- Composer (optional — a built-in PSR-4 autoloader is included for hosts without Composer)

## Installation

```bash
# 1. Clone / upload the project
cd school-erp

# 2. Configure environment
cp .env.example .env
# Edit .env with your database credentials, then generate an app key:
php bin/console key:generate

# 3. (Optional) Install dev dependencies
composer install

# 4. Create the database, run migrations and seed baseline data
php bin/console migrate
php bin/console seed

# 5. Serve locally
php -S localhost:8000 -t public
```

Then visit `http://localhost:8000`.

### Default credentials (from seed data)

| Role         | Email                  | Password     |
|--------------|------------------------|--------------|
| Super Admin  | superadmin@school.test | Password123! |
| School Admin | admin@school.test      | Password123! |
| Teacher      | teacher@school.test    | Password123! |
| Student      | student@school.test    | Password123! |
| Parent       | parent@school.test     | Password123! |

> Change all default passwords immediately after first login.

## Directory structure

```
app/             Application code (Controllers, Models, Services, Middleware, Policies, Repositories)
core/            Framework kernel (Routing, Http, Database, View, Auth, Security, Validation, Support)
config/          Configuration files (read from .env)
database/        Migrations, seeds, and the migration runner
public/          Web root & front controller (index.php)
resources/views  Templates (native PHP with layout inheritance)
routes/          web.php and api.php route definitions
storage/         Logs, cache, sessions, uploads, backups (writable)
bin/             CLI console (migrate, seed, key:generate, backup)
tests/           PHPUnit unit & feature tests
docs/            Architecture, API, installation & deployment docs
```

## Deployment

Point your web server's document root at the `public/` directory. See `docs/DEPLOYMENT.md` for Apache/Nginx/cPanel guides, and `docs/API.md` for the REST API reference.

## License

MIT — see [LICENSE](LICENSE).
