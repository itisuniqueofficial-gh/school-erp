<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Student portal — self-service views for the authenticated student.
 */
final class StudentPortalController extends Controller
{
    /**
     * Resolve the student record linked to the current user.
     *
     * @return array<string,mixed>|null
     */
    private function currentStudent(): ?array
    {
        try {
            return QueryBuilder::table('students')->where('user_id', auth()->id())->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function dashboard(Request $request): Response
    {
        $student = $this->currentStudent();
        $studentId = (int) ($student['id'] ?? 0);

        $attendance = ['present' => 0, 'total' => 0, 'rate' => 0.0];
        $feesDue = 0.0;
        try {
            if ($studentId) {
                $total = QueryBuilder::table('student_attendance')->where('student_id', $studentId)->count();
                $present = QueryBuilder::table('student_attendance')->where('student_id', $studentId)->whereIn('status', ['present', 'late', 'half_day'])->count();
                $attendance = [
                    'present' => $present,
                    'total'   => $total,
                    'rate'    => $total ? round(($present / $total) * 100, 1) : 0.0,
                ];
                $feesDue = (float) (QueryBuilder::table('fee_invoices')
                    ->where('student_id', $studentId)
                    ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                    ->selectRaw('COALESCE(SUM(total_amount + fine_amount - discount_amount - paid_amount),0) AS due')
                    ->value('due') ?? 0);
            }
        } catch (\Throwable) {
            // graceful fallback
        }

        return $this->view('portal.student.dashboard', [
            'title'      => 'Student Dashboard',
            'student'    => $student,
            'attendance' => $attendance,
            'feesDue'    => $feesDue,
        ]);
    }

    public function profile(Request $request): Response
    {
        return $this->view('portal.student.dashboard', [
            'title'   => 'My Profile',
            'student' => $this->currentStudent(),
            'attendance' => ['present' => 0, 'total' => 0, 'rate' => 0.0],
            'feesDue' => 0.0,
        ]);
    }

    public function attendance(Request $request): Response
    {
        $student = $this->currentStudent();
        $records = [];
        try {
            if ($student) {
                $records = QueryBuilder::table('student_attendance')
                    ->where('student_id', (int) $student['id'])
                    ->orderBy('date', 'DESC')->limit(60)->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.student.attendance', [
            'title' => 'My Attendance', 'records' => $records,
        ]);
    }

    public function results(Request $request): Response
    {
        $student = $this->currentStudent();
        $results = [];
        try {
            if ($student) {
                $results = QueryBuilder::table('report_cards')
                    ->select(['report_cards.*', 'exams.name AS exam_name'])
                    ->leftJoin('exams', 'report_cards.exam_id', '=', 'exams.id')
                    ->where('report_cards.student_id', (int) $student['id'])
                    ->orderBy('report_cards.id', 'DESC')->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.student.results', [
            'title' => 'My Results', 'results' => $results,
        ]);
    }

    public function fees(Request $request): Response
    {
        $student = $this->currentStudent();
        $invoices = [];
        try {
            if ($student) {
                $invoices = QueryBuilder::table('fee_invoices')
                    ->where('student_id', (int) $student['id'])
                    ->orderBy('id', 'DESC')->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.student.fees', [
            'title' => 'My Fees', 'invoices' => $invoices,
        ]);
    }

    public function timetable(Request $request): Response
    {
        return $this->view('portal.student.timetable', ['title' => 'My Timetable']);
    }
}
