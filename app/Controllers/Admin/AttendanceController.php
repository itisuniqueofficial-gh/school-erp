<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ClassModel;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Services\AttendanceLockService;
use App\Services\AuditLogger;
use App\Services\CalendarService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Daily student attendance marking and monthly reporting.
 */
final class AttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'half_day', 'leave', 'holiday'];

    public function index(Request $request): Response
    {
        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0);
        $date = (string) $request->query('date', date('Y-m-d'));

        $students = [];
        $existing = [];
        if ($classId && $sectionId) {
            $students = QueryBuilder::table('students')
                ->select(['id', 'admission_no', 'roll_no', 'first_name', 'last_name'])
                ->where('class_id', $classId)
                ->where('section_id', $sectionId)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->orderBy('roll_no')->orderBy('first_name')
                ->get();
            $existing = StudentAttendance::forClassSectionDate($classId, $sectionId, $date);
        }

        return $this->view('admin.attendance.index', [
            'title'      => 'Attendance',
            'classes'    => ClassModel::ordered(),
            'sections'   => Section::withClass(),
            'students'   => $students,
            'existing'   => $existing,
            'statuses'   => self::STATUSES,
            'selected'   => ['class_id' => $classId, 'section_id' => $sectionId, 'date' => $date],
        ]);
    }

    public function store(Request $request): Response
    {
        $classId = (int) $request->input('class_id', 0);
        $sectionId = (int) $request->input('section_id', 0);
        $date = (string) $request->input('date', date('Y-m-d'));
        $statuses = (array) $request->input('status', []); // [student_id => status]

        if (!$classId || !$sectionId || $statuses === []) {
            return $this->back(['attendance' => ['Select a class, section and date, then mark students.']]);
        }

        // Enforce date locking: past dates require an approved unlock.
        $lock = new AttendanceLockService();
        if (!$lock->isEditable($classId, $sectionId, $date)) {
            return $this->back(['attendance' => [$lock->reason($classId, $sectionId, $date)]]);
        }

        // Block marking on holidays / non-working days (per-class working week,
        // falling back to the global default).
        $calendar = new CalendarService();
        if (!$calendar->isWorkingDayForScope($date, $classId, $sectionId)) {
            return $this->back(['attendance' => [(string) $calendar->reasonForScope($date, $classId, $sectionId)]]);
        }

        $marked = 0;
        foreach ($statuses as $studentId => $status) {
            if (!in_array($status, self::STATUSES, true)) {
                continue;
            }
            StudentAttendance::mark((int) $studentId, $classId, $sectionId, $date, (string) $status, auth()->id(), 'manual');
            $marked++;
        }

        AuditLogger::log('attendance.marked', 'attendance', null, "Marked {$marked} students for {$date}");

        return $this->redirect('/admin/attendance?class_id=' . $classId . '&section_id=' . $sectionId . '&date=' . $date, [
            'success' => "Attendance saved for {$marked} students.",
        ]);
    }

    public function report(Request $request): Response
    {
        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0);
        $month = (string) $request->query('month', date('Y-m'));

        $students = [];
        $grid = [];
        $daysInMonth = (int) date('t', strtotime($month . '-01'));

        if ($classId && $sectionId) {
            $students = QueryBuilder::table('students')
                ->select(['id', 'roll_no', 'first_name', 'last_name'])
                ->where('class_id', $classId)->where('section_id', $sectionId)
                ->whereNull('deleted_at')->orderBy('roll_no')->get();

            foreach (StudentAttendance::monthly($classId, $sectionId, $month) as $row) {
                $day = (int) date('j', strtotime((string) $row['date']));
                $grid[(int) $row['student_id']][$day] = (string) $row['status'];
            }
        }

        return $this->view('admin.attendance.report', [
            'title'       => 'Attendance Report',
            'classes'     => ClassModel::ordered(),
            'sections'    => Section::withClass(),
            'students'    => $students,
            'grid'        => $grid,
            'daysInMonth' => $daysInMonth,
            'selected'    => ['class_id' => $classId, 'section_id' => $sectionId, 'month' => $month],
        ]);
    }

    /** Teacher requests an unlock for a locked (class, section, date). */
    public function requestUnlock(Request $request): Response
    {
        $data = $this->validate($request, [
            'class_id'   => 'required|integer',
            'section_id' => 'required|integer',
            'date'       => 'required|date',
            'reason'     => 'required|string|max:255',
        ]);
        $result = (new AttendanceLockService())->requestUnlock(
            (int) $data['class_id'], (int) $data['section_id'], $data['date'], $data['reason']
        );
        return $this->back($result['ok'] ? [] : ['unlock' => [$result['message']]]);
    }

    /** Admin approves/rejects an unlock request (server-side gated by route). */
    public function reviewUnlock(Request $request, string $id): Response
    {
        $approve = $request->input('decision') === 'approve';
        $result = (new AttendanceLockService())->review((int) $id, $approve);
        return $this->redirect('/admin/attendance', [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }
}
