<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Models\Exam;
use App\Models\StudentAttendance;
use App\Models\TeacherAssignment;
use App\Services\AttendanceLockService;
use App\Services\AuditLogger;
use App\Services\MarksWorkflowService;
use App\Services\NotificationService;
use App\Services\SubjectAttendanceService;
use App\Services\TeacherAssignmentService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Teacher portal — driven entirely by the teacher's real assignments
 * (teacher_assignments + class_teachers). Every action is authorized
 * server-side against those assignments; no assignment means no access.
 */
final class TeacherPortalController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'half_day', 'leave', 'excused'];

    /** @return array<string,mixed>|null */
    private function currentStaff(): ?array
    {
        try {
            return QueryBuilder::table('staff')->where('user_id', auth()->id())->whereNull('deleted_at')->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function currentYearId(): int
    {
        try {
            return (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Sections where the teacher is the class teacher (Mode A attendance). */
    private function classTeacherSections(int $staffId, int $yearId): array
    {
        return QueryBuilder::table('class_teachers')
            ->select(['class_teachers.class_id', 'class_teachers.section_id', 'classes.name AS class_name', 'sections.name AS section_name'])
            ->leftJoin('classes', 'class_teachers.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'class_teachers.section_id', '=', 'sections.id')
            ->where('class_teachers.staff_id', $staffId)
            ->where('class_teachers.academic_year_id', $yearId)
            ->get();
    }

    /**
     * Distinct (class, section) the teacher may take attendance for:
     * class-teacher sections + subject-teacher assignment sections.
     *
     * @return array<string,array<string,mixed>> keyed by "classId-sectionId"
     */
    private function markableSections(int $staffId, int $yearId): array
    {
        $out = [];
        foreach ($this->classTeacherSections($staffId, $yearId) as $r) {
            $out[$r['class_id'] . '-' . $r['section_id']] = [
                'class_id' => (int) $r['class_id'], 'section_id' => (int) $r['section_id'],
                'class_name' => $r['class_name'], 'section_name' => $r['section_name'], 'role' => 'class_teacher',
            ];
        }
        foreach (TeacherAssignment::forTeacher($staffId, $yearId) as $a) {
            $key = $a['class_id'] . '-' . $a['section_id'];
            if (!isset($out[$key])) {
                $out[$key] = [
                    'class_id' => (int) $a['class_id'], 'section_id' => (int) $a['section_id'],
                    'class_name' => $a['class_name'], 'section_name' => $a['section_name'], 'role' => 'subject_teacher',
                ];
            }
        }
        return $out;
    }

    /** May this teacher mark attendance for a class/section? (class teacher OR any subject assignment there) */
    private function canMark(int $staffId, int $yearId, int $classId, ?int $sectionId): bool
    {
        $ct = QueryBuilder::table('class_teachers')
            ->where('staff_id', $staffId)->where('academic_year_id', $yearId)
            ->where('class_id', $classId);
        $ct = $sectionId ? $ct->where('section_id', $sectionId) : $ct->whereNull('section_id');
        if ($ct->exists()) {
            return true;
        }
        $ta = QueryBuilder::table('teacher_assignments')
            ->where('staff_id', $staffId)->where('academic_year_id', $yearId)
            ->where('class_id', $classId);
        $ta = $sectionId ? $ta->where('section_id', $sectionId) : $ta->whereNull('section_id');
        return $ta->exists();
    }

    /**
     * Roster for a class/section in a year. Prefers enrolled students; falls
     * back to the denormalised students table.
     *
     * @return array<int,array<string,mixed>>
     */
    private function roster(int $yearId, int $classId, ?int $sectionId): array
    {
        $q = QueryBuilder::table('student_enrollments')
            ->select(['students.id', 'student_enrollments.roll_no', 'students.first_name', 'students.last_name', 'students.admission_no', 'students.public_id'])
            ->join('students', 'student_enrollments.student_id', '=', 'students.id')
            ->where('student_enrollments.academic_year_id', $yearId)
            ->where('student_enrollments.class_id', $classId)
            ->where('student_enrollments.status', 'active')
            ->whereNull('students.deleted_at');
        $q = $sectionId ? $q->where('student_enrollments.section_id', $sectionId) : $q->whereNull('student_enrollments.section_id');
        $rows = $q->orderBy('student_enrollments.roll_no')->get();

        if ($rows !== []) {
            return $rows;
        }

        $fb = QueryBuilder::table('students')
            ->select(['id', 'roll_no', 'first_name', 'last_name', 'admission_no', 'public_id'])
            ->where('class_id', $classId)
            ->whereNull('deleted_at')->where('status', 'active');
        $fb = $sectionId ? $fb->where('section_id', $sectionId) : $fb->whereNull('section_id');
        return $fb->orderBy('roll_no')->orderBy('first_name')->get();
    }

    // ===================================================================
    // Dashboard
    // ===================================================================
    public function dashboard(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();

        $assignments = $staffId ? TeacherAssignment::forTeacher($staffId, $yearId) : [];
        $classTeacherOf = $staffId ? $this->classTeacherSections($staffId, $yearId) : [];
        $sections = $staffId ? $this->markableSections($staffId, $yearId) : [];

        // Distinct counts.
        $subjects = [];
        foreach ($assignments as $a) {
            $subjects[(int) $a['subject_id']] = $a['subject_name'];
        }

        // Student count across markable sections.
        $studentCount = 0;
        foreach ($sections as $s) {
            $studentCount += count($this->roster($yearId, $s['class_id'], $s['section_id']));
        }

        // Attendance pending today for class-teacher sections.
        $today = date('Y-m-d');
        $pendingAttendance = [];
        foreach ($classTeacherOf as $s) {
            $expected = count($this->roster($yearId, (int) $s['class_id'], (int) $s['section_id']));
            $marked = QueryBuilder::table('student_attendance')
                ->where('class_id', (int) $s['class_id'])->where('section_id', (int) $s['section_id'])
                ->where('date', $today)->count();
            if ($expected > 0 && $marked < $expected) {
                $pendingAttendance[] = [
                    'class_name' => $s['class_name'], 'section_name' => $s['section_name'],
                    'class_id' => (int) $s['class_id'], 'section_id' => (int) $s['section_id'],
                    'marked' => $marked, 'expected' => $expected,
                ];
            }
        }

        // Pending marks: assigned (exam,class,subject) schedules in draft/correction.
        $pendingMarks = [];
        try {
            foreach ($assignments as $a) {
                $schedules = QueryBuilder::table('exam_schedules')
                    ->select(['exam_schedules.id', 'exams.name AS exam_name', 'subjects.name AS subject_name', 'classes.name AS class_name'])
                    ->leftJoin('exams', 'exam_schedules.exam_id', '=', 'exams.id')
                    ->leftJoin('subjects', 'exam_schedules.subject_id', '=', 'subjects.id')
                    ->leftJoin('classes', 'exam_schedules.class_id', '=', 'classes.id')
                    ->where('exam_schedules.class_id', (int) $a['class_id'])
                    ->where('exam_schedules.subject_id', (int) $a['subject_id'])->get();
                $wf = new MarksWorkflowService();
                foreach ($schedules as $sc) {
                    $status = $wf->currentStatus((int) $sc['id']);
                    if (in_array($status, ['draft', 'correction_required'], true)) {
                        $pendingMarks[] = $sc + ['status' => $status, 'section_id' => (int) $a['section_id']];
                    }
                }
            }
        } catch (\Throwable) {
        }

        // Upcoming exams (current year).
        $upcomingExams = [];
        try {
            $upcomingExams = QueryBuilder::table('exams')
                ->select(['exams.*'])
                ->whereRaw('(`start_date` IS NULL OR `start_date` >= ?)', [$today])
                ->orderBy('start_date')->limit(5)->get();
        } catch (\Throwable) {
        }

        return $this->view('portal.teacher.dashboard', [
            'title'             => 'Teacher Dashboard',
            'staff'             => $staff,
            'assignments'       => $assignments,
            'classTeacherOf'    => $classTeacherOf,
            'subjectCount'      => count($subjects),
            'sectionCount'      => count($sections),
            'studentCount'      => $studentCount,
            'pendingAttendance' => $pendingAttendance,
            'pendingMarks'      => $pendingMarks,
            'upcomingExams'     => $upcomingExams,
        ]);
    }

    public function classes(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();
        return $this->view('portal.teacher.classes', [
            'title'          => 'My Classes',
            'assignments'    => $staffId ? TeacherAssignment::forTeacher($staffId, $yearId) : [],
            'classTeacherOf' => $staffId ? $this->classTeacherSections($staffId, $yearId) : [],
        ]);
    }

    // ===================================================================
    // Attendance
    // ===================================================================
    public function attendance(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();
        $sections = $staffId ? $this->markableSections($staffId, $yearId) : [];

        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0);
        $date = (string) $request->query('date', date('Y-m-d'));

        $students = [];
        $existing = [];
        $editable = true;
        $lockReason = '';
        $authorized = false;

        if ($classId && $sectionId) {
            $authorized = $this->canMark($staffId, $yearId, $classId, $sectionId);
            if ($authorized) {
                $students = $this->roster($yearId, $classId, $sectionId);
                $existing = StudentAttendance::forClassSectionDate($classId, $sectionId, $date);
                $lock = new AttendanceLockService();
                $editable = $lock->isEditable($classId, $sectionId, $date);
                $lockReason = $lock->reason($classId, $sectionId, $date);
            }
        }

        return $this->view('portal.teacher.attendance', [
            'title'      => 'Mark Attendance',
            'sections'   => $sections,
            'students'   => $students,
            'existing'   => $existing,
            'statuses'   => self::STATUSES,
            'editable'   => $editable,
            'lockReason' => $lockReason,
            'authorized' => $authorized,
            'selected'   => ['class_id' => $classId, 'section_id' => $sectionId, 'date' => $date],
        ]);
    }

    public function markAttendance(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();

        $classId = (int) $request->input('class_id', 0);
        $sectionId = (int) $request->input('section_id', 0);
        $date = (string) $request->input('date', date('Y-m-d'));
        $statuses = (array) $request->input('status', []);

        $back = '/teacher/attendance?class_id=' . $classId . '&section_id=' . $sectionId . '&date=' . $date;

        if (!$classId || !$sectionId || $statuses === []) {
            return $this->redirect($back, ['error' => 'Select a class, section and date, then mark students.']);
        }

        // Server-side authorization: no assignment => no access.
        if (!$this->canMark($staffId, $yearId, $classId, $sectionId)) {
            return $this->redirect('/teacher/attendance', ['error' => 'You are not authorized to mark attendance for that class/section.']);
        }

        // Date validation + locking.
        $lock = new AttendanceLockService();
        if (!$lock->isEditable($classId, $sectionId, $date)) {
            return $this->redirect($back, ['error' => $lock->reason($classId, $sectionId, $date)]);
        }

        $marked = 0;
        foreach ($statuses as $studentId => $status) {
            if (!in_array($status, self::STATUSES, true)) {
                continue;
            }
            // 'excused' maps to the schema's 'leave' status.
            $dbStatus = $status === 'excused' ? 'leave' : $status;
            StudentAttendance::mark((int) $studentId, $classId, $sectionId, $date, $dbStatus, auth()->id(), 'manual');
            $marked++;
        }

        AuditLogger::log('attendance.marked', 'attendance', null, "Teacher {$staffId} marked {$marked} students for {$classId}-{$sectionId} on {$date}");

        return $this->redirect($back, ['success' => "Attendance saved for {$marked} student(s)."]);
    }

    // ===================================================================
    // Subject-wise attendance (Mode B)
    // ===================================================================
    public function subjectAttendance(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();
        $assignments = $staffId ? TeacherAssignment::forTeacher($staffId, $yearId) : [];

        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0);
        $subjectId = (int) $request->query('subject_id', 0);
        $date = (string) $request->query('date', date('Y-m-d'));

        $students = [];
        $existing = [];
        $editable = true;
        $lockReason = '';
        $authorized = false;

        if ($classId && $sectionId && $subjectId) {
            $authorized = (new TeacherAssignmentService())->canTeach($staffId, $yearId, $classId, $sectionId, $subjectId);
            if ($authorized) {
                $students = $this->roster($yearId, $classId, $sectionId);
                $existing = (new SubjectAttendanceService())->forSession($yearId, $classId, $sectionId, $subjectId, $date);
                $lock = new AttendanceLockService();
                $editable = $lock->isEditable($classId, $sectionId, $date);
                $lockReason = $lock->reason($classId, $sectionId, $date);
            }
        }

        return $this->view('portal.teacher.subject-attendance', [
            'title'       => 'Subject Attendance',
            'assignments' => $assignments,
            'students'    => $students,
            'existing'    => $existing,
            'statuses'    => ['present', 'absent', 'late', 'excused'],
            'editable'    => $editable,
            'lockReason'  => $lockReason,
            'authorized'  => $authorized,
            'selected'    => ['class_id' => $classId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'date' => $date],
        ]);
    }

    public function markSubjectAttendance(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();

        $classId = (int) $request->input('class_id', 0);
        $sectionId = (int) $request->input('section_id', 0);
        $subjectId = (int) $request->input('subject_id', 0);
        $date = (string) $request->input('date', date('Y-m-d'));
        $statuses = (array) $request->input('status', []);
        $back = "/teacher/subject-attendance?class_id={$classId}&section_id={$sectionId}&subject_id={$subjectId}&date={$date}";

        if (!$classId || !$sectionId || !$subjectId || $statuses === []) {
            return $this->redirect('/teacher/subject-attendance', ['error' => 'Select class, section, subject and date, then mark students.']);
        }

        // Server-side authorization against the teacher's assignments.
        if (!(new TeacherAssignmentService())->canTeach($staffId, $yearId, $classId, $sectionId, $subjectId)) {
            return $this->redirect('/teacher/subject-attendance', ['error' => 'You are not assigned to this subject for that class/section.']);
        }

        $lock = new AttendanceLockService();
        if (!$lock->isEditable($classId, $sectionId, $date)) {
            return $this->redirect($back, ['error' => $lock->reason($classId, $sectionId, $date)]);
        }

        $marked = (new SubjectAttendanceService())->markBatch($statuses, $yearId, $classId, $sectionId, $subjectId, $date, auth()->id());
        AuditLogger::log('subject_attendance.marked', 'subject_attendance', null, "Teacher {$staffId} marked {$marked} for subject {$subjectId} {$classId}-{$sectionId} on {$date}");
        return $this->redirect($back, ['success' => "Subject attendance saved for {$marked} student(s)."]);
    }

    // ===================================================================
    // Marks
    // ===================================================================
    public function marks(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();
        $assignments = $staffId ? TeacherAssignment::forTeacher($staffId, $yearId) : [];

        $examId = (int) $request->query('exam_id', 0);
        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0);
        $subjectId = (int) $request->query('subject_id', 0);

        $exams = [];
        try {
            $exams = QueryBuilder::table('exams')->orderBy('start_date', 'DESC')->get();
        } catch (\Throwable) {
        }

        $students = [];
        $existing = [];
        $schedule = null;
        $status = 'draft';
        $locked = false;
        $authorized = false;

        if ($examId && $classId && $sectionId && $subjectId) {
            $authorized = (new TeacherAssignmentService())->canTeach($staffId, $yearId, $classId, $sectionId, $subjectId);
            if ($authorized) {
                $schedule = $this->ensureSchedule($examId, $classId, $subjectId);
                $students = $this->roster($yearId, $classId, $sectionId);
                foreach (QueryBuilder::table('exam_marks')->where('exam_schedule_id', (int) $schedule['id'])->get() as $m) {
                    $existing[(int) $m['student_id']] = $m;
                }
                $wf = new MarksWorkflowService();
                $status = $wf->currentStatus((int) $schedule['id']);
                $locked = $wf->isLocked((int) $schedule['id']);
            }
        }

        return $this->view('portal.teacher.marks', [
            'title'       => 'Marks Entry',
            'assignments' => $assignments,
            'exams'       => $exams,
            'students'    => $students,
            'existing'    => $existing,
            'schedule'    => $schedule,
            'status'      => $status,
            'locked'      => $locked,
            'authorized'  => $authorized,
            'selected'    => ['exam_id' => $examId, 'class_id' => $classId, 'section_id' => $sectionId, 'subject_id' => $subjectId],
        ]);
    }

    public function storeMarks(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();

        $examId = (int) $request->input('exam_id', 0);
        $classId = (int) $request->input('class_id', 0);
        $sectionId = (int) $request->input('section_id', 0);
        $subjectId = (int) $request->input('subject_id', 0);
        $marks = (array) $request->input('marks', []);

        $back = "/teacher/marks?exam_id={$examId}&class_id={$classId}&section_id={$sectionId}&subject_id={$subjectId}";

        if (!$examId || !$classId || !$sectionId || !$subjectId) {
            return $this->redirect('/teacher/marks', ['error' => 'Select exam, class, section and subject.']);
        }

        // Server-side authorization against assignments.
        if (!(new TeacherAssignmentService())->canTeach($staffId, $yearId, $classId, $sectionId, $subjectId)) {
            return $this->redirect('/teacher/marks', ['error' => 'You are not assigned to this subject for that class/section.']);
        }

        $schedule = $this->ensureSchedule($examId, $classId, $subjectId);
        $wf = new MarksWorkflowService();
        if ($wf->isLocked((int) $schedule['id'])) {
            return $this->redirect($back, ['error' => 'These marks are locked (approved/published). Request an unlock to edit.']);
        }

        $fullMarks = (float) ($schedule['full_marks'] ?? 100);
        $saved = 0;
        foreach ($marks as $studentId => $obtained) {
            if ($obtained === '' || $obtained === null) {
                continue;
            }
            $obtained = (float) $obtained;
            if ($obtained < 0 || $obtained > $fullMarks) {
                continue; // enforce 0 <= marks <= max
            }
            $percent = $fullMarks > 0 ? ($obtained / $fullMarks) * 100 : 0;
            $gradeId = Exam::gradeForPercentage($percent);

            $existing = QueryBuilder::table('exam_marks')
                ->where('exam_schedule_id', (int) $schedule['id'])->where('student_id', (int) $studentId)->first();
            $payload = [
                'marks_obtained' => $obtained, 'grade_id' => $gradeId,
                'entered_by' => auth()->id(), 'status' => 'draft', 'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($existing) {
                QueryBuilder::table('exam_marks')->where('id', $existing['id'])->update($payload);
            } else {
                $payload['exam_schedule_id'] = (int) $schedule['id'];
                $payload['student_id'] = (int) $studentId;
                $payload['created_at'] = date('Y-m-d H:i:s');
                QueryBuilder::table('exam_marks')->insert($payload);
            }
            $saved++;
        }

        AuditLogger::log('marks.entered', 'exam_schedule', (int) $schedule['id'], "Teacher {$staffId} entered {$saved} marks");
        return $this->redirect($back, ['success' => "Saved marks for {$saved} student(s)."]);
    }

    public function submitMarks(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $yearId = $this->currentYearId();

        $examId = (int) $request->input('exam_id', 0);
        $classId = (int) $request->input('class_id', 0);
        $sectionId = (int) $request->input('section_id', 0);
        $subjectId = (int) $request->input('subject_id', 0);
        $back = "/teacher/marks?exam_id={$examId}&class_id={$classId}&section_id={$sectionId}&subject_id={$subjectId}";

        if (!(new TeacherAssignmentService())->canTeach($staffId, $yearId, $classId, $sectionId, $subjectId)) {
            return $this->redirect('/teacher/marks', ['error' => 'Not authorized for this subject.']);
        }

        $schedule = QueryBuilder::table('exam_schedules')
            ->where('exam_id', $examId)->where('class_id', $classId)->where('subject_id', $subjectId)->first();
        if ($schedule === null) {
            return $this->redirect($back, ['error' => 'Enter marks before submitting.']);
        }

        $result = (new MarksWorkflowService())->transition((int) $schedule['id'], 'submit', auth()->id());
        if ($result['ok']) {
            (new NotificationService())->notifyRoles(['school_admin', 'principal', 'vice_principal'], 'marks',
                'Marks submitted for approval', 'A teacher submitted marks awaiting your approval.', '/admin/exams');
        }
        return $this->redirect($back, [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }

    public function timetable(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $periods = [];
        try {
            if ($staffId) {
                $periods = QueryBuilder::table('timetables')
                    ->select(['timetables.*', 'timetable_slots.name AS slot_name', 'timetable_slots.start_time', 'timetable_slots.end_time', 'subjects.name AS subject_name', 'classes.name AS class_name', 'sections.name AS section_name', 'timetables.day_of_week'])
                    ->leftJoin('timetable_slots', 'timetables.slot_id', '=', 'timetable_slots.id')
                    ->leftJoin('subjects', 'timetables.subject_id', '=', 'subjects.id')
                    ->leftJoin('classes', 'timetables.class_id', '=', 'classes.id')
                    ->leftJoin('sections', 'timetables.section_id', '=', 'sections.id')
                    ->where('timetables.staff_id', $staffId)
                    ->orderBy('timetables.day_of_week')->orderBy('timetable_slots.sort_order')->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.teacher.timetable', ['title' => 'My Timetable', 'periods' => $periods]);
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
            // Use the class_subjects marks config where available.
            $cs = QueryBuilder::table('class_subjects')->where('class_id', $classId)->where('subject_id', $subjectId)->first();
            $sid = QueryBuilder::table('exam_schedules')->insert([
                'exam_id' => $examId, 'class_id' => $classId, 'subject_id' => $subjectId,
                'full_marks' => (int) ($cs['full_marks'] ?? 100), 'pass_marks' => (int) ($cs['pass_marks'] ?? 33),
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $schedule = QueryBuilder::table('exam_schedules')->where('id', $sid)->first();
        }
        return $schedule ?? [];
    }
}
