<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Central institution configuration: which optional modules are enabled, the
 * institution profile (type / board), and configurable terminology
 * (e.g. "Student" -> "Learner"). Backed by the `settings` table so it can be
 * changed from the Admin panel with no code changes.
 *
 * Values are cached per-request to avoid repeated queries.
 */
final class InstitutionService
{
    /**
     * Optional modules that can be turned on/off per institution. Core modules
     * (students, academic, users, settings) are intentionally NOT listed here —
     * they are always available.
     *
     * @var array<string,string>
     */
    private const MODULES = [
        'fees'          => 'Fees & Charges',
        'payments'      => 'Online Payments',
        'scholarships'  => 'Scholarships & Benefits',
        'library'       => 'Library',
        'transport'     => 'Transport',
        'communication' => 'Communication',
        'hr'            => 'HR & Payroll',
        'documents'     => 'Documents',
        'timetable'     => 'Timetable',
    ];

    /** Terminology keys with their default (school-oriented) labels. */
    private const TERMS = [
        'student'     => 'Student',
        'teacher'     => 'Teacher',
        'class'       => 'Class',
        'section'     => 'Section',
        'institution' => 'School',
        'principal'   => 'Principal',
    ];

    /** @var array<string,string>|null request-level cache of raw settings */
    private static ?array $cache = null;

    /** @return array<string,string> */
    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (QueryBuilder::table('settings')->where('group', 'institution')->get() as $row) {
                    self::$cache[(string) $row['key']] = (string) $row['value'];
                }
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    private static function put(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        $exists = QueryBuilder::table('settings')->where('key', $key)->first();
        if ($exists) {
            QueryBuilder::table('settings')->where('key', $key)->update(['value' => $value, 'updated_at' => $now]);
        } else {
            QueryBuilder::table('settings')->insert([
                'group' => 'institution', 'key' => $key, 'value' => $value,
                'type' => 'string', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        self::$cache = null; // invalidate
    }

    /** @return array<string,string> */
    public static function availableModules(): array
    {
        return self::MODULES;
    }

    public static function isModule(string $key): bool
    {
        return isset(self::MODULES[$key]);
    }

    /**
     * Is an optional module enabled? Unknown/core modules are always enabled.
     * Optional modules default to ENABLED unless explicitly turned off, so
     * existing installs keep working.
     */
    public function moduleEnabled(string $key): bool
    {
        if (!isset(self::MODULES[$key])) {
            return true; // core / unknown modules are always available
        }
        $raw = self::load();
        return ($raw['institution.module.' . $key] ?? '1') === '1';
    }

    public function setModule(string $key, bool $enabled): void
    {
        if (!isset(self::MODULES[$key])) {
            return;
        }
        self::put('institution.module.' . $key, $enabled ? '1' : '0');
    }

    /** @return array<string,bool> module key => enabled */
    public function modules(): array
    {
        $out = [];
        foreach (array_keys(self::MODULES) as $key) {
            $out[$key] = $this->moduleEnabled($key);
        }
        return $out;
    }

    /** Configurable label for a terminology key (e.g. term('student') -> "Learner"). */
    public function term(string $key, ?string $default = null): string
    {
        $fallback = $default ?? (self::TERMS[$key] ?? ucfirst($key));
        if (!isset(self::TERMS[$key])) {
            return $fallback;
        }
        $raw = self::load();
        $val = trim((string) ($raw['institution.term.' . $key] ?? ''));
        return $val !== '' ? $val : $fallback;
    }

    public function setTerm(string $key, string $label): void
    {
        if (!isset(self::TERMS[$key])) {
            return;
        }
        self::put('institution.term.' . $key, trim($label));
    }

    /** @return array<string,string> */
    public static function termDefaults(): array
    {
        return self::TERMS;
    }

    // -- Institution profile (type / board / name) --------------------------

    public function profile(string $key, string $default = ''): string
    {
        $raw = self::load();
        return (string) ($raw['institution.profile.' . $key] ?? $default);
    }

    public function setProfile(string $key, string $value): void
    {
        self::put('institution.profile.' . $key, trim($value));
    }
}
