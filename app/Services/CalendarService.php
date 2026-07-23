<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Calendar / working-day authority.
 *
 * Single source of truth for "is this a working day?" decisions used by
 * attendance marking and reporting. Fully configurable — the working week
 * (weekend days) is read from settings, and holidays from the `holidays`
 * table — so no working-day rule is hardcoded.
 */
final class CalendarService
{
    /**
     * Weekend day numbers (0 = Sunday ... 6 = Saturday), from the
     * `attendance_weekend_days` setting (comma-separated). Defaults to Sunday.
     *
     * @return array<int,int>
     */
    public function weekendDays(): array
    {
        $raw = QueryBuilder::table('settings')->where('key', 'attendance_weekend_days')->value('value');
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return [0]; // Sunday by default
        }
        $days = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $n = (int) $part;
                if ($n >= 0 && $n <= 6) {
                    $days[$n] = $n;
                }
            }
        }
        return $days === [] ? [0] : array_values($days);
    }

    public function isWeekend(string $date): bool
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return false;
        }
        return in_array((int) date('w', $ts), $this->weekendDays(), true);
    }

    /**
     * The holiday record on a date, or null.
     *
     * @return array<string,mixed>|null
     */
    public function holiday(string $date): ?array
    {
        $d = date('Y-m-d', (int) (strtotime($date) ?: time()));
        return QueryBuilder::table('holidays')->where('date', $d)->first();
    }

    public function isHoliday(string $date): bool
    {
        return $this->holiday($date) !== null;
    }

    /** A working day is neither a weekend nor a holiday. */
    public function isWorkingDay(string $date): bool
    {
        return !$this->isWeekend($date) && !$this->isHoliday($date);
    }

    /**
     * Scope-aware working day: uses the class/section weekend override (falling
     * back to global) plus holidays. Holidays are institution-wide.
     */
    public function isWorkingDayForScope(string $date, ?int $classId, ?int $sectionId): bool
    {
        if ($this->isHoliday($date)) {
            return false;
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return true;
        }
        $weekend = (new AttendanceSettingsService())->weekendDaysFor($classId, $sectionId);
        return !in_array((int) date('w', $ts), $weekend, true);
    }

    /** Scope-aware reason a date is not a working day, or null if it is one. */
    public function reasonForScope(string $date, ?int $classId, ?int $sectionId): ?string
    {
        $h = $this->holiday($date);
        if ($h !== null) {
            $label = trim((string) ($h['name'] ?? 'Holiday'));
            return 'Attendance is blocked on ' . date('d M Y', (int) (strtotime($date) ?: time()))
                . ' because it is a holiday' . ($label !== '' ? ' (' . $label . ')' : '') . '.';
        }
        $ts = strtotime($date);
        if ($ts !== false) {
            $weekend = (new AttendanceSettingsService())->weekendDaysFor($classId, $sectionId);
            if (in_array((int) date('w', $ts), $weekend, true)) {
                return 'Attendance is blocked on ' . date('l, d M Y', $ts)
                    . ' because it is a non-working day for this class. Adjust the working week in Attendance settings (global or per-class) if this is incorrect.';
            }
        }
        return null;
    }

    /**
     * Human-readable reason a date is not a working day, or null if it is one.
     * Used to give attendance markers a clear, actionable message.
     */
    public function reason(string $date): ?string
    {
        $h = $this->holiday($date);
        if ($h !== null) {
            $label = trim((string) ($h['name'] ?? 'Holiday'));
            return 'Attendance is blocked on ' . date('d M Y', (int) (strtotime($date) ?: time()))
                . ' because it is a holiday' . ($label !== '' ? ' (' . $label . ')' : '') . '.';
        }
        if ($this->isWeekend($date)) {
            return 'Attendance is blocked on ' . date('l, d M Y', (int) (strtotime($date) ?: time()))
                . ' because it is a non-working day (weekend). Adjust the working week in Attendance settings if this is incorrect.';
        }
        return null;
    }
}
