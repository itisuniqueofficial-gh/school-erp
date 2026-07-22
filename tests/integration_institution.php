<?php

declare(strict_types=1);

/**
 * Institution / modular ERP configuration test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_institution.php
 */

use App\Services\InstitutionService;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$svc = new InstitutionService();

// ---------------------------------------------------------------------------
section('Modules default to enabled (existing installs keep working)');
check('fees enabled by default', $svc->moduleEnabled('fees') === true);
check('library enabled by default', $svc->moduleEnabled('library') === true);
check('unknown/core module is always enabled', $svc->moduleEnabled('students') === true);
check('modules() lists all optional modules', count($svc->modules()) === count(InstitutionService::availableModules()));

// ---------------------------------------------------------------------------
section('Disabling a module persists and is reflected everywhere');
$svc->setModule('fees', false);
$svc2 = new InstitutionService(); // fresh instance -> reads persisted state
check('fees now disabled (persisted)', $svc2->moduleEnabled('fees') === false);
check('other modules unaffected', $svc2->moduleEnabled('library') === true);
check('modules() reports fees=false', ($svc2->modules()['fees'] ?? true) === false);
$svc2->setModule('fees', true);
check('re-enabling fees works', (new InstitutionService())->moduleEnabled('fees') === true);

// ---------------------------------------------------------------------------
section('Terminology is configurable with safe defaults');
check('default term for student is "Student"', $svc->term('student') === 'Student');
check('default term for class is "Class"', $svc->term('class') === 'Class');
$svc->setTerm('student', 'Learner');
$svc->setTerm('class', 'Course');
$fresh = new InstitutionService();
check('student term overridden to "Learner"', $fresh->term('student') === 'Learner');
check('class term overridden to "Course"', $fresh->term('class') === 'Course');
$svc->setTerm('student', ''); // blank restores default
check('blank term falls back to default', (new InstitutionService())->term('student') === 'Student');
check('unknown term key returns its provided default', $svc->term('nonexistent', 'Fallback') === 'Fallback');

// ---------------------------------------------------------------------------
section('Institution profile persists (type / board)');
$svc->setProfile('type', 'government');
$svc->setProfile('board', 'state');
$p = new InstitutionService();
check('institution type persisted', $p->profile('type') === 'government');
check('board persisted', $p->profile('board') === 'state');
check('profile default used when unset', $p->profile('nonexistent', 'DEF') === 'DEF');

// ---------------------------------------------------------------------------
section('Branding fields persist (name / footer / login copy)');
$svc->setProfile('name', 'Acme Public School');
$svc->setProfile('footer_text', 'Powered by Acme');
$svc->setProfile('login_welcome', 'Welcome to Acme');
$b = new InstitutionService();
check('institution name persisted', $b->profile('name') === 'Acme Public School');
check('footer text persisted', $b->profile('footer_text') === 'Powered by Acme');
check('login welcome persisted', $b->profile('login_welcome') === 'Welcome to Acme');

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll institution-config checks passed.\033[0m\n";
exit(0);
