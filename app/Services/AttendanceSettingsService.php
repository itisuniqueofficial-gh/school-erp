<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Global Attendance & Academic Calendar configuration.
 *
 * Single, database-backed source of truth for attendance type, the working
 * week and the timetable time-arrangement. Nothing is hardcoded: every module
 * (attendance entry, dashboards, timetable, reports, APIs) reads these values,
 * so changing a setting instantly reconfigures the ERP with no code changes.
 */
final class AttendanceSettingsService
{
    /** Attendance capture modes. */
    public const TYPES = [
        'daily'   => 'Daily Attendance',
        'lecture' => 'Lecture Wise Attendance',
        'period'  => 'Period Wise Attendance',
        'session' => 'Session Wise Attendance',
        'manual'  => 'Manual Attendance',
    ];

    /** Day-of-week numbers use PHP date('w'): 0 = Sunday ... 6 = Saturday. */
    public const DAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday',
    ];

    /** @var array<string,string> Setting key => default value. */
    private const DEFAULTS = [
        'attendance_type'         => 'daily',
        'attendance_weekend_days' => '0',      // Sunday
        'week_start_day'          => '1',      // Monday
        'school_open_time'        => '08:00',
        'school_close_time'       => '14:00',
        'assembly_duration_min'   => '15',
        'period_duration_min'     => '40',
        'break_duration_min'      => '5',
        'lunch_duration_min'      => '30',
        'periods_count'           => '7',
        'buffer_between_min'      => '0',
    ];

    public function get(string $key, ?string $default = null): string
    {
        $val = QueryBuilder::table('settings')->where('key', $key)->value('value');
        if ($val === null || $val === '') {
            return $default ?? (self::DEFAULTS[$key] ?? '');
        }
        return (string) $val;
    }

    public function set(string $key, string $value): void
    {
        if (QueryBuilder::table('settings')->where('key', $key)->first() !== null) {
            QueryBuilder::table('settings')->where('key', $key)->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            QueryBuilder::table('settings')->insert([
                'group' => 'general', 'key' => $key, 'value' => $value, 'type' => 'string',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** @return array<string,string> The full configuration (with defaults applied). */
    public function config(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = $this->get($key, $default);
        }
        return $out;
    }

    public function attendanceType(): string
    {
        $t = $this->get('attendance_type', 'daily');
        return isset(self::TYPES[$t]) ? $t : 'daily';
    }

    /** @return array<int,int> Weekend day numbers (0-6). */
    public function weekendDays(): array
    {
        return $this->parseWeekend($this->get('attendance_weekend_days', '0'));
    }

    /** @return array<int,int> */
    private function parseWeekend(string $raw): array
    {
        $days = [];
        foreach (explode(',', $raw) as $p) {
            $p = trim($p);
            if ($p !== '' && ctype_digit($p) && (int) $p >= 0 && (int) $p <= 6) {
                $days[(int) $p] = (int) $p;
            }
        }
        return array_values($days);
    }

    // --- Per-scope overrides (Global + Local) ------------------------------

    /** Override value for a class/section, or null when inherited from global. */
    public function overrideValue(string $scopeType, int $scopeId, string $key): ?string
    {
        $v = QueryBuilder::table('scope_settings')
            ->where('scope_type', $scopeType)->where('scope_id', $scopeId)->where('key', $key)
            ->value('value');
        return ($v === null || $v === '') ? null : (string) $v;
    }

    public function setOverride(string $scopeType, int $scopeId, string $key, string $value): void
    {
        $exists = QueryBuilder::table('scope_settings')
            ->where('scope_type', $scopeType)->where('scope_id', $scopeId)->where('key', $key)->first();
        if ($exists !== null) {
            QueryBuilder::table('scope_settings')
                ->where('scope_type', $scopeType)->where('scope_id', $scopeId)->where('key', $key)
                ->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            QueryBuilder::table('scope_settings')->insert([
                'scope_type' => $scopeType, 'scope_id' => $scopeId, 'key' => $key, 'value' => $value,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function clearOverride(string $scopeType, int $scopeId, string $key): void
    {
        QueryBuilder::table('scope_settings')
            ->where('scope_type', $scopeType)->where('scope_id', $scopeId)->where('key', $key)->delete();
    }

    /**
     * Effective configuration for a class/section: global defaults with class
     * overrides applied, then section overrides (most specific wins). Passing
     * nulls returns the global config.
     *
     * @return array<string,string>
     */
    public function configFor(?int $classId, ?int $sectionId): array
    {
        $cfg = $this->config();
        foreach (array_keys($cfg) as $key) {
            if ($classId) {
                $ov = $this->overrideValue('class', $classId, $key);
                if ($ov !== null) { $cfg[$key] = $ov; }
            }
            if ($sectionId) {
                $ov = $this->overrideValue('section', $sectionId, $key);
                if ($ov !== null) { $cfg[$key] = $ov; }
            }
        }
        return $cfg;
    }

    /** @return array<int,int> Weekend days for a specific class/section. */
    public function weekendDaysFor(?int $classId, ?int $sectionId): array
    {
        return $this->parseWeekend($this->configFor($classId, $sectionId)['attendance_weekend_days'] ?? '0');
    }

    /** Auto-generated schedule for a specific class/section. */
    public function periodScheduleFor(?int $classId, ?int $sectionId): array
    {
        return $this->periodSchedule($this->configFor($classId, $sectionId));
    }

    public function scheduleEndTimeForScope(?int $classId, ?int $sectionId): string
    {
        return $this->scheduleEndTime($this->configFor($classId, $sectionId));
    }

    /** Working day numbers = all days minus the configured weekend. @return array<int,int> */
    public function workingDays(): array
    {
        $weekend = $this->weekendDays();
        return array_values(array_filter([1, 2, 3, 4, 5, 6, 0], static fn ($d) => !in_array($d, $weekend, true)));
    }

    /**
     * Auto-generate the daily period schedule from the configured times.
     * Deterministic and conflict-free by construction (sequential, no overlap).
     *
     * @return array<int,array{label:string,start:string,end:string,type:string}>
     */
    public function periodSchedule(?array $config = null): array
    {
        $c = $config ?? $this->config();
        $cursor = $this->toMinutes((string) ($c['school_open_time'] ?? '08:00'));
        if ($cursor < 0) { $cursor = 480; }
        $slots = [];
        $add = static function (string $label, int $from, int $len, string $type) use (&$slots): int {
            $to = $from + $len;
            $slots[] = ['label' => $label, 'start' => sprintf('%02d:%02d', intdiv($from, 60), $from % 60), 'end' => sprintf('%02d:%02d', intdiv($to, 60), $to % 60), 'type' => $type];
            return $to;
        };

        $assembly = (int) $c['assembly_duration_min'];
        if ($assembly > 0) {
            $cursor = $add('Assembly', $cursor, $assembly, 'assembly');
        }

        $periods = max(1, (int) $c['periods_count']);
        $pdur = max(1, (int) $c['period_duration_min']);
        $brk = max(0, (int) $c['break_duration_min']);
        $buffer = max(0, (int) $c['buffer_between_min']);
        $lunch = max(0, (int) $c['lunch_duration_min']);
        $lunchAfter = (int) ceil($periods / 2);

        for ($i = 1; $i <= $periods; $i++) {
            $cursor = $add('Period ' . $i, $cursor, $pdur, 'period');
            if ($i === $lunchAfter && $lunch > 0) {
                $cursor = $add('Lunch', $cursor, $lunch, 'lunch');
            } elseif ($i < $periods && $brk > 0) {
                $cursor = $add('Break', $cursor, $brk, 'break');
            }
            if ($i < $periods && $buffer > 0) {
                $cursor += $buffer;
            }
        }
        return $slots;
    }

    /** End time (HH:MM) implied by the generated schedule. */
    public function scheduleEndTime(?array $config = null): string
    {
        $end = $this->scheduleEndMinutes($config);
        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
    }

    /**
     * Raw end-of-day in minutes since midnight (can exceed 24h for an
     * over-packed config — used to detect overflow reliably).
     */
    public function scheduleEndMinutes(?array $config = null): int
    {
        $c = $config ?? $this->config();
        $t = $this->toMinutes((string) ($c['school_open_time'] ?? '08:00'));
        if ($t < 0) { $t = 480; }
        $assembly = max(0, (int) ($c['assembly_duration_min'] ?? 0));
        $periods = max(1, (int) ($c['periods_count'] ?? 1));
        $pdur = max(1, (int) ($c['period_duration_min'] ?? 1));
        $brk = max(0, (int) ($c['break_duration_min'] ?? 0));
        $buffer = max(0, (int) ($c['buffer_between_min'] ?? 0));
        $lunch = max(0, (int) ($c['lunch_duration_min'] ?? 0));
        $lunchAfter = (int) ceil($periods / 2);
        if ($assembly > 0) { $t += $assembly; }
        for ($i = 1; $i <= $periods; $i++) {
            $t += $pdur;
            if ($i === $lunchAfter && $lunch > 0) {
                $t += $lunch;
            } elseif ($i < $periods && $brk > 0) {
                $t += $brk;
            }
            if ($i < $periods && $buffer > 0) {
                $t += $buffer;
            }
        }
        return $t;
    }

    /**
     * Validate a submitted configuration. Returns a list of clear, actionable
     * error messages (empty = valid).
     *
     * @param array<string,mixed> $in
     * @return array<int,string>
     */
    public function validate(array $in): array
    {
        $errors = [];
        $type = (string) ($in['attendance_type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            $errors[] = 'Choose a valid attendance type.';
        }

        $open = $this->toMinutes((string) ($in['school_open_time'] ?? ''));
        $close = $this->toMinutes((string) ($in['school_close_time'] ?? ''));
        if ($open < 0 || $close < 0) {
            $errors[] = 'Opening and closing times must be valid times (HH:MM).';
        } elseif ($close <= $open) {
            $errors[] = 'School closing time must be later than the opening time.';
        }

        foreach (['period_duration_min' => 'Period duration', 'periods_count' => 'Number of periods'] as $k => $label) {
            if ((int) ($in[$k] ?? 0) < 1) {
                $errors[] = $label . ' must be at least 1.';
            }
        }
        foreach (['break_duration_min' => 'Break duration', 'lunch_duration_min' => 'Lunch duration', 'assembly_duration_min' => 'Assembly duration', 'buffer_between_min' => 'Buffer time'] as $k => $label) {
            if ((int) ($in[$k] ?? 0) < 0) {
                $errors[] = $label . ' cannot be negative.';
            }
        }

        // The generated schedule must fit inside working hours.
        if ($errors === [] && $open >= 0 && $close > $open) {
            $probeCfg = [
                'school_open_time'      => (string) ($in['school_open_time'] ?? self::DEFAULTS['school_open_time']),
                'assembly_duration_min' => (string) (int) ($in['assembly_duration_min'] ?? 0),
                'period_duration_min'   => (string) max(1, (int) ($in['period_duration_min'] ?? 1)),
                'break_duration_min'    => (string) (int) ($in['break_duration_min'] ?? 0),
                'lunch_duration_min'    => (string) (int) ($in['lunch_duration_min'] ?? 0),
                'periods_count'         => (string) max(1, (int) ($in['periods_count'] ?? 1)),
                'buffer_between_min'    => (string) (int) ($in['buffer_between_min'] ?? 0),
            ];
            $end = $this->scheduleEndMinutes($probeCfg);
            if ($end > $close) {
                $errors[] = sprintf(
                    'The periods and breaks run until %s, which is past the %s closing time. Reduce the number of periods, durations or breaks.',
                    sprintf('%02d:%02d', intdiv($end, 60) % 24, $end % 60),
                    (string) ($in['school_close_time'] ?? '')
                );
            }
        }

        // Weekend selection must be valid day numbers (empty = no weekend, allowed).
        foreach ((array) ($in['weekend_days'] ?? []) as $d) {
            if (!ctype_digit((string) $d) || (int) $d < 0 || (int) $d > 6) {
                $errors[] = 'Weekend selection contains an invalid day.';
                break;
            }
        }
        return $errors;
    }

    /** Convert HH:MM to minutes since midnight, or -1 if invalid. */
    private function toMinutes(string $hhmm): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $m)) {
            return -1;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return -1;
        }
        return $h * 60 + $min;
    }
}
