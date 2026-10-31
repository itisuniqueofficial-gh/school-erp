#!/usr/bin/env bash
#
# XAMPP compatibility helper for School ERP.
#
#   scripts/xampp.sh check     read-only environment / install verification
#   scripts/xampp.sh setup     create .env/storage and htdocs link when possible
#   scripts/xampp.sh start     start only XAMPP Apache + MySQL/MariaDB
#   scripts/xampp.sh stop      stop only XAMPP services (never system MySQL)
#   scripts/xampp.sh restart   stop then start the XAMPP services
#   scripts/xampp.sh status    show service and application status
#
# XAMPP is auto-detected. Override with XAMPP_HOME=/path/to/xampp.
# Set XAMPP_URL=https://... when the site is not at http://127.0.0.1[/subdir].
# Set XAMPP_STRICT=1 to make check warnings fail the run.
#
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT_DIR="$(cd -- "$SCRIPT_DIR/.." && pwd -P)"
XAMPP_HOME="${XAMPP_HOME:-}"
XAMPP_URL="${XAMPP_URL:-}"
XAMPP_STRICT="${XAMPP_STRICT:-0}"
FAILS=0
WARNINGS=0

if [[ -t 1 ]]; then
    C_RESET=$'\033[0m'; C_OK=$'\033[32m'; C_WARN=$'\033[33m'
    C_FAIL=$'\033[31m'; C_INFO=$'\033[36m'; C_BOLD=$'\033[1m'
else
    C_RESET=''; C_OK=''; C_WARN=''; C_FAIL=''; C_INFO=''; C_BOLD=''
fi

ok()   { printf '  %sOK%s    %s\n' "$C_OK" "$C_RESET" "$*"; }
warn() { WARNINGS=$((WARNINGS + 1)); printf '  %sWARN%s  %s\n' "$C_WARN" "$C_RESET" "$*"; }
fail() { FAILS=$((FAILS + 1)); printf '  %sFAIL%s  %s\n' "$C_FAIL" "$C_RESET" "$*"; }
info() { printf '  %sINFO%s  %s\n' "$C_INFO" "$C_RESET" "$*"; }
section() { printf '\n%s== %s ==%s\n' "$C_BOLD" "$*" "$C_RESET"; }
die() { printf '%sError:%s %s\n' "$C_FAIL" "$C_RESET" "$*" >&2; exit 1; }

abspath() {
    (cd -- "$1" 2>/dev/null && pwd -P)
}

find_xampp() {
    local candidate
    local -a candidates=()
    [[ -n "$XAMPP_HOME" ]] && candidates+=("$XAMPP_HOME")
    candidates+=(/opt/lampp /Applications/XAMPP/xamppfiles)

    for candidate in "${candidates[@]}"; do
        [[ -n "$candidate" ]] || continue
        if [[ -x "$candidate/bin/php" ]]; then
            XAMPP_HOME="$(abspath "$candidate")"
            return 0
        fi
    done
    return 1
}

require_xampp() {
    if ! find_xampp; then
        die "XAMPP was not found. Install it or run with XAMPP_HOME=/path/to/xampp."
    fi
    PHP_BIN="$XAMPP_HOME/bin/php"
}

htdocs_dir() {
    abspath "$XAMPP_HOME/htdocs" 2>/dev/null || true
}

detect_url_path() {
    local htdocs root entry target
    htdocs="$(htdocs_dir)"
    root="$ROOT_DIR"
    URL_PATH=""

    [[ -n "$htdocs" ]] || return 0

    if [[ "$root" == "$htdocs" ]]; then
        URL_PATH=""
        return 0
    fi
    if [[ "$root" == "$htdocs"/* ]]; then
        URL_PATH="/${root#"$htdocs"/}"
        return 0
    fi

    # Symlinked project (common on Linux): htdocs/erp -> /path/to/project.
    for entry in "$htdocs"/*; do
        [[ -L "$entry" ]] || continue
        target="$(abspath "$entry" 2>/dev/null || true)"
        if [[ -n "$target" && "$target" == "$root" ]]; then
            URL_PATH="/$(basename "$entry")"
            return 0
        fi
    done
}

http_port() {
    local conf="$XAMPP_HOME/etc/httpd.conf" port
    if [[ -f "$conf" ]]; then
        port="$(sed -nE 's/^[[:space:]]*Listen[[:space:]]+([0-9]+)[[:space:]]*$/\1/p' "$conf" | head -n 1)"
        [[ -n "$port" ]] && { printf '%s' "$port"; return 0; }
    fi
    printf '80'
}

mysql_port() {
    local conf="$XAMPP_HOME/etc/my.cnf" port
    if [[ -f "$conf" ]]; then
        port="$(sed -nE 's/^[[:space:]]*port[[:space:]]*=[[:space:]]*([0-9]+).*$/\1/p' "$conf" | head -n 1)"
        [[ -n "$port" ]] && { printf '%s' "$port"; return 0; }
    fi
    printf '3306'
}

app_url() {
    local port base
    if [[ -n "$XAMPP_URL" ]]; then
        base="${XAMPP_URL%/}"
        printf '%s/' "$base"
        return 0
    fi
    port="$(http_port)"
    base="http://127.0.0.1"
    [[ "$port" != "80" ]] && base="$base:$port"
    printf '%s%s/' "$base" "${URL_PATH:-}"
}

port_open() {
    local port="$1"
    if command -v nc >/dev/null 2>&1; then
        nc -z -w 1 127.0.0.1 "$port" >/dev/null 2>&1
    else
        (exec 3<>"/dev/tcp/127.0.0.1/$port") >/dev/null 2>&1
    fi
}

xampp_process() {
    local pattern="$1"
    ps -eo args= 2>/dev/null | grep -F "$XAMPP_HOME" | grep -Eq "$pattern" || return 1
}

apache_running() {
    xampp_process '/(httpd|apache2)([[:space:]]|$)'
}

mysql_datadir() {
    local conf datadir
    conf="$XAMPP_HOME/etc/my.cnf"
    if [[ -f "$conf" ]]; then
        datadir="$(sed -nE 's/^[[:space:]]*datadir[[:space:]]*=[[:space:]]*([^[:space:]]+).*$/\1/p' "$conf" | head -n 1)"
        [[ -n "$datadir" ]] && { printf '%s' "$datadir"; return 0; }
    fi
    printf '%s' "$XAMPP_HOME/var/mysql"
}

mysql_running() {
    local datadir
    datadir="$(mysql_datadir)"
    ps -eo args= 2>/dev/null | grep -F -- "--datadir=$datadir" | grep -Eq '/(mysqld|mariadbd)([[:space:]]|$)' || return 1
}

web_user() {
    local conf="$XAMPP_HOME/etc/httpd.conf"
    [[ -f "$conf" ]] || return 0
    sed -nE 's/^[[:space:]]*User[[:space:]]+([^[:space:]]+).*$/\1/p' "$conf" | head -n 1
}

web_group() {
    local user group
    user="$(web_user)"
    [[ -n "$user" ]] || return 0
    group="$(id -gn "$user" 2>/dev/null || true)"
    [[ -n "$group" ]] && printf '%s' "$group"
}

xampp_control() {
    if [[ -x "$XAMPP_HOME/lampp" ]]; then
        printf '%s' "$XAMPP_HOME/lampp"
    elif [[ -x "$XAMPP_HOME/xampp" ]]; then
        printf '%s' "$XAMPP_HOME/xampp"
    else
        return 1
    fi
}

run_privileged() {
    if [[ "$(id -u)" -eq 0 ]]; then
        "$@"
    elif command -v sudo >/dev/null 2>&1; then
        printf '%sAdministrator privileges are required for this step; sudo may prompt.%s\n' "$C_INFO" "$C_RESET"
        sudo "$@"
    else
        die "Root privileges are required. Re-run as root: $*"
    fi
}

wait_for_port() {
    local port="$1" tries="${2:-15}" i
    for ((i = 1; i <= tries; i++)); do
        port_open "$port" && return 0
        sleep 1
    done
    return 1
}

http_code() {
    local url="$1" follow="${2:-1}"
    if [[ "$follow" == "1" ]]; then
        curl -sS -L --max-redirs 3 --max-time 10 -o /dev/null -w '%{http_code}' "$url" 2>/dev/null || printf '000'
    else
        curl -sS --max-time 10 -o /dev/null -w '%{http_code}' "$url" 2>/dev/null || printf '000'
    fi
}

detect_installation() {
    detect_url_path
    APP_URL_VALUE="$(app_url)"
}

check_requirements() {
    local version mode perm
    section "XAMPP"
    if find_xampp; then
        ok "XAMPP found at $XAMPP_HOME"
    else
        fail "XAMPP not found (set XAMPP_HOME=/path/to/xampp)"
        return 1
    fi
    PHP_BIN="$XAMPP_HOME/bin/php"
    ok "XAMPP PHP: $("$PHP_BIN" -v 2>/dev/null | head -n 1)"

    section "PHP 8.2+ and extensions"
    version="$("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || true)"
    if "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' 2>/dev/null; then
        ok "PHP $version meets the 8.2 minimum"
    else
        fail "PHP ${version:-unknown} is too old; School ERP requires PHP 8.2+"
    fi

    local ext
    for ext in pdo pdo_mysql mbstring openssl fileinfo json session ctype iconv; do
        if "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null; then
            ok "extension $ext"
        else
            fail "missing required extension: $ext"
        fi
    done
    if "$PHP_BIN" -r 'exit(in_array("mysql", PDO::getAvailableDrivers(), true) ? 0 : 1);' 2>/dev/null; then
        ok "PDO MySQL driver available"
    else
        fail "PDO MySQL driver is not available"
    fi
    if "$PHP_BIN" -r "exit(extension_loaded('gd') ? 0 : 1);" 2>/dev/null; then
        ok "optional extension gd (avatar thumbnails)"
    else
        warn "optional extension gd is missing; avatars use the built-in fallback"
    fi

    section "Project and Apache"
    if [[ -f "$ROOT_DIR/public/index.php" && -f "$ROOT_DIR/bin/console" ]]; then
        ok "project root: $ROOT_DIR"
    else
        fail "this does not look like the School ERP project root: $ROOT_DIR"
    fi
    if [[ -f "$ROOT_DIR/.htaccess" && -f "$ROOT_DIR/public/.htaccess" ]]; then
        ok "Apache .htaccess files are present"
    else
        fail "missing root or public/.htaccess"
    fi
    if "$XAMPP_HOME/bin/httpd" -M 2>/dev/null | grep -q 'rewrite_module'; then
        ok "Apache mod_rewrite is enabled"
    else
        warn "could not confirm mod_rewrite; enable it in httpd.conf"
    fi

    section "Application URL and subdirectory"
    detect_installation
    if [[ -n "${URL_PATH:-}" ]]; then
        ok "application path: $APP_URL_VALUE"
    elif [[ "$ROOT_DIR" == "$(htdocs_dir)"/* ]]; then
        ok "application URL: $APP_URL_VALUE"
    else
        warn "project is not under $XAMPP_HOME/htdocs; use a symlink, Alias, or virtual host"
        info "expected layout: $XAMPP_HOME/htdocs/$(basename "$ROOT_DIR") -> $ROOT_DIR"
    fi

    section "Environment and writable storage"
    local dir web mode perm
    web="$(web_user)"
    if [[ -f "$ROOT_DIR/.env" ]]; then
        ok ".env exists"
        mode="$(stat -c '%a' "$ROOT_DIR/.env" 2>/dev/null || stat -f '%Lp' "$ROOT_DIR/.env" 2>/dev/null || true)"
        if [[ -n "$mode" ]]; then
            perm=$((8#$mode))
            if [[ -n "$web" && "$web" != "$(id -un)" ]]; then
                if ((perm & 8#004)); then
                    ok ".env is readable by Apache user '$web' (mode $mode)"
                else
                    warn ".env is not readable by Apache user '$web' (mode $mode); run: scripts/xampp-setup.sh --fix-permissions"
                fi
            elif ((perm & 8#004)); then
                warn ".env is world-readable (mode $mode); use chmod 640 .env"
            else
                ok ".env permissions: $mode"
            fi
        fi
    else
        warn ".env is missing; run: scripts/xampp-setup.sh"
    fi

    for dir in storage storage/logs storage/cache storage/sessions storage/uploads storage/uploads/avatars storage/uploads/documents storage/backups; do
        if [[ ! -d "$ROOT_DIR/$dir" ]]; then
            fail "missing directory: $dir"
        elif [[ ! -w "$ROOT_DIR/$dir" ]]; then
            fail "not writable by $(id -un): $dir"
        else
            ok "writable: $dir"
        fi
    done
    if [[ -n "$web" && "$web" != "$(id -un)" ]]; then
        info "Apache runs as '$web'; if writes fail, run scripts/xampp-setup.sh --fix-permissions"
    fi

    section "Database"
    if [[ -f "$ROOT_DIR/.env" ]]; then
        local status
        if status="$("$PHP_BIN" "$ROOT_DIR/bin/console" install:status 2>&1)"; then
            ok "database reachable"
            printf '%s\n' "$status" | sed -nE 's/^.*(State[[:space:]]*:.*)$/  INFO  \1/p; s/^.*(Users[[:space:]]*:.*)$/  INFO  \1/p; s/^.*(Migrations pending[[:space:]]*:.*)$/  INFO  \1/p'
            if printf '%s\n' "$status" | grep -Eq 'Migrations pending[[:space:]]*:[[:space:]]*[1-9]'; then
                warn "pending migrations; back up first and run: $PHP_BIN bin/console upgrade"
            fi
            if printf '%s\n' "$status" | grep -q 'EXISTING installation'; then
                ok "existing installation detected (data will not be reset)"
            elif printf '%s\n' "$status" | grep -q 'FRESH'; then
                info "fresh database; finish installation in the browser at ${APP_URL_VALUE}install.php"
            fi
        else
            fail "database status failed; run: $PHP_BIN bin/console db:preflight"
            info "$(printf '%s' "$status" | tail -n 1)"
        fi
    else
        warn "database check skipped until .env exists"
    fi
}

check_services() {
    local aport mport
    aport="$(http_port)"
    mport="$(mysql_port)"
    section "XAMPP services"
    if apache_running; then
        ok "XAMPP Apache is running (port $aport)"
    elif port_open "$aport"; then
        fail "port $aport is owned by another web server, not XAMPP Apache"
    else
        warn "XAMPP Apache is not running; use scripts/xampp-start.sh"
    fi

    if mysql_running; then
        if port_open "$mport"; then
            ok "XAMPP MySQL/MariaDB is running (port $mport)"
        else
            warn "XAMPP MySQL/MariaDB is running but not listening on port $mport"
        fi
    elif port_open "$mport"; then
        fail "port $mport is already used by another MySQL/MariaDB service"
        info "stop the other database service or change XAMPP's MySQL port and DB_PORT"
    else
        warn "XAMPP MySQL/MariaDB is not running; use scripts/xampp-start.sh"
    fi
}

check_http() {
    local code
    if ! command -v curl >/dev/null 2>&1; then
        warn "curl is not installed; HTTP checks skipped"
        return 0
    fi
    detect_installation
    if [[ -z "${URL_PATH:-}" && -z "$XAMPP_URL" && "$ROOT_DIR" != "$(htdocs_dir)"/* ]]; then
        warn "HTTP checks skipped: cannot map $ROOT_DIR to an XAMPP URL"
        return 0
    fi
    section "HTTP checks ($APP_URL_VALUE)"
    if [[ "$(http_code "${APP_URL_VALUE}")" == "200" ]]; then
        ok "application responds with HTTP 200"
    else
        code="$(http_code "${APP_URL_VALUE}")"
        fail "application did not return HTTP 200 (got $code)"
    fi

    if [[ "$(http_code "${APP_URL_VALUE}assets/css/app.css")" == "200" ]]; then
        ok "public assets load through the subdirectory"
    else
        fail "assets/css/app.css did not return HTTP 200"
    fi

    if [[ "$(http_code "${APP_URL_VALUE}login")" == "200" ]]; then
        ok "login page loads"
    else
        fail "login page did not return HTTP 200"
    fi

    code="$(http_code "${APP_URL_VALUE}.env" 0)"
    if [[ "$code" == "200" ]]; then
        fail ".env is publicly downloadable (HTTP $code)"
    else
        ok ".env is not exposed (HTTP $code)"
    fi
    code="$(http_code "${APP_URL_VALUE}storage/installed.lock" 0)"
    if [[ "$code" == "200" ]]; then
        fail "storage/installed.lock is publicly downloadable (HTTP $code)"
    else
        ok "storage lock is not exposed (HTTP $code)"
    fi
    code="$(http_code "${APP_URL_VALUE}.git/config" 0)"
    if [[ "$code" == "200" ]]; then
        fail ".git/config is publicly downloadable (HTTP $code)"
    else
        ok ".git/config is not exposed (HTTP $code)"
    fi
}

check_php_limits() {
    local upload post
    upload="$("$PHP_BIN" -r 'echo ini_get("upload_max_filesize");' 2>/dev/null || true)"
    post="$("$PHP_BIN" -r 'echo ini_get("post_max_size");' 2>/dev/null || true)"
    section "PHP upload limits"
    info "upload_max_filesize=$upload  post_max_size=$post"
    if [[ "$upload" == "0" || "$post" == "0" ]]; then
        warn "uploads may be disabled in XAMPP php.ini"
    else
        ok "uploads are enabled"
    fi
}

cmd_check() {
    if ! check_requirements; then
        section "Result"
        printf '%s%d failure(s), %d warning(s).%s\n' "$C_FAIL" "$FAILS" "$WARNINGS" "$C_RESET"
        exit 1
    fi
    check_services
    check_php_limits
    check_http

    section "Result"
    if ((FAILS > 0)); then
        printf '%s%d failure(s), %d warning(s).%s\n' "$C_FAIL" "$FAILS" "$WARNINGS" "$C_RESET"
        exit 1
    fi
    if [[ "$XAMPP_STRICT" == "1" && "$WARNINGS" -gt 0 ]]; then
        printf '%s%d warning(s) treated as failures (XAMPP_STRICT=1).%s\n' "$C_FAIL" "$WARNINGS" "$C_RESET"
        exit 1
    fi
    printf '%sXAMPP check passed with %d warning(s).%s\n' "$C_OK" "$WARNINGS" "$C_RESET"
}

ensure_storage() {
    local dir
    section "Storage directories"
    for dir in storage storage/logs storage/cache storage/sessions storage/temp storage/tmp storage/uploads storage/uploads/avatars storage/uploads/documents storage/uploads/branding storage/backups; do
        mkdir -p "$ROOT_DIR/$dir"
        chmod 775 "$ROOT_DIR/$dir"
    done
    ok "storage directories are present (mode 775, not 777)"
}

ensure_env() {
    local web
    section "Environment"
    if [[ ! -f "$ROOT_DIR/.env" ]]; then
        cp "$ROOT_DIR/.env.example" "$ROOT_DIR/.env"
        web="$(web_user)"
        if [[ -n "$web" && "$web" != "$(id -un)" ]]; then
            chmod 644 "$ROOT_DIR/.env"
            ok "created .env from .env.example (mode 644 so Apache can read it)"
            info "local XAMPP only: .env stays outside public/ and is denied by .htaccess"
        else
            chmod 640 "$ROOT_DIR/.env"
            ok "created .env from .env.example (mode 640)"
        fi
    else
        ok ".env already exists; left unchanged"
    fi
    if ! grep -Eq '^APP_KEY=.+' "$ROOT_DIR/.env"; then
        "$PHP_BIN" "$ROOT_DIR/bin/console" key:generate
        ok "generated APP_KEY"
    else
        ok "APP_KEY is set"
    fi
}

ensure_htdocs_link() {
    local target htdocs
    section "htdocs placement"
    detect_installation
    if [[ -n "${URL_PATH:-}" ]]; then
        ok "project is reachable at $APP_URL_VALUE"
        return 0
    fi

    htdocs="$(htdocs_dir)"
    if [[ -z "$htdocs" || ! -d "$htdocs" ]]; then
        warn "XAMPP htdocs directory not found"
        return 0
    fi
    target="$htdocs/$(basename "$ROOT_DIR")"
    if [[ -L "$target" || -e "$target" ]]; then
        warn "$target already exists but does not point to this project"
        info "remove/repoint it manually, then rerun setup"
        return 0
    fi
    if [[ -w "$htdocs" ]]; then
        if ln -s "$ROOT_DIR" "$target"; then
            ok "created symlink $target -> $ROOT_DIR"
            detect_installation
        else
            warn "could not create $target"
            info "run: sudo ln -s \"$ROOT_DIR\" \"$target\""
        fi
    else
        warn "htdocs is not writable by $(id -un)"
        info "run: sudo ln -s \"$ROOT_DIR\" \"$target\""
    fi
}

fix_permissions() {
    local user group
    user="$(web_user)"
    group="$(web_group)"
    section "Storage ownership"
    if [[ -z "$user" ]]; then
        warn "could not determine the Apache user from httpd.conf"
        return 0
    fi
    info "Apache user: $user${group:+:$group}"
    chmod 775 "$ROOT_DIR/storage"
    run_privileged chown -R "$user${group:+:$group}" "$ROOT_DIR/storage"
    ok "storage ownership set to $user${group:+:$group}"
    if [[ -f "$ROOT_DIR/.env" && "$user" != "$(id -un)" ]]; then
        chmod 644 "$ROOT_DIR/.env"
        ok ".env is readable by Apache user '$user' (mode 644)"
    fi
}

cmd_setup() {
    local fix=0
    [[ "${1:-}" == "--fix-permissions" ]] && fix=1
    require_xampp
    ensure_env
    ensure_storage
    ensure_htdocs_link
    if [[ "$fix" == "1" ]]; then
        fix_permissions
    fi

    section "Database status"
    if "$PHP_BIN" "$ROOT_DIR/bin/console" install:status >/dev/null 2>&1; then
        ok "database is reachable"
    else
        warn "database is not reachable yet; create it in phpMyAdmin and set DB_* in .env"
        info "phpMyAdmin: http://127.0.0.1/phpmyadmin/"
    fi

    detect_installation
    section "Next steps"
    info "1. Start XAMPP Apache and MySQL: scripts/xampp-start.sh"
    info "2. Open ${APP_URL_VALUE:-http://127.0.0.1/$(basename "$ROOT_DIR")/}"
    info "3. Finish the browser installer (database schema is applied additively)."
    info "4. Verify everything: scripts/xampp-check.sh"
}

start_mysql() {
    local mport control
    mport="$(mysql_port)"
    control="$(xampp_control)" || die "XAMPP control command not found"
    if mysql_running; then
        ok "XAMPP MySQL/MariaDB is already running"
        return 0
    fi
    if port_open "$mport"; then
        fail "port $mport is already in use by another MySQL/MariaDB service"
        info "XAMPP MySQL was NOT started. Stop the other service or change XAMPP's port."
        return 1
    fi
    info "starting XAMPP MySQL/MariaDB on port $mport"
    run_privileged "$control" startmysql
    if wait_for_port "$mport"; then
        ok "XAMPP MySQL/MariaDB is listening on port $mport"
    else
        warn "start command completed, but port $mport is not accepting connections yet"
    fi
}

start_apache() {
    local aport control
    aport="$(http_port)"
    control="$(xampp_control)" || die "XAMPP control command not found"
    if apache_running; then
        ok "XAMPP Apache is already running"
        return 0
    fi
    if port_open "$aport"; then
        fail "port $aport is already used by another web server"
        info "XAMPP Apache was NOT started. Stop the other web server or change Apache's Listen port."
        return 1
    fi
    info "starting XAMPP Apache on port $aport"
    run_privileged "$control" startapache
    if wait_for_port "$aport"; then
        ok "XAMPP Apache is listening on port $aport"
    else
        warn "start command completed, but port $aport is not accepting connections yet"
    fi
}

cmd_start() {
    require_xampp
    section "Starting only XAMPP services"
    start_mysql || true
    start_apache || true
    section "Status"
    check_services
    ((FAILS == 0)) || exit 1
}

cmd_stop() {
    local control
    require_xampp
    control="$(xampp_control)" || die "XAMPP control command not found"
    section "Stopping only XAMPP services"
    if mysql_running; then
        run_privileged "$control" stopmysql
        ok "XAMPP MySQL/MariaDB stopped"
    else
        info "XAMPP MySQL/MariaDB is not running; nothing stopped"
    fi
    if apache_running; then
        run_privileged "$control" stopapache
        ok "XAMPP Apache stopped"
    else
        info "XAMPP Apache is not running; nothing stopped"
    fi
    info "system MySQL/Apache services were not touched"
}

cmd_restart() {
    cmd_stop
    cmd_start
}

cmd_status() {
    require_xampp
    detect_installation
    check_services
    section "Application"
    if [[ -n "${URL_PATH:-}" || -n "$XAMPP_URL" ]]; then
        info "URL: $APP_URL_VALUE"
    else
        info "URL: not mapped under $XAMPP_HOME/htdocs"
    fi
    if [[ -f "$ROOT_DIR/.env" ]]; then
        "$PHP_BIN" "$ROOT_DIR/bin/console" install:status 2>/dev/null || warn "database status unavailable"
    else
        warn ".env is missing"
    fi
    ((FAILS == 0)) || exit 1
}

usage() {
    cat <<'EOF'
Usage: scripts/xampp.sh <command> [options]

Commands:
  check                 Verify XAMPP, PHP 8.2+, extensions, storage, DB and HTTP
  setup [--fix-permissions]
                        Create .env/storage and an htdocs symlink when possible
  start                 Start only XAMPP Apache + MySQL/MariaDB
  stop                  Stop only XAMPP services (system services are untouched)
  restart               Stop then start XAMPP services
  status                Show XAMPP service and application status

Environment:
  XAMPP_HOME=/opt/lampp        Override XAMPP location
  XAMPP_URL=http://host/erp    Override the application URL for HTTP checks
  XAMPP_STRICT=1               Treat warnings as failures in `check`
EOF
}

main() {
    local command="${1:-}"
    shift || true
    case "$command" in
        check)   cmd_check "$@" ;;
        setup)   cmd_setup "$@" ;;
        start)   cmd_start "$@" ;;
        stop)    cmd_stop "$@" ;;
        restart) cmd_restart "$@" ;;
        status)  cmd_status "$@" ;;
        help|-h|--help|'') usage ;;
        *) usage >&2; exit 2 ;;
    esac
}

main "$@"
