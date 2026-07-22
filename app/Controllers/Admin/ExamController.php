<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ClassModel;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\MarksWorkflowService;
use App\Services\IdSequenceService;
use App\Services\AuditLogger;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Examinations: exams, schedules, marks entry, grading and report cards.
 */
final class ExamController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.exams.index', [
            'title'     => 'Examinations',
            'exams'     => Exam::withType(),
            'examTypes' => QueryBuilder::table('exam_types')->orderBy('name')->get(),
            'years'     => QueryBuilder::table('academic_years')->orderBy('start_date', 'DESC')->get(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'exam_type_id' => 'required|integer',
            'name'         => 'required|string|max:150',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
        ]);

        $id = Exam::create([
            'exam_type_id'     => (int) $data['exam_type_id'],
            'academic_year_id' => (int) ($request->input('academic_year_id') ?: 0) ?: null,
            'uid'              => (new IdSequenceService())->uidExam(),
            'name'             => $data['name'],
            'start_date'       => $data['start_date'] ?: null,
            'end_date'         => $data['end_date'] ?: null,
            'status'           => 'scheduled',
        ]);

        AuditLogger::log('exam.created', 'exam', $id, 'Created exam ' . $data['name']);
        return $this->redirect('/admin/exams', ['success' => 'Exam created.']);
    }

    /**
     * Marks entry screen for a given exam + class + subject.
     */
    public function marks(Request $request, string $id): Response
    {
        $examId = (int) $id;
        $exam = Exam::find($examId);
        if ($exam === null) {
            abort(404, 'Exam not found.');
        }

        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0);
        $subjectId = (int) $request->query('subject_id', 0);
        $students = [];
        $schedule = null;
        $existing = [];

        if ($classId && $subjectId) {
            $schedule = $this->ensureSchedule($examId, $classId, $subjectId);
            $studentQuery = QueryBuilder::table('students')
                ->select(['id', 'admission_no', 'roll_no', 'first_name', 'last_name'])
                ->where('class_id', $classId)->whereNull('deleted_at')->where('status', 'active');
            if ($sectionId) {
                $studentQuery->where('section_id', $sectionId);
            }
            $students = $studentQuery->orderBy('roll_no')->get();

            foreach (QueryBuilder::table('exam_marks')->where('exam_schedule_id', (int) $schedule['id'])->get() as $m) {
                $existing[(int) $m['student_id']] = $m;
            }
        }

        return $this->view('admin.exams.marks', [
            'title'     => 'Enter Marks · ' . $exam['name'],
            'exam'      => $exam,
            'classes'   => ClassModel::ordered(),
            'sections'  => \App\Models\Section::withClass(),
            'subjects'  => Subject::all(),
            'students'  => $students,
            'schedule'  => $schedule,
            'existing'  => $existing,
            'status'    => $schedule ? (new MarksWorkflowService())->currentStatus((int) $schedule['id']) : 'draft',
            'selected'  => ['class_id' => $classId, 'section_id' => $sectionId, 'subject_id' => $subjectId],
        ]);
    }

    public function storeMarks(Request $request, string $id): Response
    {
        $examId = (int) $id;
        $classId = (int) $request->input('class_id', 0);
        $sectionId = (int) $request->input('section_id', 0);
        $subjectId = (int) $request->input('subject_id', 0);
        $marks = (array) $request->input('marks', []); // [student_id => marks]

        if (!$classId || !$subjectId) {
            return $this->back(['marks' => ['Select class and subject.']]);
        }

        $schedule = $this->ensureSchedule($examId, $classId, $subjectId);

        // Locked/approved/published marks cannot be edited by teachers.
        if ((new MarksWorkflowService())->isLocked((int) $schedule['id'])) {
            return $this->back(['marks' => ['These marks are locked (approved/published). Request an unlock to edit.']]);
        }

        $fullMarks = (float) ($schedule['full_marks'] ?? 100);

        foreach ($marks as $studentId => $obtained) {
            if ($obtained === '' || $obtained === null) {
                continue;
            }
            $obtained = (float) $obtained;
            $percent = $fullMarks > 0 ? ($obtained / $fullMarks) * 100 : 0;
            $gradeId = Exam::gradeForPercentage($percent);

            $existing = QueryBuilder::table('exam_marks')
                ->where('exam_schedule_id', (int) $schedule['id'])->where('student_id', (int) $studentId)->first();

            $payload = [
                'marks_obtained' => $obtained,
                'grade_id'       => $gradeId,
                'entered_by'     => auth()->id(),
                'updated_at'     => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                QueryBuilder::table('exam_marks')->where('id', $existing['id'])->update($payload);
            } else {
                $payload['exam_schedule_id'] = (int) $schedule['id'];
                $payload['student_id'] = (int) $studentId;
                $payload['created_at'] = date('Y-m-d H:i:s');
                QueryBuilder::table('exam_marks')->insert($payload);
            }
        }

        AuditLogger::log('exam.marks_entered', 'exam', $examId, 'Entered marks');
        return $this->redirect("/admin/exams/{$examId}/marks?class_id={$classId}&section_id={$sectionId}&subject_id={$subjectId}", ['success' => 'Marks saved.']);
    }

    /**
     * Transition a marks batch (exam + class + subject) through the workflow:
     * submit | review | approve | reject | publish | lock | unlock.
     */
    public function marksWorkflow(Request $request, string $id): Response
    {
        $classId = (int) $request->input('class_id', 0);
        $subjectId = (int) $request->input('subject_id', 0);
        $action = (string) $request->input('action', '');
        $schedule = QueryBuilder::table('exam_schedules')
            ->where('exam_id', (int) $id)->where('class_id', $classId)->where('subject_id', $subjectId)->first();
        if ($schedule === null) {
            return $this->back(['marks' => ['No marks schedule found for that class/subject.']]);
        }
        $result = (new MarksWorkflowService())->transition((int) $schedule['id'], $action, auth()->id());
        if ($result['ok'] && $action === 'publish') {
            $this->notifyResultsPublished((int) $schedule['class_id']);
        }
        $back = "/admin/exams/{$id}/marks?class_id={$classId}&subject_id={$subjectId}";
        return $this->redirect($back, [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }

    /**
     * Notify students of a class (and their guardians) that results are out.
     */
    private function notifyResultsPublished(int $classId): void
    {
        try {
            $students = QueryBuilder::table('students')
                ->select(['students.id', 'students.user_id'])
                ->where('class_id', $classId)->whereNull('deleted_at')->get();
            $notif = new \App\Services\NotificationService();
            foreach ($students as $s) {
                if (!empty($s['user_id'])) {
                    $notif->notify((int) $s['user_id'], 'result', 'Results published', 'Your exam results have been published.', '/student/results');
                }
                $guardians = QueryBuilder::table('student_guardians')
                    ->select(['guardians.user_id'])
                    ->join('guardians', 'student_guardians.guardian_id', '=', 'guardians.id')
                    ->where('student_guardians.student_id', (int) $s['id'])->get();
                foreach ($guardians as $g) {
                    if (!empty($g['user_id'])) {
                        $notif->notify((int) $g['user_id'], 'result', 'Results published', 'Your child\'s results have been published.', '/parent/dashboard');
                    }
                }
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Aggregate marks into report cards for an exam.
     */
    public function reportCards(Request $request, string $id): Response
    {
        $examId = (int) $id;
        $exam = Exam::find($examId);
        if ($exam === null) {
            abort(404, 'Exam not found.');
        }

        // Ensure a versioned report card (with a unique RC id) exists for every
        // student with marks, then load the latest cards for display.
        $svc = new \App\Services\ReportCardService();
        $cards = [];
        foreach ($svc->ensureForExam($examId) as $r) {
            $cards[] = array_merge($r, [
                'name'       => trim((string) (($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))),
                'verify_url' => url('/verify/report-card/' . $r['rc_uid']),
            ]);
        }

        return $this->view('admin.exams.report-cards', [
            'title' => 'Report Cards · ' . $exam['name'],
            'exam'  => $exam,
            'cards' => $cards,
        ]);
    }

    /** Regenerate a student's report card as a new version (history preserved). */
    public function regenerateReportCard(Request $request, string $id, string $studentId): Response
    {
        $examId = (int) $id;
        if (Exam::find($examId) === null) {
            abort(404, 'Exam not found.');
        }
        $reason = trim((string) $request->input('reason', '')) ?: 'Manual regeneration';
        (new \App\Services\ReportCardService())->generate($examId, (int) $studentId, $reason);
        return $this->redirect('/admin/exams/' . $examId . '/report-cards', ['success' => 'Report card regenerated as a new version (previous version preserved).']);
    }

    /** Individual, branded report card (HTML preview / print) for one student. */
    public function reportCard(Request $request, string $id, string $studentId): Response
    {
        $examId = (int) $id; $sid = (int) $studentId;
        if (Exam::find($examId) === null) {
            abort(404, 'Exam not found.');
        }
        $doc = (new \App\Services\ReportCardDocumentService())->build($examId, $sid);
        if ($doc === null) {
            abort(404, 'No marks/report card available for this student.');
        }
        return $this->view('report-cards.show', [
            'title'       => 'Report Card',
            'doc'         => $doc,
            'downloadUrl' => url('/admin/exams/' . $examId . '/report-cards/' . $sid . '/download'),
            'backUrl'     => url('/admin/exams/' . $examId . '/report-cards'),
        ]);
    }

    /** One-click server-side PDF download of a student's report card. */
    public function downloadReportCard(Request $request, string $id, string $studentId): Response
    {
        $examId = (int) $id; $sid = (int) $studentId;
        if (Exam::find($examId) === null) {
            abort(404, 'Exam not found.');
        }
        $doc = (new \App\Services\ReportCardDocumentService())->build($examId, $sid);
        if ($doc === null) {
            abort(404, 'No marks/report card available for this student.');
        }
        $pdf = (new \App\Services\ReportCardPdfService())->render($doc);
        AuditLogger::log('reportcard.downloaded', 'report_card', $sid, 'Downloaded ' . $doc['rc_uid']);
        return (new Response($pdf, 200))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $doc['rc_uid'] . '.pdf"');
    }

    /**
     * Find or create an exam_schedule row for exam/class/subject.
     *
     * @return array<string,mixed>
     */
    private function ensureSchedule(int $examId, int $classId, int $subjectId): array
    {
        $schedule = QueryBuilder::table('exam_schedules')
            ->where('exam_id', $examId)->where('class_id', $classId)->where('subject_id', $subjectId)->first();

        if ($schedule === null) {
            $sid = QueryBuilder::table('exam_schedules')->insert([
                'exam_id' => $examId, 'class_id' => $classId, 'subject_id' => $subjectId,
                'full_marks' => 100, 'pass_marks' => 33,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $schedule = QueryBuilder::table('exam_schedules')->where('id', $sid)->first();
        }

        return $schedule ?? [];
    }
}
