<?php

declare(strict_types=1);

/**
 * Calendar / working-day authority test: holiday + weekend detection and the
 * configurable working week that gates attendance marking.
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Services\CalendarService;
use Core\Database\QueryBuilder;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$putSetting = static function (string $key, string $val): void {
    if (QueryBuilder::table('settings')->where('key', $key)->first() !== null) {
        QueryBuilder::table('settings')->where('key', $key)->update(['value' => $val, 'updated_at' => date('Y-m-d H:i:s')]);
    } else {
        QueryBuilder::table('settings')->insert(['group' => 'general', 'key' => $key, 'value' => $val, 'type' => 'string', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }
};

$sunday   = date('Y-m-d', strtotime('next sunday'));
$saturday = date('Y-m-d', strtotime('next saturday'));
$monday   = date('Y-m-d', strtotime('next monday'));
$tuesday  = date('Y-m-d', strtotime('next tuesday'));

// --- Default working week (Sunday off) ---
$putSetting('attendance_weekend_days', ''); // -> default Sunday
$cal = new CalendarService();
$check('default weekend is Sunday only', $cal->weekendDays() === [0]);
$check('Sunday is a weekend', $cal->isWeekend($sunday) === true);
$check('Saturday is NOT a weekend by default', $cal->isWeekend($saturday) === false);
$check('a normal Tuesday is a working day', $cal->isWorkingDay($tuesday) === true);
$check('Sunday is not a working day', $cal->isWorkingDay($sunday) === false);
$check('Sunday reason mentions non-working/weekend', str_contains((string) $cal->reason($sunday), 'non-working'));

// --- Holiday detection ---
QueryBuilder::table('holidays')->insert([
    'date' => $monday, 'name' => 'Founders Day', 'type' => 'institute',
    'description' => 'Test', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
]);
$cal2 = new CalendarService();
$check('holiday detected on the date', $cal2->isHoliday($monday) === true);
$check('holiday date is not a working day', $cal2->isWorkingDay($monday) === false);
$check('holiday reason names the holiday', str_contains((string) $cal2->reason($monday), 'Founders Day'));
$check('holiday type persisted (migration 028)', (string) QueryBuilder::table('holidays')->where('date', $monday)->value('type') === 'institute');
$check('a non-holiday weekday has no reason (working)', $cal2->reason($tuesday) === null);

// --- Configurable working week: add Saturday as a weekend ---
$putSetting('attendance_weekend_days', '0,6');
$cal3 = new CalendarService();
$check('config adds Saturday to the weekend', in_array(6, $cal3->weekendDays(), true));
$check('Saturday now a weekend', $cal3->isWeekend($saturday) === true);
$check('Saturday now not a working day', $cal3->isWorkingDay($saturday) === false);

// Robustness: bad config falls back to Sunday.
$putSetting('attendance_weekend_days', 'garbage,99,-3');
$check('invalid config falls back to Sunday', (new CalendarService())->weekendDays() === [0]);

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
