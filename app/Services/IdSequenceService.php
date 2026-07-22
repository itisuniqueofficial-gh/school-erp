<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Core\Database\QueryBuilder;
use PDO;

/**
 * Concurrency-safe, never-reused public identifier generator.
 *
 * Uses the `id_sequences` table with a row lock (SELECT ... FOR UPDATE on MySQL)
 * inside a transaction so two simultaneous requests can never produce the same
 * ID. Format: PREFIX-YEAR-NNNNN (e.g. STU-2026-00001).
 *
 * Prefixes: STU (student), TCH (teacher), STF (staff), PAR (parent/guardian),
 * ADM (admin/user), REC (receipt), INV (invoice), ADMN (admission).
 */
final class IdSequenceService
{
    /**
     * Reserve and return the next sequential number for a prefix+year,
     * atomically. The number is consumed and never reused.
     */
    public function next(string $prefix, ?int $year = null): int
    {
        $year ??= (int) date('Y');
        $conn = Connection::instance();

        return $conn->transaction(function () use ($conn, $prefix, $year): int {
            $driver = $conn->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Ensure the row exists.
            $exists = QueryBuilder::table('id_sequences')
                ->where('prefix', $prefix)->where('year', $year)->first();

            if ($exists === null) {
                try {
                    QueryBuilder::table('id_sequences')->insert([
                        'prefix' => $prefix, 'year' => $year, 'next_value' => 1,
                    ]);
                } catch (\Throwable) {
                    // Another request inserted it first; continue to locked read.
                }
            }

            // Lock the row (MySQL) then read + increment.
            if ($driver === 'mysql') {
                $row = $conn->selectOne(
                    'SELECT `id`, `next_value` FROM `id_sequences` WHERE `prefix` = ? AND `year` = ? FOR UPDATE',
                    [$prefix, $year]
                );
            } else {
                $row = $conn->selectOne(
                    'SELECT `id`, `next_value` FROM `id_sequences` WHERE `prefix` = ? AND `year` = ?',
                    [$prefix, $year]
                );
            }

            $value = (int) ($row['next_value'] ?? 1);
            QueryBuilder::table('id_sequences')
                ->where('id', (int) $row['id'])
                ->update(['next_value' => $value + 1]);

            return $value;
        });
    }

    /**
     * Build a formatted public ID, consuming the next sequence value.
     */
    public function generate(string $prefix, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $n = $this->next($prefix, $year);
        return sprintf('%s-%d-%05d', $prefix, $year, $n);
    }

    public function student(?int $year = null): string  { return $this->generate('STU', $year); }
    public function teacher(?int $year = null): string  { return $this->generate('TCH', $year); }
    public function staff(?int $year = null): string    { return $this->generate('STF', $year); }
    public function parent(?int $year = null): string   { return $this->generate('PAR', $year); }
    public function admin(?int $year = null): string    { return $this->generate('ADM', $year); }

    /**
     * Universal 12-digit NUMERIC public identifier. The leading digit(s) encode
     * the entity type; the remainder is a zero-padded, atomically-incremented,
     * never-reused sequence. Uses a year=0 global counter (per lead) with the
     * same row-locked transaction as next(), so it is concurrency-safe and the
     * result is guaranteed unique (also backed by a DB UNIQUE index).
     *
     * e.g. lead '1', seq 1 => "100000000001"; lead '11', seq 1 => "110000000001".
     */
    public function numeric(string $lead): string
    {
        $lead = preg_replace('/\D/', '', $lead) ?: '1';
        $seq = $this->next('UID' . $lead, 0);
        $width = 12 - strlen($lead);
        if ($width < 1) {
            $width = 1;
        }
        return $lead . str_pad((string) $seq, $width, '0', STR_PAD_LEFT);
    }

    // Entity-typed 12-digit IDs (leading digit = type).
    public function uidUser(): string     { return $this->numeric('1'); }
    public function uidStudent(): string  { return $this->numeric('2'); }
    public function uidStaff(): string    { return $this->numeric('3'); }
    public function uidClass(): string    { return $this->numeric('4'); }
    public function uidSection(): string  { return $this->numeric('5'); }
    public function uidSubject(): string  { return $this->numeric('6'); }
    public function uidYear(): string     { return $this->numeric('7'); }
    public function uidDocument(): string { return $this->numeric('8'); }
}
