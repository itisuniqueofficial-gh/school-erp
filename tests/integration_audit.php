<?php

declare(strict_types=1);

/**
 * Audit-log viewer test: logging, filtering, and pagination.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_audit.php
 */

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

// Seed a few known audit entries.
AuditLogger::log('audit.test_create', 'widget', 101, 'Created widget A');
AuditLogger::log('audit.test_update', 'widget', 101, 'Updated widget A');
AuditLogger::log('audit.test_create', 'gadget', 202, 'Created gadget B');

// ---------------------------------------------------------------------------
section('Logging writes rows with actor + context');
$row = QueryBuilder::table('audit_logs')->where('action', 'audit.test_create')->where('entity_id', 101)->first();
check('audit row persisted', $row !== null);
check('row records the acting user', (int) ($row['user_id'] ?? 0) === $adminId);
check('row records ip + description', !empty($row['description']) && array_key_exists('ip_address', $row));

// ---------------------------------------------------------------------------
section('Filtering');
$byAction = AuditLog::filtered(['action' => 'audit.test_create'])->get();
check('filter by action returns only that action', count($byAction) >= 2 && !array_filter($byAction, static fn ($r) => $r['action'] !== 'audit.test_create'));
$byEntity = AuditLog::filtered(['entity_type' => 'gadget'])->get();
check('filter by entity type works', count($byEntity) === 1 && $byEntity[0]['entity_id'] == 202);
$bySearch = AuditLog::filtered(['q' => 'widget A'])->get();
check('search matches description', count($bySearch) >= 2);
$joined = AuditLog::filtered([])->get();
check('actor name is joined from users', array_key_exists('user_name', $joined[0]));

// ---------------------------------------------------------------------------
section('Distinct filter options + pagination');
check('distinctActions includes seeded actions', in_array('audit.test_create', AuditLog::distinctActions(), true));
check('distinctEntities includes widget & gadget', in_array('widget', AuditLog::distinctEntities(), true) && in_array('gadget', AuditLog::distinctEntities(), true));
$page = AuditLog::paginate(1, 2, AuditLog::filtered([]));
check('paginate returns data + meta', isset($page['data'], $page['meta']['total'], $page['meta']['last_page']));
check('per_page respected (<=2 rows)', count($page['data']) <= 2);
check('total reflects all entries (>=3)', (int) $page['meta']['total'] >= 3);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll audit-log checks passed.\033[0m\n";
exit(0);
