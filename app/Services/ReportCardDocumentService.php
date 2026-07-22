<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Assembles a complete report-card document entirely from real database data
 * (branding, authorized student info, dynamic subject-wise marks, totals,
 * grade/GPA/division/result, year-scoped attendance, guardians, remarks and the
 * verification identity). All calculation is server-side.
 *
 * This is the single source of truth consumed by both the HTML report card and
 * the PDF renderer, so they can never disagree.
 */
final class ReportCardDocumentService
{
    private ReportCardService $cards;

    public function __construct(?ReportCardService $cards = null)
    {
        $this->cards = $cards ?? new ReportCardService();
    }

    /**
     * Build the full document for one student's exam. Ensures a report card
     * (and thus an RC id + version) exists first.
     *
     * @return array<string,mixed>|null  null when the student has no marks/card
     */
    public function build(int $examId, int $studentId): ?array
    {
        // Guarantee an identity/version exists (idempotent).
        if ($this->cards->latest($examId, $studentId) === null) {
            // Only generate when the student actually has marks for this exam.
            $hasMarks = QueryBuilder::table('exam_marks')
                ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
                ->where('exam_schedules.exam_id', $examId)
                ->where('exam_marks.student_id', $studentId)
                ->count();
            if ($hasMarks === 0) {
                return null;
            }
            $this->cards->generate($examId, $studentId);
        }
        $card = $this->cards->latest($examId, $studentId);
        if ($card === null) {
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
            ->first();
        if ($student === null) {
            return null;
        }

        $exam = QueryBuilder::table('exams')
            ->select(['exams.*', 'academic_years.name AS exam_year', 'exam_types.name AS exam_type'])
            ->leftJoin('academic_years', 'exams.academic_year_id', '=', 'academic_years.id')
            ->leftJoin('exam_types', 'exams.exam_type_id', '=', 'exam_types.id')
            ->where('exams.id', $examId)->first();

        // ----- Subject-wise marks (dynamic; only real rows) ------------------
        $schedules = QueryBuilder::table('exam_schedules')
            ->select([
                'exam_schedules.id AS schedule_id', 'exam_schedules.full_marks', 'exam_schedules.pass_marks',
                'subjects.name AS subject', 'subjects.code AS subject_code',
            ])
            ->leftJoin('subjects', 'exam_schedules.subject_id', '=', 'subjects.id')
            ->where('exam_schedules.exam_id', $examId)
            ->where('exam_schedules.class_id', (int) $student['class_id'])
            ->orderBy('subjects.name')
            ->get();

        $schedIds = array_map(static fn ($s) => (int) $s['schedule_id'], $schedules);
        $marksBySchedule = [];
        if ($schedIds !== []) {
            foreach (QueryBuilder::table('exam_marks')->where('student_id', $studentId)->whereIn('exam_schedule_id', $schedIds)->get() as $m) {
                $marksBySchedule[(int) $m['exam_schedule_id']] = $m;
            }
        }

        $subjects = [];
        $grandObtained = 0.0; $grandMax = 0.0; $anyFail = false; $gpaSum = 0.0; $gpaCount = 0;
        foreach ($schedules as $r) {
            $mk = $marksBySchedule[(int) $r['schedule_id']] ?? null;
            $max = (float) $r['full_marks'];
            $absent = $mk !== null && (int) ($mk['is_absent'] ?? 0) === 1;
            $obtained = $absent ? 0.0 : (float) ($mk['marks_obtained'] ?? 0);
            $pass = (float) $r['pass_marks'];
            $pct = $max > 0 ? round($obtained / $max * 100, 2) : 0.0;
            $g = $this->gradeFor($pct);
            $subjectPass = !$absent && $obtained >= $pass;
            if (!$subjectPass) {
                $anyFail = true;
            }
            $grandObtained += $obtained;
            $grandMax += $max;
            if ($g !== null) { $gpaSum += (float) $g['gpa']; $gpaCount++; }
            $subjects[] = [
                'subject'   => (string) ($r['subject'] ?? '—'),
                'code'      => (string) ($r['subject_code'] ?? ''),
                'max'       => $max,
                'obtained'  => $absent ? 'AB' : $obtained,
                'pass_marks'=> $pass,
                'percentage'=> $pct,
                'grade'     => $g['name'] ?? '—',
                'grade_point' => $g !== null ? (float) $g['gpa'] : 0.0,
                'result'    => $subjectPass ? 'Pass' : ($absent ? 'Absent' : 'Fail'),
                'remark'    => (string) ($mk['remarks'] ?? ''),
            ];
        }

        $overallPct = $grandMax > 0 ? round($grandObtained / $grandMax * 100, 2) : 0.0;
        $overallGrade = $this->gradeFor($overallPct);
        $gpa = $gpaCount > 0 ? round($gpaSum / $gpaCount, 2) : 0.0;
        $passed = !$anyFail && $overallPct >= 33;

        // ----- Attendance (year-scoped) --------------------------------------
        $attendance = (new AttendanceService())->getStudentAttendanceSummary($studentId, (int) ($student['year_id'] ?? 0));

        // ----- Guardians -----------------------------------------------------
        $guardians = QueryBuilder::table('student_guardians')
            ->select(['guardians.name', 'guardians.relation'])
            ->join('guardians', 'student_guardians.guardian_id', '=', 'guardians.id')
            ->where('student_guardians.student_id', $studentId)->get();
        $father = ''; $mother = ''; $guardian = '';
        foreach ($guardians as $g) {
            match ((string) $g['relation']) {
                'father' => $father = (string) $g['name'],
                'mother' => $mother = (string) $g['name'],
                default  => $guardian = $guardian ?: (string) $g['name'],
            };
        }

        // ----- Promotion status (separate from exam result) ------------------
        $promotion = 'Not yet decided';
        try {
            $enr = QueryBuilder::table('student_enrollments')
                ->where('student_id', $studentId)->where('academic_year_id', (int) ($student['year_id'] ?? 0))
                ->orderBy('id', 'DESC')->first();
            if ($enr !== null && isset($enr['status'])) {
                $promotion = ucfirst((string) $enr['status']);
            }
        } catch (\Throwable) {
        }

        return [
            'branding'   => $this->branding(),
            'card'       => $card,
            'rc_uid'     => (string) $card['rc_uid'],
            'version'    => (int) $card['version'],
            'verify_url' => url('/verify/report-card/' . $card['rc_uid']),
            'exam'       => $exam,
            'student'    => [
                'id'            => (int) $student['id'],
                'name'          => trim((string) ($student['first_name'] . ' ' . $student['last_name'])),
                'uid'           => (string) $student['uid'],
                'admission_no'  => (string) $student['admission_no'],
                'roll_no'       => (string) ($student['roll_no'] ?? ''),
                'class'         => (string) ($student['class_name'] ?? ''),
                'section'       => ((int) ($student['uses_sections'] ?? 0) === 1) ? (string) ($student['section_name'] ?? '') : '',
                'year'          => (string) ($student['year_name'] ?? ''),
                'dob'           => (string) ($student['dob'] ?? ''),
                'gender'        => ucfirst((string) ($student['gender'] ?? '')),
                'blood_group'   => (string) ($student['blood_group'] ?? ''),
                'photo'         => (string) ($student['photo'] ?? ''),
                'apaar_masked'  => $this->maskId((string) ($student['apaar_id'] ?? '')),
                'father'        => $father,
                'mother'        => $mother,
                'guardian'      => $guardian,
            ],
            'subjects'   => $subjects,
            'totals'     => [
                'obtained'   => $grandObtained,
                'max'        => $grandMax,
                'percentage' => $overallPct,
                'grade'      => $overallGrade['name'] ?? '—',
                'gpa'        => $gpa,
                'division'   => $this->division($overallPct, $passed),
                'result'     => $passed ? 'PASSED' : 'FAILED',
                'promotion'  => $promotion,
            ],
            'attendance' => $attendance,
        ];
    }

    /** School branding from the settings table (general group). */
    public function branding(): array
    {
        $rows = QueryBuilder::table('settings')->where('group', 'general')->get();
        $s = [];
        foreach ($rows as $r) {
            $s[(string) $r['key']] = (string) $r['value'];
        }
        // Board/UDISE/affiliation are optional — pull from any group if present.
        $extra = QueryBuilder::table('settings')
            ->whereIn('key', ['board_name', 'udise_code', 'affiliation_no', 'school_website'])->get();
        foreach ($extra as $r) {
            $s[(string) $r['key']] = (string) $r['value'];
        }
        return [
            'name'        => $s['school_name'] ?? 'School',
            'address'     => $s['school_address'] ?? '',
            'phone'       => $s['school_phone'] ?? '',
            'email'       => $s['school_email'] ?? '',
            'website'     => $s['school_website'] ?? '',
            'board'       => $s['board_name'] ?? '',
            'udise'       => $s['udise_code'] ?? '',
            'affiliation' => $s['affiliation_no'] ?? '',
        ];
    }

    /** @return array<string,mixed>|null */
    private function gradeFor(float $pct): ?array
    {
        return QueryBuilder::table('grades')
            ->whereRaw('? BETWEEN `min_percent` AND `max_percent`', [$pct])
            ->orderBy('min_percent', 'DESC')->first();
    }

    private function division(float $pct, bool $passed): string
    {
        if (!$passed) {
            return 'Fail';
        }
        return match (true) {
            $pct >= 60 => 'First Division',
            $pct >= 45 => 'Second Division',
            default    => 'Third Division',
        };
    }

    private function maskId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }
        $len = strlen($id);
        return $len <= 4 ? str_repeat('X', $len) : str_repeat('X', $len - 4) . substr($id, -4);
    }
}
