<?php

declare(strict_types=1);

namespace Database\Seeds;

use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Security\Hash;

/**
 * Seeds baseline data: roles, permissions, role-permission map, demo users,
 * academic structure, grading scale, and default settings.
 * Idempotent where practical (checks before inserting core singletons).
 */
final class DatabaseSeeder
{
    public function run(): void
    {
        Connection::instance()->transaction(function (): void {
            $this->seedRoles();
            $this->seedPermissions();
            $this->mapRolePermissions();
            $this->seedAcademic();
            $this->seedGrades();
            $this->seedExamTypes();
            $this->seedFeeCategories();
            $this->seedLeaveTypes();
            $this->seedSettings();
            $this->seedUsers();
            $this->seedAcademicSystem();
        });
    }

    /** @var array<string,int> role slug => id */
    private array $roleIds = [];

    /** @var array<string,int> permission slug => id */
    private array $permIds = [];

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function seedRoles(): void
    {
        $roles = [
            ['super_admin', 'Super Administrator', 'Full unrestricted access'],
            ['school_admin', 'School Administrator', 'Manages the whole school'],
            ['principal', 'Principal', 'Head of the institution'],
            ['vice_principal', 'Vice Principal', 'Assists the principal'],
            ['teacher', 'Teacher', 'Teaching staff'],
            ['class_teacher', 'Class Teacher', 'Teacher in charge of a section'],
            ['accountant', 'Accountant', 'Manages fees and finances'],
            ['librarian', 'Librarian', 'Manages the library'],
            ['receptionist', 'Receptionist', 'Front desk operations'],
            ['transport_manager', 'Transport Manager', 'Manages transport'],
            ['hr', 'HR Manager', 'Human resources & payroll'],
            ['student', 'Student', 'Enrolled student'],
            ['parent', 'Parent/Guardian', 'Guardian of a student'],
        ];

        foreach ($roles as [$slug, $name, $desc]) {
            $existing = QueryBuilder::table('roles')->where('slug', $slug)->first();
            if ($existing) {
                $this->roleIds[$slug] = (int) $existing['id'];
                continue;
            }
            $this->roleIds[$slug] = QueryBuilder::table('roles')->insert([
                'slug' => $slug, 'name' => $name, 'description' => $desc,
                'is_system' => 1, 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
    }

    private function seedPermissions(): void
    {
        $modules = [
            'students'      => ['view', 'create', 'update', 'delete', 'documents', 'verify', 'reveal_id'],
            'teachers'      => ['view', 'create', 'update', 'delete'],
            'staff'         => ['view', 'create', 'update', 'delete'],
            'users'         => ['view', 'manage'],
            'academic'      => ['view', 'manage'],
            'attendance'    => ['view', 'mark', 'unlock_request', 'unlock_approve'],
            'exams'         => ['view', 'manage'],
            'marks'         => ['submit', 'approve', 'publish', 'unlock'],
            'fees'          => ['view', 'manage', 'collect'],
            'library'       => ['view', 'manage'],
            'transport'     => ['view', 'manage'],
            'hostel'        => ['view', 'manage'],
            'inventory'     => ['view', 'manage'],
            'hr'            => ['view', 'manage'],
            'communication' => ['view', 'send'],
            'certificates'  => ['view', 'manage'],
            'reports'       => ['view'],
            'roles'         => ['view', 'manage'],
            'settings'      => ['view', 'manage'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $slug = "{$module}.{$action}";
                $existing = QueryBuilder::table('permissions')->where('slug', $slug)->first();
                if ($existing) {
                    $this->permIds[$slug] = (int) $existing['id'];
                    continue;
                }
                $this->permIds[$slug] = QueryBuilder::table('permissions')->insert([
                    'slug'   => $slug,
                    'name'   => ucfirst($action) . ' ' . ucfirst($module),
                    'module' => $module,
                    'created_at' => $this->now(), 'updated_at' => $this->now(),
                ]);
            }
        }
    }

    private function assign(string $role, array $permSlugs): void
    {
        $roleId = $this->roleIds[$role] ?? null;
        if ($roleId === null) {
            return;
        }
        foreach ($permSlugs as $slug) {
            $permId = $this->permIds[$slug] ?? null;
            if ($permId === null) {
                continue;
            }
            $exists = QueryBuilder::table('role_permissions')
                ->where('role_id', $roleId)->where('permission_id', $permId)->first();
            if (!$exists) {
                QueryBuilder::table('role_permissions')->insert([
                    'role_id' => $roleId, 'permission_id' => $permId,
                ]);
            }
        }
    }

    private function mapRolePermissions(): void
    {
        $all = array_keys($this->permIds);

        // super_admin bypasses in code but also gets all rows for completeness.
        $this->assign('super_admin', $all);
        $this->assign('school_admin', $all);
        $this->assign('principal', $all);
        $this->assign('vice_principal', array_filter($all, fn ($p) => !str_starts_with($p, 'settings') && !str_starts_with($p, 'roles')));

        $this->assign('teacher', ['students.view', 'attendance.view', 'attendance.mark', 'attendance.unlock_request', 'exams.view', 'exams.manage', 'marks.submit', 'academic.view', 'communication.view', 'reports.view']);
        $this->assign('class_teacher', ['students.view', 'students.update', 'attendance.view', 'attendance.mark', 'attendance.unlock_request', 'exams.view', 'exams.manage', 'marks.submit', 'academic.view', 'communication.view', 'communication.send', 'reports.view']);
        $this->assign('accountant', ['fees.view', 'fees.manage', 'fees.collect', 'students.view', 'reports.view']);
        $this->assign('librarian', ['library.view', 'library.manage', 'students.view']);
        $this->assign('receptionist', ['students.view', 'students.create', 'students.documents', 'communication.view', 'communication.send']);
        $this->assign('transport_manager', ['transport.view', 'transport.manage', 'students.view']);
        $this->assign('hr', ['staff.view', 'staff.create', 'staff.update', 'hr.view', 'hr.manage', 'reports.view']);
        $this->assign('student', []);
        $this->assign('parent', []);
    }

    private function createUser(string $name, string $email, string $username, string $role): int
    {
        $existing = QueryBuilder::table('users')->where('email', $email)->first();
        if ($existing) {
            $userId = (int) $existing['id'];
        } else {
            $userId = QueryBuilder::table('users')->insert([
                'name'       => $name,
                'email'      => $email,
                'username'   => $username,
                'password'   => Hash::make('Password123!'),
                'is_active'  => 1,
                'email_verified_at' => $this->now(),
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $roleId = $this->roleIds[$role] ?? null;
        if ($roleId !== null) {
            $link = QueryBuilder::table('user_roles')->where('user_id', $userId)->where('role_id', $roleId)->first();
            if (!$link) {
                QueryBuilder::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
            }
        }
        return $userId;
    }

    private function seedUsers(): void
    {
        $this->createUser('Super Admin', 'superadmin@school.test', 'superadmin', 'super_admin');
        $this->createUser('School Admin', 'admin@school.test', 'admin', 'school_admin');
        $this->createUser('Principal', 'principal@school.test', 'principal', 'principal');
        $this->createUser('Accountant', 'accountant@school.test', 'accountant', 'accountant');
        $this->createUser('Librarian', 'librarian@school.test', 'librarian', 'librarian');
        $teacherUser = $this->createUser('Demo Teacher', 'teacher@school.test', 'teacher', 'teacher');
        $studentUser = $this->createUser('Demo Student', 'student@school.test', 'student', 'student');
        $parentUser  = $this->createUser('Demo Parent', 'parent@school.test', 'parent', 'parent');

        // Link demo teacher to a staff record.
        if (!QueryBuilder::table('staff')->where('user_id', $teacherUser)->first()) {
            QueryBuilder::table('staff')->insert([
                'user_id' => $teacherUser, 'employee_code' => 'EMP-0001',
                'first_name' => 'Demo', 'last_name' => 'Teacher', 'staff_type' => 'teaching',
                'designation' => 'Senior Teacher', 'gender' => 'female', 'email' => 'teacher@school.test',
                'joining_date' => date('Y-m-d'), 'basic_salary' => 45000, 'status' => 'active',
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }

        // Link demo student to class/section.
        if (!QueryBuilder::table('students')->where('user_id', $studentUser)->first()) {
            $classId = (int) (QueryBuilder::table('classes')->orderBy('numeric_level')->value('id') ?? 0);
            $sectionId = (int) (QueryBuilder::table('sections')->value('id') ?? 0);
            $yearId = (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);
            $studentId = QueryBuilder::table('students')->insert([
                'user_id' => $studentUser, 'admission_no' => 'ADM-0001', 'roll_no' => '1',
                'first_name' => 'Demo', 'last_name' => 'Student', 'gender' => 'male',
                'dob' => '2012-05-10', 'email' => 'student@school.test',
                'class_id' => $classId ?: null, 'section_id' => $sectionId ?: null,
                'academic_year_id' => $yearId ?: null, 'admission_date' => date('Y-m-d'),
                'status' => 'active', 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);

            // Guardian.
            $guardianId = QueryBuilder::table('guardians')->insert([
                'user_id' => $parentUser, 'name' => 'Demo Parent', 'relation' => 'father',
                'email' => 'parent@school.test', 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            QueryBuilder::table('student_guardians')->insert([
                'student_id' => $studentId, 'guardian_id' => $guardianId, 'is_primary' => 1,
            ]);
        }
    }

    private function seedAcademic(): void
    {
        if (!QueryBuilder::table('academic_years')->where('is_current', 1)->first()) {
            $start = (int) date('Y');
            QueryBuilder::table('academic_years')->insert([
                'name' => $start . '-' . ($start + 1),
                'start_date' => $start . '-04-01', 'end_date' => ($start + 1) . '-03-31',
                'is_current' => 1, 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }

        // Classes 1..10.
        if (QueryBuilder::table('classes')->count() === 0) {
            for ($i = 1; $i <= 10; $i++) {
                $classId = QueryBuilder::table('classes')->insert([
                    'name' => 'Class ' . $i, 'numeric_level' => $i,
                    'created_at' => $this->now(), 'updated_at' => $this->now(),
                ]);
                foreach (['A', 'B'] as $sec) {
                    QueryBuilder::table('sections')->insert([
                        'class_id' => $classId, 'name' => $sec, 'capacity' => 40,
                        'created_at' => $this->now(), 'updated_at' => $this->now(),
                    ]);
                }
            }
        }

        // Subjects.
        if (QueryBuilder::table('subjects')->count() === 0) {
            $subjects = [
                ['English', 'ENG'], ['Mathematics', 'MATH'], ['Science', 'SCI'],
                ['Social Studies', 'SST'], ['Computer Science', 'CS'], ['Physical Education', 'PE'],
            ];
            foreach ($subjects as [$name, $code]) {
                QueryBuilder::table('subjects')->insert([
                    'name' => $name, 'code' => $code, 'type' => 'theory',
                    'created_at' => $this->now(), 'updated_at' => $this->now(),
                ]);
            }
        }

        // Timetable slots (periods).
        if (QueryBuilder::table('timetable_slots')->count() === 0) {
            $slots = [
                ['Period 1', '09:00:00', '09:45:00', 0], ['Period 2', '09:45:00', '10:30:00', 0],
                ['Break', '10:30:00', '10:45:00', 1], ['Period 3', '10:45:00', '11:30:00', 0],
                ['Period 4', '11:30:00', '12:15:00', 0], ['Lunch', '12:15:00', '13:00:00', 1],
                ['Period 5', '13:00:00', '13:45:00', 0], ['Period 6', '13:45:00', '14:30:00', 0],
            ];
            $order = 1;
            foreach ($slots as [$name, $s, $e, $break]) {
                QueryBuilder::table('timetable_slots')->insert([
                    'name' => $name, 'start_time' => $s, 'end_time' => $e,
                    'is_break' => $break, 'sort_order' => $order++,
                ]);
            }
        }
    }

    private function seedGrades(): void
    {
        if (QueryBuilder::table('grades')->count() > 0) {
            return;
        }
        $grades = [
            ['A+', 90, 100, 4.0, 'Outstanding'], ['A', 80, 89.99, 3.6, 'Excellent'],
            ['B+', 70, 79.99, 3.2, 'Very Good'], ['B', 60, 69.99, 2.8, 'Good'],
            ['C', 50, 59.99, 2.4, 'Satisfactory'], ['D', 33, 49.99, 1.6, 'Pass'],
            ['F', 0, 32.99, 0.0, 'Fail'],
        ];
        foreach ($grades as [$name, $min, $max, $gpa, $remark]) {
            QueryBuilder::table('grades')->insert([
                'name' => $name, 'min_percent' => $min, 'max_percent' => $max,
                'gpa' => $gpa, 'remark' => $remark,
            ]);
        }
    }

    private function seedExamTypes(): void
    {
        if (QueryBuilder::table('exam_types')->count() > 0) {
            return;
        }
        foreach ([['Unit Test', 10], ['Midterm', 30], ['Final', 60]] as [$name, $weight]) {
            QueryBuilder::table('exam_types')->insert([
                'name' => $name, 'weightage' => $weight,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
    }

    private function seedFeeCategories(): void
    {
        if (QueryBuilder::table('fee_categories')->count() > 0) {
            return;
        }
        foreach ([['Tuition Fee', 1], ['Admission Fee', 0], ['Exam Fee', 1], ['Transport Fee', 1], ['Library Fee', 1]] as [$name, $recurring]) {
            QueryBuilder::table('fee_categories')->insert([
                'name' => $name, 'is_recurring' => $recurring,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
    }

    private function seedLeaveTypes(): void
    {
        if (QueryBuilder::table('leave_types')->count() > 0) {
            return;
        }
        foreach ([['Casual Leave', 12, 1], ['Sick Leave', 10, 1], ['Earned Leave', 15, 1], ['Unpaid Leave', 0, 0]] as [$name, $days, $paid]) {
            QueryBuilder::table('leave_types')->insert([
                'name' => $name, 'days_allowed' => $days, 'is_paid' => $paid,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
    }

    private function seedSettings(): void
    {
        $defaults = [
            ['general', 'school_name', 'Greenfield International School', 'string'],
            ['general', 'school_email', 'info@school.test', 'string'],
            ['general', 'school_phone', '+1 555 010 2020', 'string'],
            ['general', 'school_address', '123 Education Lane, Knowledge City', 'string'],
            ['general', 'currency', '₹', 'string'],
            ['general', 'timezone', 'Asia/Kolkata', 'string'],
            ['academic', 'week_start', 'Monday', 'string'],
            ['academic', 'attendance_type', 'daily', 'string'],
        ];
        foreach ($defaults as [$group, $key, $value, $type]) {
            if (QueryBuilder::table('settings')->where('key', $key)->first()) {
                continue;
            }
            QueryBuilder::table('settings')->insert([
                'group' => $group, 'key' => $key, 'value' => $value, 'type' => $type,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
    }

    /**
     * Backfill permanent public IDs and seed one demo enrollment + teacher
     * assignment so the academic workflows have coherent starting data.
     */
    private function seedAcademicSystem(): void
    {
        $year = (int) date('Y');

        // Backfill public_id for records that lack one (never reused).
        $backfill = function (string $table, string $prefix) use ($year): void {
            $rows = QueryBuilder::table($table)->whereNull('public_id')->orderBy('id')->get();
            $n = 1;
            foreach ($rows as $row) {
                QueryBuilder::table($table)->where('id', (int) $row['id'])->update([
                    'public_id' => sprintf('%s-%d-%05d', $prefix, $year, $n++),
                ]);
            }
        };
        $backfill('users', 'USR');
        $backfill('students', 'STU');
        $backfill('guardians', 'PAR');

        // Academic-entity human-friendly IDs (AY-/CLS-/SEC-/SUB-).
        foreach (QueryBuilder::table('academic_years')->whereNull('public_id')->get() as $ay) {
            QueryBuilder::table('academic_years')->where('id', (int) $ay['id'])->update([
                'public_id' => 'AY-' . date('Y', strtotime((string) $ay['start_date'])) . '-' . date('y', strtotime((string) $ay['end_date'])),
            ]);
        }
        foreach (QueryBuilder::table('classes')->whereNull('public_id')->get() as $c) {
            QueryBuilder::table('classes')->where('id', (int) $c['id'])->update(['public_id' => sprintf('CLS-%04d', (int) $c['id'])]);
        }
        foreach (QueryBuilder::table('sections')->whereNull('public_id')->get() as $s) {
            QueryBuilder::table('sections')->where('id', (int) $s['id'])->update(['public_id' => sprintf('SEC-%04d', (int) $s['id'])]);
        }
        foreach (QueryBuilder::table('subjects')->whereNull('public_id')->get() as $s) {
            QueryBuilder::table('subjects')->where('id', (int) $s['id'])->update(['public_id' => sprintf('SUB-%04d', (int) $s['id'])]);
        }
        // Teachers vs non-teaching staff get different prefixes.
        foreach (QueryBuilder::table('staff')->whereNull('public_id')->orderBy('id')->get() as $i => $s) {
            $prefix = ($s['staff_type'] ?? 'teaching') === 'teaching' ? 'TCH' : 'STF';
            QueryBuilder::table('staff')->where('id', (int) $s['id'])->update([
                'public_id' => sprintf('%s-%d-%05d', $prefix, $year, $i + 1),
            ]);
        }

        // Universal 12-digit numeric uids for all core entities (leading digit =
        // type). Reserve each sequence's next_value so runtime generation via
        // IdSequenceService::numeric() continues without collision.
        $uidBackfill = function (string $table, string $lead): void {
            $rows = QueryBuilder::table($table)->whereNull('uid')->orderBy('id')->get();
            $seq = 1;
            $width = 12 - strlen($lead);
            foreach ($rows as $r) {
                QueryBuilder::table($table)->where('id', (int) $r['id'])
                    ->update(['uid' => $lead . str_pad((string) $seq, $width, '0', STR_PAD_LEFT)]);
                $seq++;
            }
            $prefix = 'UID' . $lead;
            $existing = QueryBuilder::table('id_sequences')->where('prefix', $prefix)->where('year', 0)->first();
            if ($existing) {
                QueryBuilder::table('id_sequences')->where('id', (int) $existing['id'])->update(['next_value' => $seq]);
            } else {
                QueryBuilder::table('id_sequences')->insert(['prefix' => $prefix, 'year' => 0, 'next_value' => $seq]);
            }
        };
        $uidBackfill('users', '1');
        $uidBackfill('students', '2');
        $uidBackfill('staff', '3');
        $uidBackfill('classes', '4');
        $uidBackfill('sections', '5');
        $uidBackfill('subjects', '6');
        $uidBackfill('academic_years', '7');
        $uidBackfill('student_documents', '8');

        // Seed id_sequences so future generated IDs continue after the demo data.
        foreach (['STU' => 2, 'TCH' => 2, 'STF' => 2, 'PAR' => 2, 'USR' => 9] as $prefix => $nextVal) {
            if (!QueryBuilder::table('id_sequences')->where('prefix', $prefix)->where('year', $year)->first()) {
                QueryBuilder::table('id_sequences')->insert(['prefix' => $prefix, 'year' => $year, 'next_value' => $nextVal]);
            }
        }

        $yearId = (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);
        $student = QueryBuilder::table('students')->where('admission_no', 'ADM-0001')->first();
        $teacher = QueryBuilder::table('staff')->where('employee_code', 'EMP-0001')->first();

        // Demo student enrollment (roll 1) for the current year.
        if ($yearId && $student && (int) ($student['class_id'] ?? 0) && (int) ($student['section_id'] ?? 0)
            && !QueryBuilder::table('student_enrollments')->where('academic_year_id', $yearId)->where('student_id', (int) $student['id'])->first()) {
            QueryBuilder::table('student_enrollments')->insert([
                'student_id' => (int) $student['id'], 'academic_year_id' => $yearId,
                'class_id' => (int) $student['class_id'], 'section_id' => (int) $student['section_id'],
                'roll_no' => 1, 'status' => 'active',
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }

        // Demo teacher assignment: Math for the demo student's class/section.
        $subject = QueryBuilder::table('subjects')->where('code', 'MATH')->first();
        if ($yearId && $teacher && $subject && $student && (int) ($student['class_id'] ?? 0) && (int) ($student['section_id'] ?? 0)
            && !QueryBuilder::table('teacher_assignments')
                ->where('academic_year_id', $yearId)->where('class_id', (int) $student['class_id'])
                ->where('section_id', (int) $student['section_id'])->where('subject_id', (int) $subject['id'])
                ->where('staff_id', (int) $teacher['id'])->first()) {
            QueryBuilder::table('teacher_assignments')->insert([
                'academic_year_id' => $yearId, 'class_id' => (int) $student['class_id'],
                'section_id' => (int) $student['section_id'], 'subject_id' => (int) $subject['id'],
                'staff_id' => (int) $teacher['id'], 'role' => 'subject_teacher',
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            $demoAssignmentId = (int) (QueryBuilder::table('teacher_assignments')
                ->where('academic_year_id', $yearId)->where('class_id', (int) $student['class_id'])
                ->where('section_id', (int) $student['section_id'])->where('subject_id', (int) $subject['id'])
                ->where('staff_id', (int) $teacher['id'])->value('id') ?? 0);
            if ($demoAssignmentId && !QueryBuilder::table('teacher_assignment_history')->where('assignment_id', $demoAssignmentId)->first()) {
                QueryBuilder::table('teacher_assignment_history')->insert([
                    'assignment_id' => $demoAssignmentId, 'staff_id' => (int) $teacher['id'], 'role' => 'subject_teacher',
                    'start_date' => date('Y-m-d'), 'status' => 'active', 'created_at' => $this->now(),
                ]);
            }
            QueryBuilder::table('class_teachers')->insert([
                'academic_year_id' => $yearId, 'class_id' => (int) $student['class_id'],
                'section_id' => (int) $student['section_id'], 'staff_id' => (int) $teacher['id'],
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);

            // Allocate the core subjects to the demo class (configure once, reuse).
            foreach (QueryBuilder::table('subjects')->whereIn('code', ['MATH', 'ENG', 'SCI'])->get() as $sub) {
                if (!QueryBuilder::table('class_subjects')->where('class_id', (int) $student['class_id'])->where('subject_id', (int) $sub['id'])->first()) {
                    $csId = QueryBuilder::table('class_subjects')->insert([
                        'class_id' => (int) $student['class_id'], 'subject_id' => (int) $sub['id'],
                        'full_marks' => 100, 'pass_marks' => 33, 'status' => 'active',
                        'created_at' => $this->now(), 'updated_at' => $this->now(),
                    ]);
                    QueryBuilder::table('class_subjects')->where('id', $csId)->update(['public_id' => sprintf('CSA-%04d', $csId)]);
                }
            }
        }
    }
}
