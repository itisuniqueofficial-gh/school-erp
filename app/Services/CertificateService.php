<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Digital certificate issuance & verification.
 *
 * Replaces paper Bonafide / Transfer / Leaving / Character / Study / custom
 * certificates. Each issued certificate gets a unique public id
 * (CERT-YYYY-NNNNN), an auto-populated body (from real student data), an
 * immutable data snapshot, and a public QR verification page. Existing
 * certificates are never overwritten; corrections are issued as new records and
 * old ones can be revoked (history preserved).
 */
final class CertificateService
{
    public const TYPES = [
        'bonafide'  => 'Bonafide Certificate',
        'transfer'  => 'Transfer Certificate',
        'leaving'   => 'School Leaving Certificate',
        'character' => 'Character Certificate',
        'study'     => 'Study Certificate',
        'custom'    => 'Custom Certificate',
    ];

    /** @return array<string,string> */
    public function types(): array
    {
        return self::TYPES;
    }

    /** Body template per type, with {placeholder} tokens. */
    private function template(string $type): string
    {
        return match ($type) {
            'bonafide' => 'This is to certify that {name}, {guardian_line}, bearing Admission No. {admission_no} (Student ID {uid}), is a bonafide student of {school} studying in {class}{section_line} during the academic year {year}. This certificate is issued on the request of the student/guardian for official purposes.',
            'transfer' => 'This is to certify that {name}, {guardian_line}, Admission No. {admission_no}, Date of Birth {dob}, was a student of {school} in {class}{section_line} during the academic year {year}. The student has been granted a Transfer Certificate. Conduct and character during the period of study were satisfactory. All dues have been cleared.',
            'leaving'  => 'This is to certify that {name}, {guardian_line}, Admission No. {admission_no}, has left {school} after completing studies in {class}{section_line} for the academic year {year}. During the period of study, conduct and character were found to be satisfactory.',
            'character'=> 'This is to certify that {name}, {guardian_line}, Admission No. {admission_no}, has been a student of {school} in {class}{section_line}. To the best of our knowledge, the student bears a good moral character and has maintained satisfactory conduct throughout their association with the institution.',
            'study'    => 'This is to certify that {name}, {guardian_line}, Admission No. {admission_no} (Student ID {uid}), is currently pursuing studies in {class}{section_line} at {school} for the academic year {year}.',
            default    => '',
        };
    }

    /**
     * Issue a certificate for a student. Auto-populates data from the database.
     *
     * @param array{body?:string,reference_no?:string,issued_on?:string,title?:string} $opts
     * @return string|null the new certificate id, or null on invalid input
     */
    public function issue(int $studentId, string $type, array $opts = []): ?string
    {
        if (!isset(self::TYPES[$type])) {
            return null;
        }
        $student = QueryBuilder::table('students')
            ->select([
                'students.*',
                'classes.name AS class_name', 'classes.uses_sections',
                'sections.name AS section_name',
                'academic_years.name AS year_name', 'academic_years.id AS year_id',
            ])
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'students.section_id', '=', 'sections.id')
            ->leftJoin('academic_years', 'students.academic_year_id', '=', 'academic_years.id')
            ->where('students.id', $studentId)
            ->whereNull('students.deleted_at')
            ->first();
        if ($student === null) {
            return null;
        }

        $data = $this->populate($student);
        $body = trim((string) ($opts['body'] ?? ''));
        if ($type === 'custom') {
            if ($body === '') {
                return null; // custom certificates require a body
            }
        } else {
            $body = $this->fill($this->template($type), $data);
        }
        // Custom bodies may still use placeholders.
        $body = $this->fill($body, $data);

        // Reflect the approved purpose in the wording when provided.
        $purpose = trim((string) ($opts['purpose'] ?? ''));
        if ($purpose !== '') {
            $body = rtrim($body) . ' This certificate is issued for the purpose of ' . $purpose . '.';
        }

        $certUid = (new IdSequenceService())->generate('CERT');
        $now = date('Y-m-d H:i:s');
        QueryBuilder::table('certificates')->insert([
            'cert_uid'         => $certUid,
            'type'             => $type,
            'title'            => trim((string) ($opts['title'] ?? '')) ?: self::TYPES[$type],
            'student_id'       => $studentId,
            'academic_year_id' => (int) ($student['year_id'] ?? 0) ?: null,
            'reference_no'     => trim((string) ($opts['reference_no'] ?? '')) ?: null,
            'body'             => $body,
            'data_json'        => json_encode($data, JSON_UNESCAPED_UNICODE),
            'branding_snapshot'=> json_encode((new ReportCardDocumentService())->brandingSnapshot(), JSON_UNESCAPED_UNICODE),
            'status'           => 'issued',
            'issued_by'        => auth()->id(),
            'issued_on'        => trim((string) ($opts['issued_on'] ?? '')) ?: date('Y-m-d'),
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        AuditLogger::log('certificate.issued', 'certificate', $studentId, "Issued {$certUid} ({$type}) for student {$studentId}");
        return $certUid;
    }

    /** @return array<string,string> Placeholder data snapshot from real records. */
    private function populate(array $student): array
    {
        $guardians = QueryBuilder::table('student_guardians')
            ->select(['guardians.name', 'guardians.relation'])
            ->join('guardians', 'student_guardians.guardian_id', '=', 'guardians.id')
            ->where('student_guardians.student_id', (int) $student['id'])->get();
        $father = ''; $mother = ''; $guardian = '';
        foreach ($guardians as $g) {
            match ((string) $g['relation']) {
                'father' => $father = (string) $g['name'],
                'mother' => $mother = (string) $g['name'],
                default  => $guardian = $guardian ?: (string) $g['name'],
            };
        }
        $name = trim((string) ($student['first_name'] . ' ' . $student['last_name']));
        $section = ((int) ($student['uses_sections'] ?? 0) === 1) ? (string) ($student['section_name'] ?? '') : '';
        $guardianLine = $father !== '' ? "son/daughter of {$father}" : ($guardian !== '' ? "ward of {$guardian}" : 'a student of this institution');

        return [
            'name'          => $name,
            'uid'           => (string) $student['uid'],
            'admission_no'  => (string) $student['admission_no'],
            'roll_no'       => (string) ($student['roll_no'] ?? ''),
            'class'         => (string) ($student['class_name'] ?? ''),
            'section'       => $section,
            'section_line'  => $section !== '' ? ' - ' . $section : '',
            'year'          => (string) ($student['year_name'] ?? ''),
            'dob'           => (string) ($student['dob'] ?? ''),
            'gender'        => ucfirst((string) ($student['gender'] ?? '')),
            'father'        => $father,
            'mother'        => $mother,
            'guardian'      => $guardian,
            'guardian_line' => $guardianLine,
            'school'        => (new ReportCardDocumentService())->branding()['name'],
        ];
    }

    /** @param array<string,string> $data */
    private function fill(string $template, array $data): string
    {
        return preg_replace_callback('/\{(\w+)\}/', static fn ($m) => $data[$m[1]] ?? $m[0], $template) ?? $template;
    }

    /** @return array<int,array<string,mixed>> */
    public function list(array $filters = []): array
    {
        $q = QueryBuilder::table('certificates')
            ->select([
                'certificates.*',
                'students.first_name', 'students.last_name', 'students.admission_no',
            ])
            ->join('students', 'certificates.student_id', '=', 'students.id')
            ->orderBy('certificates.id', 'DESC');
        if (!empty($filters['type'])) {
            $q->where('certificates.type', (string) $filters['type']);
        }
        if (!empty($filters['student_id'])) {
            $q->where('certificates.student_id', (int) $filters['student_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('certificates.status', (string) $filters['status']);
        }
        return $q->limit((int) ($filters['limit'] ?? 200))->get();
    }

    public function find(string $certUid): ?array
    {
        return QueryBuilder::table('certificates')->where('cert_uid', $certUid)->first();
    }

    public function revoke(string $certUid, string $reason): bool
    {
        $cert = $this->find($certUid);
        if ($cert === null || $cert['status'] === 'revoked') {
            return false;
        }
        QueryBuilder::table('certificates')->where('id', (int) $cert['id'])->update([
            'status' => 'revoked', 'revoked_reason' => $reason !== '' ? $reason : 'Revoked by administrator', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('certificate.revoked', 'certificate', (int) $cert['student_id'], "Revoked {$certUid}: {$reason}");
        return true;
    }

    /**
     * Full document for HTML/PDF rendering (branding + certificate + student).
     * @return array<string,mixed>|null
     */
    public function document(string $certUid): ?array
    {
        $cert = QueryBuilder::table('certificates')
            ->select([
                'certificates.*',
                'students.first_name', 'students.last_name', 'students.admission_no', 'students.uid AS student_uid',
                'classes.name AS class_name', 'academic_years.name AS year_name',
            ])
            ->join('students', 'certificates.student_id', '=', 'students.id')
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->leftJoin('academic_years', 'certificates.academic_year_id', '=', 'academic_years.id')
            ->where('certificates.cert_uid', $certUid)->first();
        if ($cert === null) {
            return null;
        }
        // Prefer the branding captured at issue time (historical integrity); fall
        // back to current branding for certificates issued before snapshots.
        $rcd = new ReportCardDocumentService();
        $snapshot = null;
        if (!empty($cert['branding_snapshot'])) {
            $decoded = json_decode((string) $cert['branding_snapshot'], true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }
        $branding = $snapshot !== null ? $rcd->brandingFromSnapshot($snapshot) : $rcd->branding();
        return [
            'branding'   => $branding,
            'certificate'=> $cert,
            'cert_uid'   => (string) $cert['cert_uid'],
            'title'      => (string) $cert['title'],
            'type'       => (string) $cert['type'],
            'body'       => (string) $cert['body'],
            'status'     => (string) $cert['status'],
            'issued_on'  => (string) $cert['issued_on'],
            'reference_no'=> (string) ($cert['reference_no'] ?? ''),
            'student'    => [
                'name'         => trim((string) ($cert['first_name'] . ' ' . $cert['last_name'])),
                'admission_no' => (string) $cert['admission_no'],
                'uid'          => (string) $cert['student_uid'],
                'class'        => (string) ($cert['class_name'] ?? ''),
                'year'         => (string) ($cert['year_name'] ?? ''),
            ],
            'verify_url' => url('/verify/certificate/' . $cert['cert_uid']),
        ];
    }

    /**
     * Public verification — non-sensitive fields only.
     * @return array<string,mixed>|null
     */
    public function verify(string $certUid): ?array
    {
        $doc = $this->document($certUid);
        if ($doc === null) {
            return null;
        }
        return [
            'cert_uid'      => $doc['cert_uid'],
            'title'         => $doc['title'],
            'type'          => $doc['type'],
            'student_name'  => $doc['student']['name'],
            'student_uid'   => $doc['student']['uid'],
            'class_name'    => $doc['student']['class'],
            'academic_year' => $doc['student']['year'],
            'issued_on'     => $doc['issued_on'],
            'status'        => $doc['status'],
            'valid'         => $doc['status'] === 'issued',
        ];
    }
}
