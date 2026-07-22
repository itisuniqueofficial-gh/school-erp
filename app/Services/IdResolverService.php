<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Universal "Go to Record by ID" resolver.
 *
 * Maps any unique ERP identifier (student/staff public IDs, admission number,
 * 12-digit user ID, employee code, certificate / report-card / request IDs) to
 * the canonical record and its admin route. Only ever matches on GLOBALLY
 * UNIQUE columns — never on ambiguous fields like roll number — so an ID always
 * resolves to at most one record. Returns null when nothing matches.
 */
final class IdResolverService
{
    /**
     * @return array{type:string,label:string,name:string,uid:string,route:string}|null
     */
    public function resolve(string $raw): ?array
    {
        $id = trim($raw);
        if ($id === '') {
            return null;
        }
        $U = strtoupper($id);

        // ---- Prefixed identifiers -----------------------------------------
        if (str_starts_with($U, 'RC-')) {
            $r = QueryBuilder::table('report_cards')->where('rc_uid', $U)->first();
            return $r ? $this->hit('Report Card', $U, $U, '/admin/exams/' . (int) $r['exam_id'] . '/report-cards/' . (int) $r['student_id']) : null;
        }
        if (str_starts_with($U, 'CERT-')) {
            $r = QueryBuilder::table('certificates')->where('cert_uid', $U)->first();
            return $r ? $this->hit('Certificate', $U, (string) $r['title'], '/admin/certificates/' . $U) : null;
        }
        if (str_starts_with($U, 'REQ-')) {
            $r = QueryBuilder::table('certificate_requests')->where('req_uid', $U)->first();
            return $r ? $this->hit('Certificate Request', $U, ucfirst((string) $r['type']) . ' request', '/admin/certificate-requests') : null;
        }
        if (str_starts_with($U, 'STU-')) {
            $r = QueryBuilder::table('students')->whereNull('deleted_at')->where('public_id', $U)->first();
            return $r ? $this->student($r) : null;
        }
        if (str_starts_with($U, 'TCH-') || str_starts_with($U, 'STF-')) {
            $r = QueryBuilder::table('staff')->whereNull('deleted_at')->where('public_id', $U)->first();
            return $r ? $this->staff($r) : null;
        }

        // ---- Unprefixed: try each entity's unique columns -----------------
        // Student: admission number, 12-digit UID, or public id.
        $r = QueryBuilder::table('students')->whereNull('deleted_at')
            ->whereRaw('(`admission_no` = ? OR `uid` = ? OR `public_id` = ?)', [$id, $id, $U])->first();
        if ($r) {
            return $this->student($r);
        }
        // Staff: employee code, UID, or public id.
        $r = QueryBuilder::table('staff')->whereNull('deleted_at')
            ->whereRaw('(`employee_code` = ? OR `uid` = ? OR `public_id` = ?)', [$id, $id, $U])->first();
        if ($r) {
            return $this->staff($r);
        }
        // Certificate / report card / request without the caller typing the prefix casing.
        if (($r = QueryBuilder::table('certificates')->where('cert_uid', $U)->first()) !== null) {
            return $this->hit('Certificate', $U, (string) $r['title'], '/admin/certificates/' . $U);
        }
        if (($r = QueryBuilder::table('report_cards')->where('rc_uid', $U)->first()) !== null) {
            return $this->hit('Report Card', $U, $U, '/admin/exams/' . (int) $r['exam_id'] . '/report-cards/' . (int) $r['student_id']);
        }

        return null;
    }

    private function student(array $r): array
    {
        return $this->hit(
            'Student',
            (string) ($r['public_id'] ?? $r['admission_no'] ?? $r['uid']),
            trim((string) ($r['first_name'] . ' ' . $r['last_name'])),
            '/admin/students/' . (int) $r['id']
        );
    }

    private function staff(array $r): array
    {
        $route = ((string) ($r['staff_type'] ?? '')) === 'teaching'
            ? '/admin/teachers/' . (int) $r['id']
            : '/admin/staff/' . (int) $r['id'];
        return $this->hit(
            ((string) ($r['staff_type'] ?? '')) === 'teaching' ? 'Teacher' : 'Staff',
            (string) ($r['public_id'] ?? $r['employee_code']),
            trim((string) ($r['first_name'] . ' ' . $r['last_name'])),
            $route
        );
    }

    /** @return array{type:string,label:string,name:string,uid:string,route:string} */
    private function hit(string $type, string $uid, string $name, string $route): array
    {
        return ['type' => $type, 'label' => $type, 'name' => $name, 'uid' => $uid, 'route' => $route];
    }
}
