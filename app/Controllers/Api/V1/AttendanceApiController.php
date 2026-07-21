<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Models\StudentAttendance;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * REST API for attendance.
 */
final class AttendanceApiController extends Controller
{
    public function index(Request $request): Response
    {
        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0);
        $date = (string) $request->query('date', date('Y-m-d'));

        if (!$classId || !$sectionId) {
            return $this->error('class_id and section_id are required.', 422);
        }
        $rows = array_values(StudentAttendance::forClassSectionDate($classId, $sectionId, $date));
        return $this->success($rows);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'student_id' => 'required|integer',
            'class_id'   => 'required|integer',
            'section_id' => 'required|integer',
            'date'       => 'required|date',
            'status'     => 'required|in:present,absent,late,half_day,leave,holiday',
        ]);
        StudentAttendance::mark(
            (int) $data['student_id'], (int) $data['class_id'], (int) $data['section_id'],
            $data['date'], $data['status'], auth()->id(), 'manual'
        );
        return $this->success(null, 'Attendance recorded.', 201);
    }
}
