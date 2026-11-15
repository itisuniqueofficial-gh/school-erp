<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Permanent Roll Number (PRN) and admission-number issuing.
 *
 * PRN = YYYYMMDD (admission date) + admission sequence, zero-padded to at least
 * 4 digits, e.g. admission 2026-11-15 / ADM-2026-0001 => 202611150001.
 *
 * Guarantees:
 *  - The sequence comes from `id_sequences` (prefix ADMN, per admission year)
 *    under a row lock inside the caller's transaction, so concurrent admissions
 *    can never receive the same number.
 *  - For a given admission year the sequence is unique, so (date, sequence) is
 *    unique. The UNIQUE index on students.prn and the append-only prn_registry
 *    are the final guard, and a collision just moves on to the next sequence.
 *  - A PRN is written once. It is never regenerated or reused.
 *
 * Legacy admission numbers (e.g. "ADM-0001") are kept as they are. Their
 * trailing number is reused as the PRN sequence when it is unambiguous,
 * otherwise a fresh sequence value is issued (and noted in prn_registry).
 */
final class StudentIdentifierService
{
    private const MAX_ATTEMPTS = 200;

    public function __construct(private readonly IdSequenceService $ids = new IdSequenceService())
    {
    }

    /** Build a PRN from a Y-m-d date and an admission sequence number. */
    public static function formatPrn(string $date, int $seq): string
    {
        return date('Ymd', (int) strtotime($date)) . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function isValidPrn(string $prn): bool
    {
        return preg_match('/^\d{12,}$/', $prn) === 1;
    }

    /** Normalise to Y-m-d, or null when missing / not a real calendar date. */
    public static function normalizeDate(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || str_starts_with($v, '0000')) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', substr($v, 0, 10));
        if ($d === false || $d->format('Y-m-d') !== substr($v, 0, 10)) {
            return null;
        }
        return $d->format('Y-m-d');
    }

    /** Sequence embedded in a conventional admission number, or null. */
    public static function sequenceFromAdmissionNo(string $admissionNo): ?int
    {
        if (preg_match('/^[A-Za-z]+-(?:\d{4}-)?(\d{1,9})$/', trim($admissionNo), $m) === 1 && (int) $m[1] > 0) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Next admission number as it would be issued now (display only; the real
     * number is reserved atomically at creation time).
     */
    public function peekAdmissionNo(?string $date = null): string
    {
        $date = self::normalizeDate($date) ?? date('Y-m-d');
        $year = (int) substr($date, 0, 4);
        $row = QueryBuilder::table('id_sequences')->where('prefix', 'ADMN')->where('year', $year)->first();
        $seq = max(1, (int) ($row['next_value'] ?? 1), $this->highestAdmissionSuffix($year) + 1);
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++, $seq++) {
            $no = $this->admissionNo($year, $seq);
            if (!$this->admissionNoTaken($no)) {
                return $no;
            }
        }
        return $this->admissionNo($year, $seq);
    }

    /**
     * Reserve identifiers for a student about to be inserted. MUST run inside
     * the same transaction as the INSERT (Connection::transaction joins).
     *
     * @return array{admission_no:string,admission_date:string,prn:string,notes:?string}
     */
    public function reserve(?string $admissionNo, mixed $admissionDate): array
    {
        $date = self::normalizeDate($admissionDate) ?? date('Y-m-d');
        $year = (int) substr($date, 0, 4);
        $admissionNo = trim((string) $admissionNo);
        $notes = [];

        return Connection::instance()->transaction(function () use ($admissionNo, $date, $year, &$notes): array {
            $preferred = $admissionNo !== '' ? self::sequenceFromAdmissionNo($admissionNo) : null;
            if ($admissionNo === '') {
                // Continue after any ADM-YYYY-NNNN numbers that exist (incl. soft-deleted).
                $this->ids->ensureAtLeast('ADMN', $year, $this->highestAdmissionSuffix($year) + 1);
            }

            for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
                if ($admissionNo === '') {
                    // Issue a fresh admission number; its sequence doubles as the PRN sequence.
                    $seq = $this->ids->next('ADMN', $year);
                    $no = $this->admissionNo($year, $seq);
                    if ($this->admissionNoTaken($no)) {
                        continue; // a legacy/manual number already uses it
                    }
                    $prn = self::formatPrn($date, $seq);
                    if ($this->prnTaken($prn)) {
                        continue;
                    }
                    return ['admission_no' => $no, 'admission_date' => $date, 'prn' => $prn, 'notes' => null];
                }

                // Caller supplied an admission number (kept untouched).
                $seq = $preferred ?? $this->ids->next('ADMN', $year);
                $prn = self::formatPrn($date, $seq);
                if (!$this->prnTaken($prn)) {
                    if ($preferred === null) {
                        $notes[] = 'sequence issued (admission_no not in a recognised format)';
                    }
                    return ['admission_no' => $admissionNo, 'admission_date' => $date, 'prn' => $prn,
                        'notes' => $notes ? implode('; ', $notes) : null];
                }
                // Same date + sequence already used: fall back to a fresh sequence.
                if ($preferred !== null) {
                    $notes[] = 'admission sequence ' . $preferred . ' already used on ' . $date . '; new sequence issued';
                    $preferred = null;
                }
            }
            throw new \RuntimeException('Could not allocate a unique PRN.');
        });
    }

    /**
     * Record an issued PRN in the append-only registry. Call after the student
     * row exists, in the same transaction.
     */
    public function register(int $studentId, array $ids, ?string $uid, string $source = 'admission'): void
    {
        QueryBuilder::table('prn_registry')->insert([
            'prn'            => $ids['prn'],
            'student_id'     => $studentId,
            'student_uid'    => $uid,
            'admission_no'   => $ids['admission_no'],
            'admission_date' => $ids['admission_date'],
            'source'         => $source,
            'notes'          => isset($ids['notes']) ? substr((string) $ids['notes'], 0, 255) : null,
            'issued_by'      => $this->actor(),
            'issued_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function prnTaken(string $prn): bool
    {
        return QueryBuilder::table('prn_registry')->where('prn', $prn)->count() > 0
            || QueryBuilder::table('students')->where('prn', $prn)->count() > 0;
    }

    /** Highest NNNN among existing ADM-YYYY-NNNN numbers (soft-deleted rows included). */
    private function highestAdmissionSuffix(int $year): int
    {
        $prefix = 'ADM-' . $year . '-';
        $max = Connection::instance()->scalar(
            'SELECT MAX(CAST(SUBSTRING(`admission_no`, ?) AS UNSIGNED)) FROM `students` WHERE `admission_no` LIKE ? AND SUBSTRING(`admission_no`, ?) REGEXP \'^[0-9]+$\'',
            [strlen($prefix) + 1, $prefix . '%', strlen($prefix) + 1]
        );
        return (int) $max;
    }

    private function admissionNoTaken(string $no): bool
    {
        // Raw table: includes soft-deleted students.
        return QueryBuilder::table('students')->where('admission_no', $no)->count() > 0;
    }

    private function admissionNo(int $year, int $seq): string
    {
        return sprintf('ADM-%d-%04d', $year, $seq);
    }

    private function actor(): ?int
    {
        try {
            return auth()->id();
        } catch (\Throwable) {
            return null;
        }
    }

    // -----------------------------------------------------------------------
    // Backfill
    // -----------------------------------------------------------------------

    /**
     * Give every existing student a PRN (and an enrollment/roll number when the
     * data allows it). Idempotent, never overwrites an existing PRN, never
     * deletes data, and reports what it could not resolve instead of guessing.
     *
     * @return array<string,mixed>
     */
    public function backfill(bool $dryRun = false): array
    {
        $r = [
            'dry_run' => $dryRun, 'prn_issued' => 0, 'prn_with_notes' => 0, 'admission_dates_filled' => 0,
            'admission_no_issued' => 0, 'enrollments_created' => 0, 'rolls_reassigned' => 0,
            'student_roll_synced' => 0, 'unresolved' => [],
        ];
        $conn = Connection::instance();

        $students = $conn->select('SELECT * FROM `students` WHERE `prn` IS NULL ORDER BY `id`');
        foreach ($students as $s) {
            $sid = (int) $s['id'];
            $notes = [];
            $date = self::normalizeDate($s['admission_date'] ?? null);
            if ($date === null) {
                $date = self::normalizeDate($s['created_at'] ?? null);
                $notes[] = $date !== null ? 'admission_date missing; created_at used' : 'admission_date and created_at missing; backfill date used';
                $date ??= date('Y-m-d');
                $r['admission_dates_filled']++;
            }
            if ($dryRun) {
                $r['prn_issued']++;
                continue;
            }
            try {
                $conn->transaction(function () use ($s, $sid, $date, $notes, &$r): void {
                    $had = trim((string) ($s['admission_no'] ?? ''));
                    $ids = $this->reserve($had !== '' ? $had : null, $date);
                    if ($had === '') {
                        $r['admission_no_issued']++;
                        $ids['notes'] = trim(($ids['notes'] ?? '') . '; admission_no was blank; issued ' . $ids['admission_no'], '; ');
                    }
                    if ($notes) {
                        $ids['notes'] = trim(implode('; ', $notes) . '; ' . ($ids['notes'] ?? ''), '; ');
                    }
                    $upd = ['prn' => $ids['prn']];
                    if ($had === '') {
                        $upd['admission_no'] = $ids['admission_no'];
                    }
                    if (self::normalizeDate($s['admission_date'] ?? null) === null) {
                        $upd['admission_date'] = $date;
                    }
                    QueryBuilder::table('students')->where('id', $sid)->whereNull('prn')->update($upd);
                    $this->register($sid, $ids, $s['uid'] ?? null, 'backfill');
                    if (!empty($ids['notes'])) {
                        $r['prn_with_notes']++;
                    }
                    $r['prn_issued']++;
                });
            } catch (\Throwable $e) {
                $r['unresolved'][] = ['student_id' => $sid, 'issue' => 'PRN backfill failed: ' . $e->getMessage()];
            }
        }

        $this->backfillEnrollments($dryRun, $r);
        return $r;
    }

    /** @param array<string,mixed> $r */
    private function backfillEnrollments(bool $dryRun, array &$r): void
    {
        $conn = Connection::instance();
        $enroll = new EnrollmentService();

        // Students with a class + year but no enrollment for that year.
        $rows = $conn->select(
            'SELECT s.* FROM `students` s
             WHERE s.`deleted_at` IS NULL AND s.`class_id` IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM `student_enrollments` e WHERE e.`student_id` = s.`id` AND e.`academic_year_id` = s.`academic_year_id`)
             ORDER BY s.`id`'
        );
        foreach ($rows as $s) {
            $sid = (int) $s['id'];
            $yearId = (int) ($s['academic_year_id'] ?? 0);
            if ($yearId === 0) {
                $r['unresolved'][] = ['student_id' => $sid, 'issue' => 'Has a class but no academic year: no enrollment/roll number created. Edit the student and set the academic year.'];
                continue;
            }
            if ($dryRun) {
                $r['enrollments_created']++;
                continue;
            }
            // Keep the legacy roll number if it is a free positive integer; otherwise next serial.
            $legacy = ctype_digit(trim((string) ($s['roll_no'] ?? ''))) ? (int) $s['roll_no'] : 0;
            $res = $enroll->enroll($sid, $yearId, (int) $s['class_id'], ((int) $s['section_id']) ?: null,
                $legacy > 0 && !$enroll->rollTaken($yearId, (int) $s['class_id'], ((int) $s['section_id']) ?: null, $legacy) ? $legacy : null,
                'backfill', 'Backfilled from existing student record');
            if ($res['ok']) {
                $r['enrollments_created']++;
                if ($legacy > 0 && (int) $res['roll_no'] !== $legacy) {
                    $r['rolls_reassigned']++;
                    $r['unresolved'][] = ['student_id' => $sid, 'issue' => "Legacy roll '{$s['roll_no']}' was already taken; new roll {$res['roll_no']} assigned (old value kept in student_roll_history)."];
                }
            } else {
                $r['unresolved'][] = ['student_id' => $sid, 'issue' => 'Enrollment failed: ' . $res['message']];
            }
        }

        // Keep the denormalised students.roll_no equal to the current enrollment.
        $drift = $conn->select(
            'SELECT s.`id`, s.`roll_no`, e.`roll_no` AS enr_roll FROM `students` s
             JOIN `student_enrollments` e ON e.`student_id` = s.`id` AND e.`academic_year_id` = s.`academic_year_id`
             WHERE s.`deleted_at` IS NULL'
        );
        foreach ($drift as $d) {
            if ((string) $d['roll_no'] === (string) $d['enr_roll']) {
                continue;
            }
            if (!$dryRun) {
                QueryBuilder::table('students')->where('id', (int) $d['id'])->update(['roll_no' => (string) $d['enr_roll']]);
            }
            $r['student_roll_synced']++;
        }

        // Enrollments with roll 0 (invalid) get a proper serial.
        $zero = $conn->select('SELECT * FROM `student_enrollments` WHERE `roll_no` = 0 ORDER BY `id`');
        foreach ($zero as $e) {
            if (!$dryRun) {
                $new = $enroll->changeRoll((int) $e['student_id'], (int) $e['academic_year_id'], null, 'Backfill: invalid roll 0', 'backfill');
                if (!$new['ok']) {
                    $r['unresolved'][] = ['student_id' => (int) $e['student_id'], 'issue' => 'Roll 0 could not be fixed: ' . $new['message']];
                    continue;
                }
            }
            $r['rolls_reassigned']++;
        }
    }
}
