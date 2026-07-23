<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Universal "Go to Record by ID" resolver.
 *
 * Maps any unique ERP identifier (student/staff public IDs, admission number,
 * 12-digit user ID, employee code, certificate / report-card / request IDs) to
 * the canonical record and its admin route.
 *
 *  - Input is canonicalised first (trim, strip invisible characters, normalise
 *    hyphen/space separators, upper-case prefixed IDs) so "cert-2026-00001",
 *    "CERT 2026 00001" and " CERT-2026-00001 " all resolve to the same record.
 *  - Lookups are archive-INCLUSIVE (soft-deleted records still resolve, flagged
 *    `archived`) so an existing record is never reported as "not found".
 *  - Each hit carries the permission the caller needs, so the UI can tell
 *    "access denied" apart from "not found".
 *  - Only globally-unique columns are matched (never ambiguous roll numbers).
 */
final class IdResolverService
{
    /**
     * @return array{type:string,name:string,uid:string,route:string,permission:string,archived:bool}|null
     */
    public function resolve(string $raw): ?array
    {
        $norm = $this->normalize($raw);
        if ($norm === '') {
            return null;
        }
        $U = $this->canonical($norm);

        // ---- Prefixed identifiers -----------------------------------------
        if (str_starts_with($U, 'RC-')) {
            $r = QueryBuilder::table('report_cards')->whereRaw('UPPER(`rc_uid`) = ?', [$U])->first();
            return $r ? $this->hit('Report Card', $U, $U, '/admin/exams/' . (int) $r['exam_id'] . '/report-cards/' . (int) $r['student_id'], 'exams.view', false) : null;
        }
        if (str_starts_with($U, 'CERT-')) {
            $r = QueryBuilder::table('certificates')->whereRaw('UPPER(`cert_uid`) = ?', [$U])->first();
            return $r ? $this->hit('Certificate', (string) $r['cert_uid'], (string) $r['title'], '/admin/certificates/' . $r['cert_uid'], 'certificates.view', ($r['status'] ?? '') === 'revoked') : null;
        }
        if (str_starts_with($U, 'REQ-')) {
            $r = QueryBuilder::table('certificate_requests')->whereRaw('UPPER(`req_uid`) = ?', [$U])->first();
            return $r ? $this->hit('Certificate Request', (string) $r['req_uid'], ucfirst((string) $r['type']) . ' request', '/admin/certificate-requests', 'certificates.view', false) : null;
        }
        if (str_starts_with($U, 'STU-')) {
            $r = QueryBuilder::table('students')->whereRaw('UPPER(`public_id`) = ?', [$U])->first();
            return $r ? $this->student($r) : null;
        }
        if (str_starts_with($U, 'TCH-') || str_starts_with($U, 'STF-')) {
            $r = QueryBuilder::table('staff')->whereRaw('UPPER(`public_id`) = ?', [$U])->first();
            return $r ? $this->staff($r) : null;
        }

        // ---- Unprefixed: try each entity's unique columns -----------------
        $r = QueryBuilder::table('students')
            ->whereRaw('(UPPER(`admission_no`) = ? OR `uid` = ? OR UPPER(`public_id`) = ?)', [$U, $norm, $U])->first();
        if ($r) {
            return $this->student($r);
        }
        $r = QueryBuilder::table('staff')
            ->whereRaw('(UPPER(`employee_code`) = ? OR `uid` = ? OR UPPER(`public_id`) = ?)', [$U, $norm, $U])->first();
        if ($r) {
            return $this->staff($r);
        }
        if (($r = QueryBuilder::table('certificates')->whereRaw('UPPER(`cert_uid`) = ?', [$U])->first()) !== null) {
            return $this->hit('Certificate', (string) $r['cert_uid'], (string) $r['title'], '/admin/certificates/' . $r['cert_uid'], 'certificates.view', ($r['status'] ?? '') === 'revoked');
        }
        if (($r = QueryBuilder::table('report_cards')->whereRaw('UPPER(`rc_uid`) = ?', [$U])->first()) !== null) {
            return $this->hit('Report Card', (string) $r['rc_uid'], (string) $r['rc_uid'], '/admin/exams/' . (int) $r['exam_id'] . '/report-cards/' . (int) $r['student_id'], 'exams.view', false);
        }

        return null;
    }

    /**
     * Canonicalisation for display/matching. Public so other lookups (global
     * search, verification) can share the exact same rules.
     */
    public function normalize(string $raw): string
    {
        $id = trim($raw);
        // Strip control + zero-width/invisible characters.
        $id = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $id);
        $id = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $id);
        // Collapse internal whitespace.
        return trim((string) preg_replace('/\s+/', ' ', $id));
    }

    /** Upper-cased, separator-normalised form used for ID matching. */
    public function canonical(string $norm): string
    {
        $u = strtoupper($norm);
        // Normalise spacing around hyphens: "CERT - 2026" -> "CERT-2026".
        $u = (string) preg_replace('/\s*-\s*/', '-', $u);
        // Convert a spaced ID like "CERT 2026 00001" into "CERT-2026-00001".
        if (preg_match('/^[A-Z]{2,6}[\s-]\S/', $u) === 1) {
            $u = (string) preg_replace('/\s+/', '-', $u);
        }
        return $u;
    }

    private function student(array $r): array
    {
        return $this->hit(
            'Student',
            (string) ($r['public_id'] ?? $r['admission_no'] ?? $r['uid']),
            trim((string) ($r['first_name'] . ' ' . $r['last_name'])),
            '/admin/students/' . (int) $r['id'],
            'students.view',
            !empty($r['deleted_at'])
        );
    }

    private function staff(array $r): array
    {
        $teaching = ((string) ($r['staff_type'] ?? '')) === 'teaching';
        return $this->hit(
            $teaching ? 'Teacher' : 'Staff',
            (string) ($r['public_id'] ?? $r['employee_code']),
            trim((string) ($r['first_name'] . ' ' . $r['last_name'])),
            ($teaching ? '/admin/teachers/' : '/admin/staff/') . (int) $r['id'],
            $teaching ? 'teachers.view' : 'staff.view',
            !empty($r['deleted_at'])
        );
    }

    /** @return array{type:string,name:string,uid:string,route:string,permission:string,archived:bool} */
    private function hit(string $type, string $uid, string $name, string $route, string $permission, bool $archived): array
    {
        return ['type' => $type, 'name' => $name, 'uid' => $uid, 'route' => $route, 'permission' => $permission, 'archived' => $archived];
    }
}
