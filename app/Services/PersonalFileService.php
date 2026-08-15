<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Student Personal File / document-checklist logic.
 *
 * The checklist of required documents is configurable (document_requirements
 * table); actual files live in student_documents (matched by type = code) and
 * carry their own verification_status. A requirement can be marked
 * "not applicable" per student (student_document_exclusions) with a reason, so
 * optional documents (e.g. EWS) are never reported as missing.
 */
final class PersonalFileService
{
    /**
     * The default checklist seeded on install. Kept here so the seeder and any
     * repair routine share one definition. Admins may edit/extend it in the DB.
     *
     * @return array<int,array{code:string,name:string,category:string,is_required:int,signature_required:int,physical_required:int}>
     */
    public static function defaultRequirements(): array
    {
        $r = static fn (string $code, string $name, string $cat, int $req = 1, int $sig = 0, int $phys = 1): array
            => ['code' => $code, 'name' => $name, 'category' => $cat, 'is_required' => $req, 'signature_required' => $sig, 'physical_required' => $phys];

        return [
            $r('check_slip',            'Check Slip (2025-26)',                              'admission'),
            $r('admission_form',        'Admission (ERP) Form / Application',                'admission'),
            $r('seat_acceptance',       'Seat Acceptance Form',                              'admission', 1, 1),
            $r('eligibility_form',      'Eligibility Form (Online Copy With Sign)',          'admission', 1, 1),
            $r('undertaking_1',         'Undertaking Annexure-I',                            'undertaking', 1, 1),
            $r('undertaking_2',         'Undertaking Annexure-II (Good Conduct & Discipline)','undertaking', 1, 1),
            $r('anti_ragging',          'Anti-Ragging Form (Online Copy)',                   'undertaking', 1, 1),
            $r('entrance_scorecard',    'MH-CET / JEE / MGMU CET Score Card (Xerox)',        'academic'),
            $r('hsc_marksheet',         'HSC Mark Memo (Xerox)',                             'academic'),
            $r('ssc_marksheet',         'SSC Mark Memo (Xerox)',                             'academic'),
            $r('hsc_leaving',           'H.S.C (T.C) Leaving Certificate (Xerox)',           'academic'),
            $r('nationality_domicile',  'Nationality Certificate / Domicile (Xerox)',        'identity'),
            $r('cbse_migration',        'CBSE Migration Certificate (Xerox)',                'academic', 0),
            $r('aadhaar',               'Aadhaar Card (Xerox)',                              'identity'),
            $r('ews_certificate',       'EWS — Economically Weaker Section Certificate (Xerox)', 'category', 0),
        ];
    }

    /**
     * Active requirements ordered for display.
     * @return array<int,array<string,mixed>>
     */
    public function requirements(): array
    {
        return QueryBuilder::table('document_requirements')
            ->where('active', 1)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /** All active requirement codes (used to validate uploads). @return string[] */
    public function requirementCodes(): array
    {
        return array_column($this->requirements(), 'code');
    }

    /**
     * Per-requirement checklist status for a student.
     *
     * status ∈ verified | pending | rejected | missing | not_applicable
     *
     * @return array<int,array<string,mixed>>
     */
    public function checklist(int $studentId): array
    {
        $reqs = $this->requirements();

        // Uploaded docs for this student, keyed by requirement code (type).
        $docs = [];
        foreach (QueryBuilder::table('student_documents')->where('student_id', $studentId)->orderBy('id', 'DESC')->get() as $d) {
            $type = (string) ($d['type'] ?? '');
            // keep the most recent per type (first seen because ordered DESC)
            if ($type !== '' && !isset($docs[$type])) {
                $docs[$type] = $d;
            }
        }

        // Not-applicable exclusions.
        $excluded = [];
        foreach (QueryBuilder::table('student_document_exclusions')->where('student_id', $studentId)->get() as $e) {
            $excluded[(string) $e['requirement_code']] = (string) ($e['reason'] ?? '');
        }

        $out = [];
        foreach ($reqs as $req) {
            $code = (string) $req['code'];
            $doc = $docs[$code] ?? null;
            if (array_key_exists($code, $excluded)) {
                $status = 'not_applicable';
            } elseif ($doc === null) {
                $status = 'missing';
            } else {
                $status = match ((string) ($doc['verification_status'] ?? 'pending')) {
                    'verified' => 'verified',
                    'rejected' => 'rejected',
                    default    => 'pending',
                };
            }
            $out[] = [
                'code'               => $code,
                'name'               => (string) $req['name'],
                'category'           => (string) $req['category'],
                'is_required'        => (int) $req['is_required'] === 1,
                'signature_required' => (int) ($req['signature_required'] ?? 0) === 1,
                'physical_required'  => (int) ($req['physical_required'] ?? 0) === 1,
                'status'             => $status,
                'document'           => $doc,
                'na_reason'          => $excluded[$code] ?? null,
            ];
        }
        return $out;
    }

    /**
     * Completion summary. "Applicable required" = required requirements that are
     * NOT marked not-applicable. A requirement counts as complete when a
     * verified document exists (uploaded-but-unverified counts as pending).
     *
     * @return array{total:int,applicable:int,verified:int,pending:int,rejected:int,missing:int,not_applicable:int,percent:int,complete:bool}
     */
    public function summary(int $studentId): array
    {
        $items = $this->checklist($studentId);
        $c = ['total' => count($items), 'applicable' => 0, 'verified' => 0, 'pending' => 0, 'rejected' => 0, 'missing' => 0, 'not_applicable' => 0];
        foreach ($items as $it) {
            $status = (string) $it['status'];
            if ($status === 'not_applicable') {
                $c['not_applicable']++;
                continue;
            }
            // Only required items count toward the applicable/percent denominator.
            if ($it['is_required']) {
                $c['applicable']++;
            }
            $c[$status] = ($c[$status] ?? 0) + 1;
        }
        // Percent is over required-and-applicable items, by verified count.
        $verifiedRequired = 0;
        foreach ($items as $it) {
            if ($it['is_required'] && $it['status'] === 'verified') {
                $verifiedRequired++;
            }
        }
        $percent = $c['applicable'] > 0 ? (int) round(($verifiedRequired / $c['applicable']) * 100) : 100;
        return $c + [
            'percent'  => $percent,
            'complete' => $c['applicable'] > 0 ? ($verifiedRequired >= $c['applicable']) : true,
        ];
    }

    public function markNotApplicable(int $studentId, string $code, string $reason, ?int $byUserId): bool
    {
        if (!in_array($code, $this->requirementCodes(), true)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $existing = QueryBuilder::table('student_document_exclusions')
            ->where('student_id', $studentId)->where('requirement_code', $code)->first();
        if ($existing) {
            QueryBuilder::table('student_document_exclusions')
                ->where('id', (int) $existing['id'])
                ->update(['reason' => $reason, 'excluded_by' => $byUserId, 'created_at' => $now]);
        } else {
            QueryBuilder::table('student_document_exclusions')->insert([
                'student_id' => $studentId, 'requirement_code' => $code,
                'reason' => $reason, 'excluded_by' => $byUserId, 'created_at' => $now,
            ]);
        }
        return true;
    }

    public function clearNotApplicable(int $studentId, string $code): void
    {
        QueryBuilder::table('student_document_exclusions')
            ->where('student_id', $studentId)->where('requirement_code', $code)->delete();
    }
}
