<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Services\TeacherAssignmentService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Dedicated Teaching Assignments management module (year → class → section →
 * subject → teacher → role). Provides a filterable dashboard, a cascading
 * create workflow, one-click reassignment and history-safe removal. All
 * relationship/duplicate/conflict rules are enforced in TeacherAssignmentService
 * (application + database layers).
 */
final class TeachingAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $currentYearId = (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);

        $filters = [
            'academic_year_id' => (int) $request->query('academic_year_id', 0),
            'class_id'         => (int) $request->query('class_id', 0),
            'section_id'       => (int) $request->query('section_id', 0),
            'subject_id'       => (int) $request->query('subject_id', 0),
            'staff_id'         => (int) $request->query('staff_id', 0),
            'role'             => (string) $request->query('role', ''),
        ];

        $assignments = TeacherAssignment::filtered($filters);

        // Statistics.
        $total = (int) QueryBuilder::table('teacher_assignments')->count();
        $currentCount = (int) QueryBuilder::table('teacher_assignments')->where('academic_year_id', $currentYearId)->count();
        $subjectsNoTeacher = (int) ($this->safeScalar(
            'SELECT COUNT(*) FROM `class_subjects` cs WHERE NOT EXISTS (SELECT 1 FROM `teacher_assignments` ta WHERE ta.`class_id`=cs.`class_id` AND ta.`subject_id`=cs.`subject_id` AND ta.`academic_year_id` = ?)',
            [$currentYearId]
        ));
        $conflicts = (int) ($this->safeScalar(
            "SELECT COUNT(*) FROM (SELECT 1 FROM `teacher_assignments` WHERE `role`='subject_teacher' GROUP BY `academic_year_id`,`class_id`,`section_id`,`subject_id` HAVING COUNT(*) > 1) t"
        ));

        return $this->view('admin.teaching-assignments.index', [
            'title'       => 'Teaching Assignments',
            'assignments' => $assignments,
            'filters'     => $filters,
            'years'       => AcademicYear::query()->orderBy('start_date', 'DESC')->get(),
            'classes'     => ClassModel::ordered(),
            'sections'    => Section::withClass(),
            'subjects'    => Subject::query()->where('status', 'active')->orderBy('name')->get(),
            'teachers'    => Staff::query()->where('staff_type', 'teaching')->orderBy('first_name')->get(),
            'currentYearId' => $currentYearId,
            'stats'       => [
                'total' => $total, 'current' => $currentCount,
                'subjects_no_teacher' => $subjectsNoTeacher, 'conflicts' => $conflicts,
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'academic_year_id' => 'required|integer',
            'class_id'         => 'required|integer',
            'section_id'       => 'nullable|integer',
            'subject_id'       => 'required|integer',
            'staff_id'         => 'required|integer',
            'role'             => 'required|in:subject_teacher,assistant_teacher',
        ]);
        $result = (new TeacherAssignmentService())->assign(
            (int) $data['academic_year_id'], (int) $data['class_id'], ((int) ($data['section_id'] ?? 0)) ?: null,
            (int) $data['subject_id'], (int) $data['staff_id'], $data['role']
        );
        return $this->redirect('/admin/teaching-assignments', [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }

    public function reassign(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'new_staff_id'   => 'required|integer',
            'reason'         => 'required|string|max:255',
            'effective_date' => 'nullable|date',
        ]);
        $result = (new TeacherAssignmentService())->reassign(
            (int) $id, (int) $data['new_staff_id'], $data['reason'], $data['effective_date'] ?: null
        );
        return $this->redirect('/admin/teaching-assignments', [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }

    public function destroy(Request $request, string $id): Response
    {
        (new TeacherAssignmentService())->remove((int) $id);
        return $this->redirect('/admin/teaching-assignments', ['success' => 'Assignment removed.']);
    }

    /** @param array<int,mixed> $bindings */
    private function safeScalar(string $sql, array $bindings = []): mixed
    {
        try {
            return \Core\Database\Connection::instance()->scalar($sql, $bindings);
        } catch (\Throwable) {
            return 0;
        }
    }
}
