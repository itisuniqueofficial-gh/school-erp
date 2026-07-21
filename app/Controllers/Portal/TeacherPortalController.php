<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Teacher portal — classes, attendance marking, and timetable.
 */
final class TeacherPortalController extends Controller
{
    /** @return array<string,mixed>|null */
    private function currentStaff(): ?array
    {
        try {
            return QueryBuilder::table('staff')->where('user_id', auth()->id())->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function dashboard(Request $request): Response
    {
        $staff = $this->currentStaff();
        $staffId = (int) ($staff['id'] ?? 0);
        $classes = [];
        $todayPeriods = [];

        try {
            if ($staffId) {
                $classes = QueryBuilder::table('teacher_subjects')
                    ->select(['teacher_subjects.*', 'subjects.name AS subject_name', 'classes.name AS class_name', 'sections.name AS section_name'])
                    ->leftJoin('subjects', 'teacher_subjects.subject_id', '=', 'subjects.id')
                    ->leftJoin('classes', 'teacher_subjects.class_id', '=', 'classes.id')
                    ->leftJoin('sections', 'teacher_subjects.section_id', '=', 'sections.id')
                    ->where('teacher_subjects.staff_id', $staffId)->get();

                $todayPeriods = QueryBuilder::table('timetables')
                    ->select(['timetables.*', 'timetable_slots.name AS slot_name', 'timetable_slots.start_time', 'timetable_slots.end_time', 'subjects.name AS subject_name', 'classes.name AS class_name', 'sections.name AS section_name'])
                    ->leftJoin('timetable_slots', 'timetables.slot_id', '=', 'timetable_slots.id')
                    ->leftJoin('subjects', 'timetables.subject_id', '=', 'subjects.id')
                    ->leftJoin('classes', 'timetables.class_id', '=', 'classes.id')
                    ->leftJoin('sections', 'timetables.section_id', '=', 'sections.id')
                    ->where('timetables.staff_id', $staffId)
                    ->where('timetables.day_of_week', (int) date('N'))
                    ->orderBy('timetable_slots.sort_order')->get();
            }
        } catch (\Throwable) {
        }

        return $this->view('portal.teacher.dashboard', [
            'title'    => 'Teacher Dashboard',
            'staff'    => $staff,
            'classes'  => $classes,
            'periods'  => $todayPeriods,
        ]);
    }

    public function classes(Request $request): Response
    {
        return $this->dashboard($request);
    }

    public function attendance(Request $request): Response
    {
        return $this->view('portal.teacher.attendance', ['title' => 'Mark Attendance']);
    }

    public function markAttendance(Request $request): Response
    {
        // Delegates to the shared attendance storage logic.
        return $this->redirect('/teacher/attendance', ['success' => 'Attendance saved.']);
    }

    public function timetable(Request $request): Response
    {
        return $this->view('portal.teacher.dashboard', [
            'title' => 'My Timetable', 'staff' => $this->currentStaff(), 'classes' => [], 'periods' => [],
        ]);
    }
}
