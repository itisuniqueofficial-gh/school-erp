<?php

declare(strict_types=1);

/**
 * Deployment portability test: automatic base-URL detection across hosts,
 * schemes, reverse proxies, subdirectories, and explicit APP_URL.
 *
 *   php tests/integration_portability.php
 */

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

// ---------------------------------------------------------------------------
section('Explicit APP_URL always wins');
check('configured URL is used verbatim', detect_base_url(['HTTP_HOST' => 'ignored'], 'https://erp.example.com') === 'https://erp.example.com');
check('configured URL trailing slash trimmed', detect_base_url([], 'https://x.test/') === 'https://x.test');

// ---------------------------------------------------------------------------
section('Auto-detect from request (no APP_URL)');
check('plain http host', detect_base_url(['HTTP_HOST' => 'school.test', 'SCRIPT_NAME' => '/index.php']) === 'http://school.test');
check('HTTPS via server var', detect_base_url(['HTTP_HOST' => 'school.test', 'HTTPS' => 'on', 'SCRIPT_NAME' => '/index.php']) === 'https://school.test');
check('HTTPS via port 443', detect_base_url(['HTTP_HOST' => 'school.test', 'SERVER_PORT' => 443, 'SCRIPT_NAME' => '/index.php']) === 'https://school.test');
check('custom domain + port', detect_base_url(['HTTP_HOST' => 'localhost:8000', 'SCRIPT_NAME' => '/index.php']) === 'http://localhost:8000');

// ---------------------------------------------------------------------------
section('Reverse proxy / Cloudflare (X-Forwarded-*)');
check('X-Forwarded-Proto=https honored', detect_base_url(['HTTP_HOST' => 'erp.example.com', 'HTTP_X_FORWARDED_PROTO' => 'https', 'SCRIPT_NAME' => '/index.php']) === 'https://erp.example.com');
check('X-Forwarded-Host honored', detect_base_url(['HTTP_HOST' => 'internal:8080', 'HTTP_X_FORWARDED_HOST' => 'public.example.com', 'HTTP_X_FORWARDED_PROTO' => 'https', 'SCRIPT_NAME' => '/index.php']) === 'https://public.example.com');
check('comma-separated forwarded host takes first', detect_base_url(['HTTP_X_FORWARDED_HOST' => 'a.example.com, b.internal', 'HTTP_X_FORWARDED_PROTO' => 'https', 'SCRIPT_NAME' => '/index.php']) === 'https://a.example.com');

// ---------------------------------------------------------------------------
section('Subdirectory installation');
check('subdir /erp detected', detect_base_url(['HTTP_HOST' => 'example.com', 'SCRIPT_NAME' => '/erp/index.php']) === 'http://example.com/erp');
check('nested subdir detected', detect_base_url(['HTTP_HOST' => 'example.com', 'SCRIPT_NAME' => '/apps/erp/index.php']) === 'http://example.com/apps/erp');
check('/public docroot stripped', detect_base_url(['HTTP_HOST' => 'example.com', 'SCRIPT_NAME' => '/erp/public/index.php']) === 'http://example.com/erp');
check('root install has no subpath', detect_base_url(['HTTP_HOST' => 'example.com', 'SCRIPT_NAME' => '/index.php']) === 'http://example.com');

// ---------------------------------------------------------------------------
section('CLI / no request context');
check('falls back to http://localhost', detect_base_url([]) === 'http://localhost');

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll portability checks passed.\033[0m\n";
exit(0);
