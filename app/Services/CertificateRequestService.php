<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Student-initiated certificate request & approval workflow.
 *
 * A student requests a certificate stating a purpose; authorised staff review
 * and approve or reject. Approval auto-issues the real certificate via
 * {@see CertificateService} using verified ERP data (students never enter their
 * own official data) and links it back to the request. Requests are never
 * deleted — the full history and status timeline are preserved.
 */
final class CertificateRequestService
{
    /** Certificate types a student is allowed to request (custom excluded). */
    public const REQUESTABLE = ['bonafide', 'study', 'character', 'transfer', 'leaving'];

    /** Predefined Bonafide/general purposes. */
    public const PURPOSES = [
        'Scholarship', 'Bank Account', 'Passport', 'Visa', 'Government Scheme',
        'Hostel', 'Education Loan', 'Internship', 'Examination', 'Travel Concession',
        'Bus/Railway Concession', 'Aadhaar/PAN Related Work', 'College/University Admission', 'Other',
    ];

    /** Human-readable status labels for the timeline. */
    public const STATUS_LABELS = [
        'submitted'          => 'Submitted',
        'under_review'       => 'Under Review',
        'changes_requested'  => 'Changes Requested',
        'approved'           => 'Approved',
        'generated'          => 'Generated — Ready for Download',
        'rejected'           => 'Rejected',
        'cancelled'          => 'Cancelled',
    ];

    /** @return array<int,string> */
    public function purposes(): array
    {
        return self::PURPOSES;
    }

    /**
     * Create a request. Prevents a duplicate OPEN request for the same
     * student+type. Returns the request id, or null on invalid input.
     */
    public function create(int $studentId, string $type, string $purpose, string $reason): ?string
    {
        if (!in_array($type, self::REQUESTABLE, true)) {
            return null;
        }
        if (QueryBuilder::table('students')->where('id', $studentId)->whereNull('deleted_at')->first() === null) {
            return null;
        }
        // Duplicate prevention: one open request per (student, type).
        $open = QueryBuilder::table('certificate_requests')
            ->where('student_id', $studentId)->where('type', $type)
            ->whereIn('status', ['submitted', 'under_review', 'changes_requested', 'approved'])
            ->first();
        if ($open !== null) {
            return null;
        }

        $reqUid = (new IdSequenceService())->generate('REQ');
        $now = date('Y-m-d H:i:s');
        QueryBuilder::table('certificate_requests')->insert([
            'req_uid'      => $reqUid,
            'student_id'   => $studentId,
            'type'         => $type,
            'purpose'      => $purpose !== '' ? $purpose : null,
            'reason'       => $reason !== '' ? $reason : null,
            'status'       => 'submitted',
            'requested_by' => auth()->id(),
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
        AuditLogger::log('certificate_request.created', 'certificate_request', $studentId, "Requested {$type} ({$reqUid})");
        return $reqUid;
    }

    public function find(string $reqUid): ?array
    {
        return QueryBuilder::table('certificate_requests')->where('req_uid', $reqUid)->first();
    }

    /** @return array<int,array<string,mixed>> */
    public function forStudent(int $studentId): array
    {
        return QueryBuilder::table('certificate_requests')
            ->where('student_id', $studentId)->orderBy('id', 'DESC')->get();
    }

    /** @return array<int,array<string,mixed>> Admin queue with student names. */
    public function list(array $filters = []): array
    {
        $q = QueryBuilder::table('certificate_requests')
            ->select([
                'certificate_requests.*',
                'students.first_name', 'students.last_name', 'students.admission_no',
            ])
            ->join('students', 'certificate_requests.student_id', '=', 'students.id')
            ->orderBy('certificate_requests.id', 'DESC');
        if (!empty($filters['status'])) {
            $q->where('certificate_requests.status', (string) $filters['status']);
        }
        return $q->limit((int) ($filters['limit'] ?? 300))->get();
    }

    /**
     * Approve a request: issues the real certificate from verified data and
     * links it. Idempotent-ish: only acts on non-terminal requests.
     */
    public function approve(string $reqUid, string $note = ''): ?string
    {
        $req = $this->find($reqUid);
        if ($req === null || in_array($req['status'], ['rejected', 'cancelled', 'generated'], true)) {
            return null;
        }
        $certUid = (new CertificateService())->issue((int) $req['student_id'], (string) $req['type'], [
            'purpose' => (string) ($req['purpose'] ?? ''),
        ]);
        if ($certUid === null) {
            return null;
        }
        $cert = QueryBuilder::table('certificates')->where('cert_uid', $certUid)->first();
        QueryBuilder::table('certificate_requests')->where('id', (int) $req['id'])->update([
            'status'         => 'generated',
            'certificate_id' => (int) ($cert['id'] ?? 0) ?: null,
            'cert_uid'       => $certUid,
            'reviewed_by'    => auth()->id(),
            'reviewed_at'    => date('Y-m-d H:i:s'),
            'review_note'    => $note !== '' ? $note : null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('certificate_request.approved', 'certificate_request', (int) $req['student_id'], "Approved {$reqUid} -> issued {$certUid}");
        return $certUid;
    }

    public function reject(string $reqUid, string $note): bool
    {
        $req = $this->find($reqUid);
        if ($req === null || in_array($req['status'], ['rejected', 'cancelled', 'generated'], true)) {
            return false;
        }
        QueryBuilder::table('certificate_requests')->where('id', (int) $req['id'])->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note !== '' ? $note : 'Rejected by reviewer',
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('certificate_request.rejected', 'certificate_request', (int) $req['student_id'], "Rejected {$reqUid}: {$note}");
        return true;
    }

    /** A student may cancel only their own request while it is still open. */
    public function cancel(string $reqUid, int $studentId): bool
    {
        $req = $this->find($reqUid);
        if ($req === null || (int) $req['student_id'] !== $studentId) {
            return false;
        }
        if (!in_array($req['status'], ['submitted', 'under_review', 'changes_requested'], true)) {
            return false;
        }
        QueryBuilder::table('certificate_requests')->where('id', (int) $req['id'])->update([
            'status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('certificate_request.cancelled', 'certificate_request', $studentId, "Cancelled {$reqUid}");
        return true;
    }
}
