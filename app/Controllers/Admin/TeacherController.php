<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Staff;
use App\Models\User;
use App\Models\TeacherAssignment;
use App\Models\ClassModel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\AcademicYear;
use App\Services\TeacherAssignmentService;
use App\Services\IdSequenceService;
use App\Services\AuditLogger;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;
use Core\Security\Hash;

/**
 * Manages teaching staff: profiles, qualifications, subject allocation.
 */
final class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search'     => (string) $request->query('search', ''),
            'staff_type' => 'teaching',
            'status'     => (string) $request->query('status', ''),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $result = Staff::paginate($page, (int) config('app.per_page', 15), Staff::filtered($filters));

        return $this->view('admin.teachers.index', [
            'title'    => 'Teachers',
            'teachers' => $result['data'],
            'meta'     => $result['meta'],
            'filters'  => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin.teachers.create', [
            'title'    => 'Add Teacher',
            'code'     => Staff::generateEmployeeCode(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'first_name'  => 'required|string|max:80',
            'last_name'   => 'required|string|max:80',
            'gender'      => 'nullable|in:male,female,other',
            'email'       => 'nullable|email|max:190',
            'phone'       => 'nullable|phone',
            'designation' => 'nullable|string|max:120',
            'qualification' => 'nullable|string|max:200',
            'experience_years' => 'nullable|numeric',
            'basic_salary'=> 'nullable|numeric',
            'create_login'=> 'nullable|boolean',
        ]);

        try {
            $publicId = (new IdSequenceService())->teacher();
            $uid = (new IdSequenceService())->uidStaff();
            $staffId = Connection::instance()->transaction(function () use ($data, $request, $publicId, $uid): int {
                $userId = null;
                if ($request->boolean('create_login') && !empty($data['email'])) {
                    $userId = User::create([
                        'name' => $data['first_name'] . ' ' . $data['last_name'],
                        'uid' => (new IdSequenceService())->uidUser(),
                        'email' => $data['email'],
                        'username' => strtolower($data['first_name']) . random_int(100, 999),
                        'password' => Hash::make('Password123!'),
                        'is_active' => 1,
                    ]);
                    User::assignRole($userId, 'teacher');
                }

                return Staff::create([
                    'user_id'     => $userId,
                    'uid'         => $uid,
                    'public_id'   => $publicId,
                    'employee_code' => Staff::generateEmployeeCode(),
                    'first_name'  => $data['first_name'],
                    'last_name'   => $data['last_name'],
                    'staff_type'  => 'teaching',
                    'gender'      => $data['gender'] ?: null,
                    'email'       => $data['email'] ?: null,
                    'phone'       => $data['phone'] ?: null,
                    'designation' => $data['designation'] ?: 'Teacher',
                    'qualification' => $data['qualification'] ?: null,
                    'experience_years' => $data['experience_years'] ?: 0,
                    'basic_salary' => $data['basic_salary'] ?: 0,
                    'joining_date' => date('Y-m-d'),
                    'status'      => 'active',
                ]);
            });
        } catch (\Throwable $e) {
            logger()->error('Teacher create failed: ' . $e->getMessage());
            return $this->back(['general' => ['Could not save the teacher.']], $request->all());
        }

        AuditLogger::log('teacher.created', 'staff', $staffId, 'Added teacher ' . $data['first_name']);
        return $this->redirect('/admin/teachers/' . $staffId, ['success' => 'Teacher added successfully.']);
    }

    public function show(Request $request, string $id): Response
    {
        $teacher = Staff::profile((int) $id);
        if ($teacher === null) {
            abort(404, 'Teacher not found.');
        }
        return $this->view('admin.teachers.show', [
            'title'          => $teacher['first_name'] . ' ' . $teacher['last_name'],
            'teacher'        => $teacher,
            'qualifications' => Staff::qualifications((int) $id),
            'subjects'       => Staff::subjects((int) $id),
            'assignments'    => TeacherAssignment::forTeacher((int) $id),
            'assignmentHistory' => (new TeacherAssignmentService())->historyForTeacher((int) $id),
            'otherTeachers'  => Staff::query()->where('staff_type', 'teaching')->whereRaw('`id` != ?', [(int) $id])->orderBy('first_name')->get(),
            'years'          => AcademicYear::all(),
            'classes'        => ClassModel::ordered(),
            'sections'       => Section::withClass(),
            'allSubjects'    => Subject::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Add a teaching assignment (year → class → section → subject → teacher).
     */
    public function storeAssignment(Request $request, string $id): Response
    {
        if (Staff::find((int) $id) === null) {
            abort(404, 'Teacher not found.');
        }
        $data = $this->validate($request, [
            'academic_year_id' => 'required|integer',
            'class_id'         => 'required|integer',
            'section_id'       => 'required|integer',
            'subject_id'       => 'required|integer',
            'role'             => 'required|in:subject_teacher,assistant_teacher',
        ]);
        $result = (new TeacherAssignmentService())->assign(
            (int) $data['academic_year_id'], (int) $data['class_id'], (int) $data['section_id'],
            (int) $data['subject_id'], (int) $id, $data['role']
        );
        return $this->redirect('/admin/teachers/' . $id, [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }

    public function removeAssignment(Request $request, string $id, string $assignmentId): Response
    {
        (new TeacherAssignmentService())->remove((int) $assignmentId);
        return $this->redirect('/admin/teachers/' . $id, ['success' => 'Assignment removed.']);
    }

    /** One-click teacher reassignment (with effective date + reason + history). */
    public function reassignAssignment(Request $request, string $id, string $assignmentId): Response
    {
        $data = $this->validate($request, [
            'new_staff_id'   => 'required|integer',
            'reason'         => 'required|string|max:255',
            'effective_date' => 'nullable|date',
        ]);
        $result = (new TeacherAssignmentService())->reassign(
            (int) $assignmentId, (int) $data['new_staff_id'], $data['reason'], $data['effective_date'] ?: null
        );
        return $this->redirect('/admin/teachers/' . $id . '#assignments', [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }

    public function assignClassTeacher(Request $request, string $id): Response
    {
        if (Staff::find((int) $id) === null) {
            abort(404, 'Teacher not found.');
        }
        $data = $this->validate($request, [
            'academic_year_id' => 'required|integer',
            'class_id'         => 'required|integer',
            'section_id'       => 'required|integer',
        ]);
        $result = (new TeacherAssignmentService())->assignClassTeacher(
            (int) $data['academic_year_id'], (int) $data['class_id'], (int) $data['section_id'], (int) $id
        );
        return $this->redirect('/admin/teachers/' . $id, [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }

    public function edit(Request $request, string $id): Response
    {
        $teacher = Staff::find((int) $id);
        if ($teacher === null) {
            abort(404, 'Teacher not found.');
        }
        return $this->view('admin.teachers.edit', ['title' => 'Edit Teacher', 'teacher' => $teacher]);
    }

    public function update(Request $request, string $id): Response
    {
        if (Staff::find((int) $id) === null) {
            abort(404, 'Teacher not found.');
        }
        $data = $this->validate($request, [
            'first_name'  => 'required|string|max:80',
            'last_name'   => 'required|string|max:80',
            'email'       => 'nullable|email|max:190',
            'phone'       => 'nullable|phone',
            'designation' => 'nullable|string|max:120',
            'status'      => 'required|in:active,inactive,on_leave,resigned',
        ]);

        Staff::updateById((int) $id, [
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'email'       => $data['email'] ?: null,
            'phone'       => $data['phone'] ?: null,
            'designation' => $data['designation'] ?: null,
            'qualification' => $request->input('qualification'),
            'experience_years' => (float) $request->input('experience_years', 0),
            'basic_salary'=> (float) $request->input('basic_salary', 0),
            'status'      => $data['status'],
        ]);

        AuditLogger::log('teacher.updated', 'staff', (int) $id, 'Updated teacher');
        return $this->redirect('/admin/teachers/' . $id, ['success' => 'Teacher updated.']);
    }

    public function destroy(Request $request, string $id): Response
    {
        if (Staff::find((int) $id) === null) {
            abort(404, 'Teacher not found.');
        }
        Staff::deleteById((int) $id);
        AuditLogger::log('teacher.deleted', 'staff', (int) $id, 'Removed teacher');
        return $this->redirect('/admin/teachers', ['success' => 'Teacher removed.']);
    }
}
