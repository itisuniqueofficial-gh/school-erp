# Database Portability Audit Report

**Project:** School ERP (Core PHP 8.2+, no framework)  
**Date:** $(date -u +%Y-%m-%dT%H:%M:%SZ)  
**Purpose:** Assess current DB portability and plan phased implementation

## 1. Framework/Architecture
- **Type:** Custom MVC, PSR-4, front-controller (`public/index.php`)
- **ORM:** Custom `Core\Database\Model` + `Core\Database\QueryBuilder`
- **Connection:** PDO singleton (`Core\Database\Connection`) with prepared statements
- **Config:** Centralized via `config/database.php` + `Core\Config\Config` + `Core\Support\Env`
- **Migrations:** SQL files in `database/migrations/*.sql` run by `Database\Migrator` (tracks in `migrations` table)
- **Console:** `bin/console` with `migrate`, `migrate:fresh`, `migrate:status`, `upgrade`, `seed`, `backup`, `health`, `key:generate`, etc.

## 2. Current DB Support
- **Default/only fully wired:** MySQL/MariaDB (driver `mysql`)
- **Partially wired:** PostgreSQL config exists in `config/database.php` under `connections.pgsql` (DSN, port 5432, charset utf8) but **not tested/used** by migrations/Migrator which is MySQL-specific
- **SQLite/SQL Server:** Not present in config/connection/migrations
- **Connection:** `Connection::connect()` builds DSN as `'%s:host=%s;port=%d;dbname=%s;charset=%s'` - **MySQL/Postgres-style only** (SQLite needs different DSN like `sqlite:/path/to/db.sqlite` with no host/port/dbname)
- **Migrator:** Creates `migrations` table with MySQL syntax (`INT UNSIGNED AUTO_INCREMENT`, backticks, InnoDB/charset). `fresh()` does `SET FOREIGN_KEY_CHECKS = 0` and `SHOW TABLES` then `DROP TABLE IF EXISTS` - MySQL-specific. `isIgnorable()` checks MySQL error codes. Splits SQL on semicolons (works for plain DDL/DML).

## 3. Hardcoded MySQL-Specific Patterns Found
**Migrations (86 occurrences of ENGINE=, 83 AUTO_INCREMENT, many ENUM/TINYINT(1)):**
- All 30 migrations use `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC`
- `AUTO_INCREMENT` used extensively
- `ENUM(...)` used in many tables (gender, status fields etc.) - not portable to Postgres/SQLite/SQL Server
- `TINYINT(1)` used for booleans - Postgres prefers BOOLEAN, SQLite treats as INTEGER
- `DATETIME`/`TIMESTAMP` used
- Foreign keys with `ON DELETE CASCADE`/`RESTRICT` (portable in principle but syntax differs)
- Backtick identifiers throughout

**Code:**
- `App\Services\BackupService` likely uses `mysqldump` (MySQL-specific)
- `Connection` uses MySQL-style DSN
- `Migrator` uses MySQL-specific SQL (`SHOW TABLES`, `SET FOREIGN_KEY_CHECKS`, backticks)
- `core/Database/QueryBuilder` uses backticks for wrapping identifiers - works for MySQL/MariaDB but Postgres uses double quotes
- `database/Seeds/DatabaseSeeder.php` may contain MySQL-specific syntax
- Some queries use `DATE_FORMAT`, `NOW()` - found in `App\Services\AttendanceService` (DATE_FORMAT used)

## 4. Existing Partial Support
- **Postgres config exists** - good foundation
- **DatabasePreflight** class exists for connection diagnostics
- **Health check in console** - checks tables via `information_schema.tables WHERE table_schema = DATABASE()` (MySQL-specific)

## 5. Gaps vs Requirements
Missing: SQLite support, SQL Server support (prepared), unified driver abstraction that works across all layers, portable migrations (ENUM->CHECK/VARCHAR, AUTO_INCREMENT->IDENTITY/SERIAL, BOOLEAN handling), portable identifier quoting, portable backup/restore, cross-DB migration utility, DB health monitoring UI, comprehensive testing matrix, updated docs.

## 6. Risk Assessment
- **High:** Migrations are not portable as-is (ENUM, ENGINE, AUTO_INCREMENT, backticks). Converting all 30 migrations is complex but necessary.
- **Medium:** Backup uses mysqldump (MySQL-specific). Need portable alternatives.
- **Medium:** Some raw queries use MySQL functions.
- **Low:** QueryBuilder/Connection can be abstracted.

## Recommended Approach
Follow the phases strictly. Start with architecture (abstraction layer), extend config/connection for all drivers, make migrator driver-aware, convert migrations to be portable or add driver-specific migration paths, add backup/restore abstraction, add cross-DB migration tool, update docs/tests.
