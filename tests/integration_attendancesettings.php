<?php

declare(strict_types=1);

/**
 * Global Attendance & Calendar settings test: persistence, working-week
 * derivation, auto period-schedule generation, and validation rules.
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Services\AttendanceSettingsService;
use App\Services\CalendarService;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$svc = new AttendanceSettingsService();

// Defaults available before any save.
$check('default attendance type is daily', $svc->attendanceType() === 'daily');

// Persist a configuration.
$svc->set('attendance_type', 'period');
$svc->set('attendance_weekend_days', '0,6'); // Sun + Sat
$svc->set('school_open_time', '08:00');
$svc->set('school_close_time', '15:00');
$svc->set('assembly_duration_min', '15');
$svc->set('period_duration_min', '40');
$svc->set('break_duration_min', '5');
$svc->set('lunch_duration_min', '30');
$svc->set('periods_count', '6');
$svc->set('buffer_between_min', '0');

$fresh = new AttendanceSettingsService();
$check('attendance type persisted', $fresh->attendanceType() === 'period');
$check('weekend days parsed (Sun+Sat)', $fresh->weekendDays() === [0, 6]);
$check('working days exclude weekend', $fresh->workingDays() === [1, 2, 3, 4, 5]);

// CalendarService must respect the same weekend setting (shared source of truth).
$check('CalendarService reads the same weekend', (new CalendarService())->weekendDays() === [0, 6]);

// Auto-generated schedule.
$sched = $fresh->periodSchedule();
$periodSlots = array_values(array_filter($sched, static fn ($s) => $s['type'] === 'period'));
$check('generates the configured number of periods', count($periodSlots) === 6);
$check('schedule starts at the opening time', ($sched[0]['start'] ?? '') === '08:00');
$check('assembly slot present first', ($sched[0]['label'] ?? '') === 'Assembly');
$check('a lunch slot is inserted', in_array('lunch', array_column($sched, 'type'), true));
$check('no slot overlaps (end == next start)', (function () use ($sched) {
    for ($i = 1; $i < count($sched); $i++) {
        // buffer=0 here, so each slot should start exactly when the previous ended
        if ($sched[$i]['start'] !== $sched[$i - 1]['end']) { return false; }
    }
    return true;
})());
$check('schedule end time within closing time', $fresh->scheduleEndTime() <= '15:00');

// --- Validation ---
$base = ['attendance_type' => 'daily', 'school_open_time' => '08:00', 'school_close_time' => '15:00', 'period_duration_min' => '40', 'periods_count' => '6', 'break_duration_min' => '5', 'lunch_duration_min' => '30', 'assembly_duration_min' => '15', 'buffer_between_min' => '0', 'weekend_days' => ['0']];
$check('valid config passes validation', $svc->validate($base) === []);

$bad1 = array_merge($base, ['school_close_time' => '07:00']); // close before open
$check('close-before-open rejected', in_array('School closing time must be later than the opening time.', $svc->validate($bad1), true));

$bad2 = array_merge($base, ['attendance_type' => 'telepathy']);
$check('invalid attendance type rejected', $svc->validate($bad2) !== []);

$bad3 = array_merge($base, ['periods_count' => '0']);
$check('zero periods rejected', $svc->validate($bad3) !== []);

// Too many long periods that overflow the day.
$bad4 = array_merge($base, ['periods_count' => '20', 'period_duration_min' => '60', 'lunch_duration_min' => '60']);
$errs4 = $svc->validate($bad4);
$check('overflowing schedule rejected with a clear message', $errs4 !== [] && str_contains(implode(' ', $errs4), 'past the'));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
