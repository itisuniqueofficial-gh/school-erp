<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ClassModel;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Services\AuditLogger;
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
}
