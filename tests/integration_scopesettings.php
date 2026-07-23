<?php

declare(strict_types=1);

/**
 * Global + Local (per class/section) configuration override test.
 * Verifies that a class/section can independently override the working week,
 * times and period structure without affecting the global default or siblings.
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Services\AttendanceSettingsService;
use App\Services\CalendarService;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$svc = new AttendanceSettingsService();

// Global baseline: Sunday-only weekend, 7 periods from 08:00.
$svc->set('attendance_weekend_days', '0');
$svc->set('school_open_time', '08:00');
$svc->set('periods_count', '7');

$classId = 9101;   // arbitrary scope ids (scope_settings is not FK-bound)
$otherClass = 9102;
$sectionId = 9201;

// No override yet -> inherits global.
$check('unset scope inherits global weekend', $svc->weekendDaysFor($classId, null) === [0]);
$check('unset scope inherits global open time', $svc->configFor($classId, null)['school_open_time'] === '08:00');

// Class override: earlier start, Sat+Sun weekend, 4 periods.
$svc->setOverride('class', $classId, 'attendance_weekend_days', '0,6');
$svc->setOverride('class', $classId, 'school_open_time', '07:30');
$svc->setOverride('class', $classId, 'periods_count', '4');

$cfg = $svc->configFor($classId, null);
$check('class override applies weekend', $svc->weekendDaysFor($classId, null) === [0, 6]);
$check('class override applies open time', $cfg['school_open_time'] === '07:30');
$check('class override applies period count', $cfg['periods_count'] === '4');
$check('unset keys still inherit global', $cfg['school_close_time'] === $svc->config()['school_close_time']);
$check('class period schedule has 4 periods', count(array_filter($svc->periodScheduleFor($classId, null), static fn ($s) => $s['type'] === 'period')) === 4);
$check('class schedule starts at overridden time', ($svc->periodScheduleFor($classId, null)[0]['start'] ?? '') === '07:30');

// Sibling class is untouched.
$check('sibling class still inherits global', $svc->weekendDaysFor($otherClass, null) === [0]);
// Global itself is untouched.
$check('global weekend unaffected by class override', $svc->weekendDays() === [0]);

// Section override wins over class override (most specific).
$svc->setOverride('section', $sectionId, 'attendance_weekend_days', '5'); // Friday off
$check('section override beats class override', $svc->weekendDaysFor($classId, $sectionId) === [5]);
$check('class override still applies to non-overridden section key', $svc->configFor($classId, $sectionId)['school_open_time'] === '07:30');

// Scope-aware calendar respects the override.
$saturday = date('Y-m-d', strtotime('next saturday'));
$cal = new CalendarService();
$check('Saturday is non-working for the Sat+Sun class', $cal->isWorkingDayForScope($saturday, $classId, null) === false);
$check('Saturday is working for a global (Sunday-only) class', $cal->isWorkingDayForScope($saturday, $otherClass, null) === true);
$check('scope reason explains the per-class non-working day', str_contains((string) $cal->reasonForScope($saturday, $classId, null), 'non-working day for this class'));

// Clearing an override restores inheritance.
$svc->clearOverride('class', $classId, 'attendance_weekend_days');
$check('cleared override falls back to global', $svc->weekendDaysFor($classId, null) === [0]);

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
