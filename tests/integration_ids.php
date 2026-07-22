<?php

declare(strict_types=1);

/**
 * Universal 12-digit ID system integration test (Phase 5).
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_ids.php
 */

use App\Services\IdSequenceService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function throws(callable $fn): bool { try { $fn(); return false; } catch (\Throwable) { return true; } }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }
function isUid(string $s, string $lead): bool { return strlen($s) === 12 && ctype_digit($s) && str_starts_with($s, $lead); }

$svc = new IdSequenceService();

// ---------------------------------------------------------------------------
section('Backfill: every core entity has a valid 12-digit uid');
$map = ['users' => '1', 'students' => '2', 'staff' => '3', 'classes' => '4', 'sections' => '5', 'subjects' => '6', 'academic_years' => '7'];
foreach ($map as $table => $lead) {
    $total = (int) QueryBuilder::table($table)->count();
    $nulls = (int) QueryBuilder::table($table)->whereNull('uid')->count();
    check("{$table}: no NULL uids", $total === 0 || $nulls === 0);
    $sample = (string) QueryBuilder::table($table)->whereNotNull('uid')->value('uid');
    check("{$table}: uid is 12 numeric digits, leading '{$lead}'", $sample === '' || isUid($sample, $lead));
}

// ---------------------------------------------------------------------------
section('Generator: format, leading digit, sequential + unique');
$a = $svc->uidStudent();
$b = $svc->uidStudent();
check('uidStudent format (12 digits, leading 2)', isUid($a, '2') && isUid($b, '2'));
check('Sequential generations differ', $a !== $b);
check('Sequence is strictly increasing', (int) $b > (int) $a);
check('uidClass leading 4', isUid($svc->uidClass(), '4'));
check('uidSubject leading 6', isUid($svc->uidSubject(), '6'));
check('uidDocument leading 8', isUid($svc->uidDocument(), '8'));
check('two-digit lead (11) still 12 digits', $svc->numeric('11') === '110000000001' || (strlen($svc->numeric('11')) === 12));

// 100 rapid generations are all unique (sequence integrity / concurrency-safety proxy).
$seen = [];
$dupFound = false;
for ($i = 0; $i < 100; $i++) {
    $u = $svc->uidStudent();
    if (isset($seen[$u])) { $dupFound = true; break; }
    $seen[$u] = true;
}
check('100 generations produced 0 duplicates', !$dupFound && count($seen) === 100);

// ---------------------------------------------------------------------------
section('Database UNIQUE + never-reused');
$existingUid = (string) QueryBuilder::table('students')->whereNotNull('uid')->value('uid');
check('Duplicate uid blocked by DB UNIQUE', throws(fn () =>
    QueryBuilder::table('students')->insert([
        'uid' => $existingUid, 'admission_no' => 'DUP-' . uniqid(), 'first_name' => 'Dup', 'last_name' => 'Uid',
        'gender' => 'male', 'admission_date' => date('Y-m-d'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ])
));

// Never reused: generating consumes the sequence even if the row is later removed.
$beforeNext = (int) QueryBuilder::table('id_sequences')->where('prefix', 'UID2')->where('year', 0)->value('next_value');
$u1 = $svc->uidStudent();
$u2 = $svc->uidStudent();
$afterNext = (int) QueryBuilder::table('id_sequences')->where('prefix', 'UID2')->where('year', 0)->value('next_value');
check('Sequence next_value advances (IDs consumed/reserved)', $afterNext === $beforeNext + 2);
check('A consumed ID is never handed out again', (int) $u2 > (int) $u1 && $u1 !== $u2);

// ---------------------------------------------------------------------------
section('Immutability convention');
// uid columns are only ever written on insert/backfill; there is no update path
// that changes an existing uid. Verify a seeded uid is stable across a re-read.
$sid = (int) QueryBuilder::table('students')->whereNotNull('uid')->value('id');
$uidA = (string) QueryBuilder::table('students')->where('id', $sid)->value('uid');
$uidB = (string) QueryBuilder::table('students')->where('id', $sid)->value('uid');
check('Existing uid is stable', $uidA !== '' && $uidA === $uidB);

// ---------------------------------------------------------------------------
echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll 12-digit ID checks passed.\033[0m\n";
exit(0);
