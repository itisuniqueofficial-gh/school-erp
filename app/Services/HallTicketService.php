<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Hall ticket / admit card issuance & verification.
 *
 * Tickets are scoped to a real exam + class (+ optional section) and only
 * issued to eligible students. Regular/board/practical tickets go to the class
 * roster; back-paper/supplementary/retest tickets go only to students who failed
 * that exam (from their report card). Issuance is duplicate-safe
 * (UNIQUE exam+student+type), auditable, and backed by a public QR page.
 */
final class HallTicketService
{
    public const TYPES = [
        'hall_ticket'   => 'Hall Ticket',
        'admit_card'    => 'Admit Card',
        'exam_pass'     => 'Exam Pass',
        'back_paper'    => 'Back Paper Hall Ticket',
        'repeater'      => 'Repeater Hall Ticket',
        'supplementary' => 'Supplementary Hall Ticket',
        'retest'        => 'Re-Test Hall Ticket',
        'practical'     => 'Practical Exam Hall Ticket',
        'board'         => 'Board Exam Hall Ticket',
        'custom'        => 'Custom Entry Permit',
    ];

    /** Types whose eligibility is the set of students who FAILED the exam. */
    private const FAILED_TYPES = ['back_paper', 'supplementary', 'retest', 'repeater'];

    /** @return array<string,string> */
    public function types(): array
    {
        return self::TYPES;
    }

    /**
     * Eligible students for a given exam + class (+ optional section) and type,
     * each annotated with whether a ticket of this type is already issued.
     *
     * @return array<int,array<string,mixed>>
     */
    public function eligibleStudents(int $examId, int $classId, ?int $sectionId, string $type): array
    {
        // Exam must actually cover this class (have schedules for it).
        $covers = QueryBuilder::table('exam_schedules')->where('exam_id', $examId)->where('class_id', $classId)->count();
        if ($covers === 0) {
            return [];
        }

        $q = QueryBuilder::table('students')
            ->select(['students.id', 'students.first_name', 'students.last_name', 'students.admission_no', 'students.roll_no', 'students.uid'])
            ->where('students.class_id', $classId)
            ->whereNull('students.deleted_at')
            ->where('students.status', 'active');
        if ($sectionId !== null && $sectionId > 0) {
            $q->where('students.section_id', $sectionId);
        }
        $students = $q->orderBy('students.roll_no')->get();

        // For back-paper/supplementary/retest/repeater: only students who FAILED.
        $failedIds = null;
        if (in_array($type, self::FAILED_TYPES, true)) {
            $failedIds = array_map(
                static fn ($r) => (int) $r['student_id'],
                QueryBuilder::table('report_cards')->select(['student_id'])->where('exam_id', $examId)->where('result', 'fail')->get()
            );
        }

        // Already-issued tickets of this type for this exam.
        $issued = [];
        foreach (QueryBuilder::table('hall_tickets')->select(['student_id', 'status'])->where('exam_id', $examId)->where('type', $type)->get() as $h) {
            $issued[(int) $h['student_id']] = (string) $h['status'];
        }

        $out = [];
        foreach ($students as $s) {
            $sid = (int) $s['id'];
            if ($failedIds !== null && !in_array($sid, $failedIds, true)) {
                continue; // not eligible for a failed-only ticket type
            }
            $out[] = [
                'id'           => $sid,
                'name'         => trim((string) ($s['first_name'] . ' ' . $s['last_name'])),
                'admission_no' => (string) $s['admission_no'],
                'roll_no'      => (string) ($s['roll_no'] ?? ''),
                'uid'          => (string) $s['uid'],
                'status'       => $issued[$sid] ?? 'eligible',
                'issued'       => isset($issued[$sid]),
            ];
        }
        return $out;
    }

    /** Exam schedule rows (subject + date + time) for the ticket. */
    public function subjectsForExam(int $examId, int $classId): array
    {
        return QueryBuilder::table('exam_schedules')
            ->select([
                'exam_schedules.exam_date', 'exam_schedules.start_time', 'exam_schedules.end_time',
                'exam_schedules.full_marks', 'subjects.name AS subject', 'subjects.code AS code',
            ])
            ->leftJoin('subjects', 'exam_schedules.subject_id', '=', 'subjects.id')
            ->where('exam_schedules.exam_id', $examId)
            ->where('exam_schedules.class_id', $classId)
            ->orderBy('exam_schedules.exam_date')->get();
    }

    /**
     * Issue a hall ticket (idempotent per exam+student+type). Returns the ht_uid,
     * or null if the student is not eligible for this type.
     *
     * @param array{center?:string,instructions?:string,signature_required?:bool,stamp_required?:bool} $opts
     */
    public function issue(int $examId, int $studentId, string $type, array $opts = []): ?string
    {
        if (!isset(self::TYPES[$type])) {
            return null;
        }
        $student = QueryBuilder::table('students')->where('id', $studentId)->whereNull('deleted_at')->first();
        if ($student === null) {
            return null;
        }
        // Failed-only types require a failing report card for the exam.
        if (in_array($type, self::FAILED_TYPES, true)) {
            $failed = QueryBuilder::table('report_cards')->where('exam_id', $examId)->where('student_id', $studentId)->where('result', 'fail')->count();
            if ($failed === 0) {
                return null;
            }
        }
        // Idempotent: return the existing ticket if already issued.
        $existing = QueryBuilder::table('hall_tickets')->where('exam_id', $examId)->where('student_id', $studentId)->where('type', $type)->first();
        if ($existing !== null) {
            return (string) $existing['ht_uid'];
        }

        $htUid = (new IdSequenceService())->generate('HT');
        $now = date('Y-m-d H:i:s');
        QueryBuilder::table('hall_tickets')->insert([
            'ht_uid'     => $htUid,
            'exam_id'    => $examId,
            'student_id' => $studentId,
            'class_id'   => (int) ($student['class_id'] ?? 0) ?: null,
            'section_id' => (int) ($student['section_id'] ?? 0) ?: null,
            'type'       => $type,
            'attempt_type' => in_array($type, self::FAILED_TYPES, true) ? $type : 'regular',
            'status'     => 'issued',
            'roll_no'    => (string) ($student['roll_no'] ?? ''),
            'exam_center' => trim((string) ($opts['center'] ?? '')) ?: null,
            'instructions' => trim((string) ($opts['instructions'] ?? '')) ?: null,
            'signature_required' => !empty($opts['signature_required']) ? 1 : 0,
            'stamp_required' => !empty($opts['stamp_required']) ? 1 : 0,
            'issued_by'  => auth()->id(),
            'issued_on'  => date('Y-m-d'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        AuditLogger::log('hallticket.issued', 'hall_ticket', $studentId, "Issued {$htUid} ({$type}) exam {$examId}");
        return $htUid;
    }

    /**
     * Bulk-issue for a class/section. Returns counts.
     * @param array{center?:string,instructions?:string,signature_required?:bool,stamp_required?:bool} $opts
     * @return array{created:int,skipped:int,total:int}
     */
    public function issueForClass(int $examId, int $classId, ?int $sectionId, string $type, array $opts = [], ?array $onlyStudentIds = null): array
    {
        $created = 0; $skipped = 0; $total = 0;
        foreach ($this->eligibleStudents($examId, $classId, $sectionId, $type) as $s) {
            if ($onlyStudentIds !== null && !in_array((int) $s['id'], $onlyStudentIds, true)) {
                continue;
            }
            $total++;
            if (!empty($s['issued'])) {
                $skipped++;
                continue;
            }
            $this->issue($examId, (int) $s['id'], $type, $opts) !== null ? $created++ : $skipped++;
        }
        AuditLogger::log('hallticket.bulk', 'hall_ticket', null, "Bulk {$type} exam {$examId} class {$classId}: +{$created} skip{$skipped}");
        return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
    }

    public function find(string $htUid): ?array
    {
        return QueryBuilder::table('hall_tickets')->where('ht_uid', $htUid)->first();
    }

    /** @return array<int,array<string,mixed>> */
    public function forStudent(int $studentId): array
    {
        return QueryBuilder::table('hall_tickets')
            ->select(['hall_tickets.*', 'exams.name AS exam_name', 'academic_years.name AS year_name'])
            ->leftJoin('exams', 'hall_tickets.exam_id', '=', 'exams.id')
            ->leftJoin('academic_years', 'exams.academic_year_id', '=', 'academic_years.id')
            ->where('hall_tickets.student_id', $studentId)
            ->orderBy('hall_tickets.id', 'DESC')->get();
    }

    /** @return array<int,array<string,mixed>> */
    public function list(array $filters = []): array
    {
        $q = QueryBuilder::table('hall_tickets')
            ->select([
                'hall_tickets.*', 'students.first_name', 'students.last_name', 'students.admission_no',
                'exams.name AS exam_name', 'classes.name AS class_name',
            ])
            ->join('students', 'hall_tickets.student_id', '=', 'students.id')
            ->leftJoin('exams', 'hall_tickets.exam_id', '=', 'exams.id')
            ->leftJoin('classes', 'hall_tickets.class_id', '=', 'classes.id')
            ->orderBy('hall_tickets.id', 'DESC');
        foreach (['exam_id' => 'hall_tickets.exam_id', 'class_id' => 'hall_tickets.class_id', 'type' => 'hall_tickets.type', 'status' => 'hall_tickets.status'] as $k => $col) {
            if (!empty($filters[$k])) {
                $q->where($col, $filters[$k]);
            }
        }
        return $q->limit((int) ($filters['limit'] ?? 300))->get();
    }

    public function revoke(string $htUid, string $reason): bool
    {
        $ht = $this->find($htUid);
        if ($ht === null || $ht['status'] === 'revoked') {
            return false;
        }
        QueryBuilder::table('hall_tickets')->where('id', (int) $ht['id'])->update([
            'status' => 'revoked', 'revoked_reason' => $reason !== '' ? $reason : 'Revoked by administrator', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('hallticket.revoked', 'hall_ticket', (int) $ht['student_id'], "Revoked {$htUid}: {$reason}");
        return true;
    }

    /** Full document for HTML/PDF rendering. @return array<string,mixed>|null */
    public function document(string $htUid): ?array
    {
        $ht = QueryBuilder::table('hall_tickets')
            ->select([
                'hall_tickets.*',
                'students.first_name', 'students.last_name', 'students.admission_no', 'students.uid AS student_uid',
                'students.photo', 'students.section_id AS s_section',
                'classes.name AS class_name', 'sections.name AS section_name', 'classes.uses_sections',
                'exams.name AS exam_name', 'exams.academic_year_id',
                'academic_years.name AS year_name', 'exam_types.name AS exam_type',
            ])
            ->join('students', 'hall_tickets.student_id', '=', 'students.id')
            ->leftJoin('classes', 'hall_tickets.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'hall_tickets.section_id', '=', 'sections.id')
            ->leftJoin('exams', 'hall_tickets.exam_id', '=', 'exams.id')
            ->leftJoin('academic_years', 'exams.academic_year_id', '=', 'academic_years.id')
            ->leftJoin('exam_types', 'exams.exam_type_id', '=', 'exam_types.id')
            ->where('hall_tickets.ht_uid', $htUid)->first();
        if ($ht === null) {
            return null;
        }
        return [
            'branding'   => (new ReportCardDocumentService())->branding(),
            'ht'         => $ht,
            'ht_uid'     => (string) $ht['ht_uid'],
            'type'       => (string) $ht['type'],
            'type_label' => self::TYPES[$ht['type']] ?? 'Hall Ticket',
            'status'     => (string) $ht['status'],
            'issued_on'  => (string) $ht['issued_on'],
            'exam'       => ['name' => (string) ($ht['exam_name'] ?? ''), 'type' => (string) ($ht['exam_type'] ?? '')],
            'subjects'   => $this->subjectsForExam((int) $ht['exam_id'], (int) $ht['class_id']),
            'student'    => [
                'name'         => trim((string) ($ht['first_name'] . ' ' . $ht['last_name'])),
                'admission_no' => (string) $ht['admission_no'],
                'uid'          => (string) $ht['student_uid'],
                'roll_no'      => (string) ($ht['roll_no'] ?? ''),
                'class'        => (string) ($ht['class_name'] ?? ''),
                'section'      => ((int) ($ht['uses_sections'] ?? 0) === 1) ? (string) ($ht['section_name'] ?? '') : '',
                'year'         => (string) ($ht['year_name'] ?? ''),
                'photo'        => (string) ($ht['photo'] ?? ''),
            ],
            'center'       => (string) ($ht['exam_center'] ?? ''),
            'instructions' => (string) ($ht['instructions'] ?? ''),
            'signature_required' => (int) ($ht['signature_required'] ?? 0) === 1,
            'stamp_required'     => (int) ($ht['stamp_required'] ?? 0) === 1,
            'verify_url'   => url('/verify/hall-ticket/' . $ht['ht_uid']),
        ];
    }

    /** Public verification — non-sensitive fields only. @return array<string,mixed>|null */
    public function verify(string $htUid): ?array
    {
        $doc = $this->document($htUid);
        if ($doc === null) {
            return null;
        }
        return [
            'ht_uid'        => $doc['ht_uid'],
            'type'          => $doc['type_label'],
            'student_name'  => $doc['student']['name'],
            'student_uid'   => $doc['student']['uid'],
            'class_name'    => $doc['student']['class'],
            'section'       => $doc['student']['section'],
            'exam_name'     => $doc['exam']['name'],
            'academic_year' => $doc['student']['year'],
            'issued_on'     => $doc['issued_on'],
            'status'        => $doc['status'],
            'valid'         => $doc['status'] === 'issued',
        ];
    }
}
