<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Section;
use App\Models\Subject;
use App\Services\AuditLogger;
use App\Services\IdSequenceService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Academic structure management: years, classes, sections, subjects.
 */
final class AcademicController extends Controller
{
    // ---- Academic years ---------------------------------------------------

    public function years(Request $request): Response
    {
        return $this->view('admin.academic.years', [
            'title' => 'Academic Years',
            'years' => AcademicYear::query()->orderBy('start_date', 'DESC')->get(),
        ]);
    }

    public function storeYear(Request $request): Response
    {
        $data = $this->validate($request, [
            'name'       => 'required|string|max:50|unique:academic_years,name',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        $id = AcademicYear::create([
            'name' => $data['name'], 'start_date' => $data['start_date'],
            'end_date' => $data['end_date'], 'is_current' => 0,
        ]);
        AcademicYear::updateById($id, ['public_id' => 'AY-' . date('Y', strtotime($data['start_date'])) . '-' . date('y', strtotime($data['end_date'])), 'uid' => (new IdSequenceService())->uidYear()]);

        if ($request->boolean('is_current')) {
            AcademicYear::makeCurrent($id);
        }

        AuditLogger::log('academic.year_created', 'academic_year', $id, 'Created year ' . $data['name']);
        return $this->redirect('/admin/academic/years', ['success' => 'Academic year added.']);
    }

    // ---- Classes ----------------------------------------------------------

    public function classes(Request $request): Response
    {
        return $this->view('admin.academic.classes', [
            'title'   => 'Classes',
            'classes' => ClassModel::ordered(),
        ]);
    }

    public function storeClass(Request $request): Response
    {
        $data = $this->validate($request, [
            'name'          => 'required|string|max:80|unique:classes,name',
            'numeric_level' => 'required|integer',
        ]);

        $id = ClassModel::create($data + ['uses_sections' => $request->boolean('uses_sections') ? 1 : 0]);
        ClassModel::updateById($id, ['public_id' => sprintf('CLS-%04d', $id), 'uid' => (new IdSequenceService())->uidClass()]);
        AuditLogger::log('academic.class_created', 'class', $id, 'Created class ' . $data['name']);
        return $this->redirect('/admin/academic/classes', ['success' => 'Class added.']);
    }

    // ---- Sections ---------------------------------------------------------

    public function sections(Request $request): Response
    {
        return $this->view('admin.academic.sections', [
            'title'    => 'Sections',
            'sections' => Section::withClass(),
            'classes'  => ClassModel::ordered(),
        ]);
    }

    public function storeSection(Request $request): Response
    {
        $data = $this->validate($request, [
            'class_id' => 'required|integer|exists:classes,id',
            'name'     => 'required|string|max:50',
            'capacity' => 'nullable|integer',
        ]);

        // Application-level duplicate guard (DB UNIQUE(class_id,name) is the backstop).
        if (QueryBuilder::table('sections')->where('class_id', (int) $data['class_id'])->where('name', $data['name'])->exists()) {
            return $this->redirect('/admin/academic/sections', ['error' => 'A section with that name already exists for this class.']);
        }

        try {
            $id = Section::create([
                'class_id' => (int) $data['class_id'],
                'name'     => $data['name'],
                'capacity' => (int) ($data['capacity'] ?? 40),
            ]);
        } catch (\Throwable) {
            return $this->redirect('/admin/academic/sections', ['error' => 'Duplicate section prevented by the database.']);
        }
        Section::updateById($id, ['public_id' => sprintf('SEC-%04d', $id), 'uid' => (new IdSequenceService())->uidSection()]);
        AuditLogger::log('academic.section_created', 'section', $id, 'Created section');
        return $this->redirect('/admin/academic/sections', ['success' => 'Section added.']);
    }

    public function updateSection(Request $request, string $id): Response
    {
        $section = Section::find((int) $id);
        if ($section === null) {
            abort(404, 'Section not found.');
        }
        $data = $this->validate($request, [
            'name'     => 'required|string|max:50',
            'capacity' => 'nullable|integer',
            'status'   => 'required|in:active,archived',
        ]);
        // Duplicate guard: another section in the same class with the same name.
        $dup = QueryBuilder::table('sections')
            ->where('class_id', (int) $section['class_id'])->where('name', $data['name'])
            ->whereRaw('`id` != ?', [(int) $id])->exists();
        if ($dup) {
            return $this->redirect('/admin/academic/sections', ['error' => 'Another section with that name already exists for this class.']);
        }
        Section::updateById((int) $id, [
            'name' => $data['name'], 'capacity' => (int) ($data['capacity'] ?? 40), 'status' => $data['status'],
        ]);
        AuditLogger::log('academic.section_updated', 'section', (int) $id, 'Updated section ' . $data['name']);
        return $this->redirect('/admin/academic/sections', ['success' => 'Section updated.']);
    }

    /** Toggle a section between active and archived (history-safe; never a delete). */
    public function archiveSection(Request $request, string $id): Response
    {
        $section = Section::find((int) $id);
        if ($section === null) {
            abort(404, 'Section not found.');
        }
        $new = ($section['status'] ?? 'active') === 'archived' ? 'active' : 'archived';
        Section::updateById((int) $id, ['status' => $new]);
        AuditLogger::log('academic.section_' . $new, 'section', (int) $id, ucfirst($new) . ' section');
        return $this->redirect('/admin/academic/sections', ['success' => 'Section ' . $new . '.']);
    }

    // ---- Subjects ---------------------------------------------------------

    public function subjects(Request $request): Response
    {
        return $this->view('admin.academic.subjects', [
            'title'    => 'Subjects',
            'subjects' => Subject::query()->orderBy('name')->get(),
        ]);
    }

    public function storeSubject(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:30|unique:subjects,code',
            'type' => 'required|in:theory,practical,both',
        ]);

        $id = Subject::create($data);
        Subject::updateById($id, ['public_id' => sprintf('SUB-%04d', $id), 'uid' => (new IdSequenceService())->uidSubject()]);
        AuditLogger::log('academic.subject_created', 'subject', $id, 'Created subject ' . $data['name']);
        return $this->redirect('/admin/academic/subjects', ['success' => 'Subject added.']);
    }

    // ---- Edit / archive (history-safe) ------------------------------------

    public function updateClass(Request $request, string $id): Response
    {
        if (ClassModel::find((int) $id) === null) {
            abort(404, 'Class not found.');
        }
        $data = $this->validate($request, [
            'name'          => 'required|string|max:80',
            'numeric_level' => 'required|integer',
            'status'        => 'required|in:active,archived',
        ]);
        ClassModel::updateById((int) $id, [
            'name' => $data['name'], 'code' => $request->input('code'),
            'numeric_level' => (int) $data['numeric_level'],
            'uses_sections' => $request->boolean('uses_sections') ? 1 : 0,
            'description' => $request->input('description'), 'status' => $data['status'],
        ]);
        AuditLogger::log('academic.class_updated', 'class', (int) $id, 'Updated class ' . $data['name']);
        return $this->redirect('/admin/academic/classes/' . $id, ['success' => 'Class updated.']);
    }

    /** Toggle a class between active and archived (never physically deleted). */
    public function archiveClass(Request $request, string $id): Response
    {
        $class = ClassModel::find((int) $id);
        if ($class === null) {
            abort(404, 'Class not found.');
        }
        $new = ($class['status'] ?? 'active') === 'archived' ? 'active' : 'archived';
        ClassModel::updateById((int) $id, ['status' => $new]);
        AuditLogger::log('academic.class_' . $new, 'class', (int) $id, ucfirst($new) . ' class');
        return $this->redirect('/admin/academic/classes/' . $id, ['success' => 'Class ' . $new . '.']);
    }

    public function updateSubject(Request $request, string $id): Response
    {
        if (Subject::find((int) $id) === null) {
            abort(404, 'Subject not found.');
        }
        $data = $this->validate($request, [
            'name'   => 'required|string|max:120',
            'code'   => 'required|string|max:30',
            'type'   => 'required|in:theory,practical,both',
            'status' => 'required|in:active,archived',
        ]);
        Subject::updateById((int) $id, [
            'name' => $data['name'], 'code' => $data['code'], 'type' => $data['type'],
            'max_marks' => (int) $request->input('max_marks', 100),
            'pass_marks' => (int) $request->input('pass_marks', 33),
            'description' => $request->input('description'), 'status' => $data['status'],
        ]);
        AuditLogger::log('academic.subject_updated', 'subject', (int) $id, 'Updated subject ' . $data['name']);
        return $this->redirect('/admin/academic/subjects', ['success' => 'Subject updated.']);
    }

    public function archiveSubject(Request $request, string $id): Response
    {
        $subject = Subject::find((int) $id);
        if ($subject === null) {
            abort(404, 'Subject not found.');
        }
        $new = ($subject['status'] ?? 'active') === 'archived' ? 'active' : 'archived';
        Subject::updateById((int) $id, ['status' => $new]);
        AuditLogger::log('academic.subject_' . $new, 'subject', (int) $id, ucfirst($new) . ' subject');
        return $this->redirect('/admin/academic/subjects', ['success' => 'Subject ' . $new . '.']);
    }

    /** Class management dashboard: overview of one class + edit/archive. */
    public function classDashboard(Request $request, string $id): Response
    {
        $class = ClassModel::find((int) $id);
        if ($class === null) {
            abort(404, 'Class not found.');
        }
        $yearId = (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);
        $sections = QueryBuilder::table('sections')->where('class_id', (int) $id)->orderBy('name')->get();
        $subjects = QueryBuilder::table('class_subjects')
            ->select(['subjects.id', 'subjects.name', 'subjects.code'])
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->where('class_subjects.class_id', (int) $id)->get();
        $teachers = QueryBuilder::table('teacher_assignments')
            ->selectRaw('DISTINCT `staff`.`id` AS id, CONCAT(`staff`.`first_name`, \' \', `staff`.`last_name`) AS name, `subjects`.`name` AS subject_name, `sections`.`name` AS section_name')
            ->join('staff', 'teacher_assignments.staff_id', '=', 'staff.id')
            ->leftJoin('subjects', 'teacher_assignments.subject_id', '=', 'subjects.id')
            ->leftJoin('sections', 'teacher_assignments.section_id', '=', 'sections.id')
            ->where('teacher_assignments.class_id', (int) $id)->where('teacher_assignments.academic_year_id', $yearId)->get();
        $studentCount = QueryBuilder::table('students')->where('class_id', (int) $id)->whereNull('deleted_at')->count();

        return $this->view('admin.academic.class-dashboard', [
            'title' => 'Class · ' . $class['name'],
            'class' => $class,
            'sections' => $sections,
            'subjects' => $subjects,
            'teachers' => $teachers,
            'studentCount' => $studentCount,
            'dependencies' => (new \App\Services\DependencyService())->forClass((int) $id),
        ]);
    }

    // ---- Academic year edit / activate / archive --------------------------

    public function updateYear(Request $request, string $id): Response
    {
        if (AcademicYear::find((int) $id) === null) {
            abort(404, 'Academic year not found.');
        }
        $data = $this->validate($request, [
            'name'       => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
            'status'     => 'required|in:active,archived',
        ]);
        AcademicYear::updateById((int) $id, [
            'name' => $data['name'], 'start_date' => $data['start_date'],
            'end_date' => $data['end_date'], 'status' => $data['status'],
        ]);
        AuditLogger::log('academic.year_updated', 'academic_year', (int) $id, 'Updated year ' . $data['name']);
        return $this->redirect('/admin/academic/years', ['success' => 'Academic year updated.']);
    }

    /** Make exactly one academic year current (unsets the others). */
    public function activateYear(Request $request, string $id): Response
    {
        $year = AcademicYear::find((int) $id);
        if ($year === null) {
            abort(404, 'Academic year not found.');
        }
        AcademicYear::makeCurrent((int) $id);
        AcademicYear::updateById((int) $id, ['status' => 'active']);
        AuditLogger::log('academic.year_activated', 'academic_year', (int) $id, 'Activated year ' . ($year['name'] ?? ''));
        return $this->redirect('/admin/academic/years', ['success' => 'Academic year activated as current.']);
    }

    public function archiveYear(Request $request, string $id): Response
    {
        $year = AcademicYear::find((int) $id);
        if ($year === null) {
            abort(404, 'Academic year not found.');
        }
        if ((int) ($year['is_current'] ?? 0) === 1) {
            return $this->redirect('/admin/academic/years', ['error' => 'Cannot archive the current active year. Activate another year first.']);
        }
        $new = ($year['status'] ?? 'active') === 'archived' ? 'active' : 'archived';
        AcademicYear::updateById((int) $id, ['status' => $new]);
        AuditLogger::log('academic.year_' . $new, 'academic_year', (int) $id, ucfirst($new) . ' year');
        return $this->redirect('/admin/academic/years', ['success' => 'Academic year ' . $new . '.']);
    }

    // ---- Dependency-aware safe delete (archive is the default; hard delete only
    //      when zero dependents so historical data is never destroyed) ---------

    public function deleteClass(Request $request, string $id): Response
    {
        $class = ClassModel::find((int) $id);
        if ($class === null) {
            abort(404, 'Class not found.');
        }
        $svc = new \App\Services\DependencyService();
        $dep = $svc->forClass((int) $id);
        if ($dep['total'] > 0) {
            return $this->redirect('/admin/academic/classes/' . $id, ['error' => 'Cannot delete this class — it has ' . $svc->summary($dep) . '. Archive it instead.']);
        }
        ClassModel::deleteById((int) $id);
        AuditLogger::log('academic.class_deleted', 'class', (int) $id, 'Deleted class ' . ($class['name'] ?? ''));
        return $this->redirect('/admin/academic/classes', ['success' => 'Class deleted (no dependent records).']);
    }

    public function deleteSection(Request $request, string $id): Response
    {
        $section = Section::find((int) $id);
        if ($section === null) {
            abort(404, 'Section not found.');
        }
        $svc = new \App\Services\DependencyService();
        $dep = $svc->forSection((int) $id);
        if ($dep['total'] > 0) {
            return $this->redirect('/admin/academic/sections', ['error' => 'Cannot delete this section — it has ' . $svc->summary($dep) . '. Archive it instead.']);
        }
        Section::deleteById((int) $id);
        AuditLogger::log('academic.section_deleted', 'section', (int) $id, 'Deleted section');
        return $this->redirect('/admin/academic/sections', ['success' => 'Section deleted (no dependent records).']);
    }

    public function deleteSubject(Request $request, string $id): Response
    {
        $subject = Subject::find((int) $id);
        if ($subject === null) {
            abort(404, 'Subject not found.');
        }
        $svc = new \App\Services\DependencyService();
        $dep = $svc->forSubject((int) $id);
        if ($dep['total'] > 0) {
            return $this->redirect('/admin/academic/subjects', ['error' => 'Cannot delete this subject — it has ' . $svc->summary($dep) . '. Archive it instead.']);
        }
        Subject::deleteById((int) $id);
        AuditLogger::log('academic.subject_deleted', 'subject', (int) $id, 'Deleted subject ' . ($subject['name'] ?? ''));
        return $this->redirect('/admin/academic/subjects', ['success' => 'Subject deleted (no dependent records).']);
    }

    public function deleteYear(Request $request, string $id): Response
    {
        $year = AcademicYear::find((int) $id);
        if ($year === null) {
            abort(404, 'Academic year not found.');
        }
        if ((int) ($year['is_current'] ?? 0) === 1) {
            return $this->redirect('/admin/academic/years', ['error' => 'Cannot delete the current academic year. Activate another year first.']);
        }
        $svc = new \App\Services\DependencyService();
        $dep = $svc->forYear((int) $id);
        if ($dep['total'] > 0) {
            return $this->redirect('/admin/academic/years', ['error' => 'Cannot delete this academic year — it has ' . $svc->summary($dep) . '. Archive it instead.']);
        }
        AcademicYear::deleteById((int) $id);
        AuditLogger::log('academic.year_deleted', 'academic_year', (int) $id, 'Deleted academic year ' . ($year['name'] ?? ''));
        return $this->redirect('/admin/academic/years', ['success' => 'Academic year deleted (no dependent records).']);
    }

    /** Copy a previous academic year's setup (teacher assignments + class teachers). */
    public function copyYearSetup(Request $request): Response
    {
        $data = $this->validate($request, [
            'from_year_id' => 'required|integer',
            'to_year_id'   => 'required|integer',
        ]);
        $result = (new \App\Services\AcademicYearService())->copySetup(
            (int) $data['from_year_id'], (int) $data['to_year_id'], $request->boolean('copy_teachers')
        );
        return $this->redirect('/admin/academic/years', [$result['ok'] ? 'success' : 'error' => $result['message']]);
    }

    // ---- Class-subject allocation ("configure once, reuse") ---------------
    public function classSubjects(Request $request): Response
    {
        $classId = (int) $request->query('class_id', 0);
        $yearId = (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);
        $allocations = [];
        if ($classId) {
            $allocations = QueryBuilder::table('class_subjects')
                ->select(['class_subjects.*', 'subjects.name AS subject_name', 'subjects.code AS subject_code'])
                ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
                ->where('class_subjects.class_id', $classId)
                ->orderBy('subjects.name')->get();
        }
        return $this->view('admin.academic.class-subjects', [
            'title'       => 'Class Subjects',
            'classes'     => ClassModel::ordered(),
            'subjects'    => Subject::query()->where('status', 'active')->orderBy('name')->get(),
            'allocations' => $allocations,
            'selected'    => ['class_id' => $classId],
            'yearId'      => $yearId,
        ]);
    }

    public function storeClassSubject(Request $request): Response
    {
        $data = $this->validate($request, [
            'class_id'   => 'required|integer|exists:classes,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'full_marks' => 'nullable|integer',
            'pass_marks' => 'nullable|integer',
        ]);
        $classId = (int) $data['class_id'];
        $subjectId = (int) $data['subject_id'];

        // Application-level duplicate guard (DB UNIQUE(class_id,subject_id) is the backstop).
        if (QueryBuilder::table('class_subjects')->where('class_id', $classId)->where('subject_id', $subjectId)->exists()) {
            return $this->redirect('/admin/academic/class-subjects?class_id=' . $classId, ['error' => 'That subject is already allocated to this class.']);
        }
        try {
            $csId = QueryBuilder::table('class_subjects')->insert([
                'class_id' => $classId, 'subject_id' => $subjectId,
                'full_marks' => (int) ($data['full_marks'] ?? 100), 'pass_marks' => (int) ($data['pass_marks'] ?? 33),
                'status' => 'active', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            QueryBuilder::table('class_subjects')->where('id', $csId)->update(['public_id' => sprintf('CSA-%04d', $csId)]);
        } catch (\Throwable) {
            return $this->redirect('/admin/academic/class-subjects?class_id=' . $classId, ['error' => 'Duplicate allocation prevented by the database.']);
        }
        AuditLogger::log('academic.class_subject_allocated', 'class_subject', $csId, "Allocated subject {$subjectId} to class {$classId}");
        return $this->redirect('/admin/academic/class-subjects?class_id=' . $classId, ['success' => 'Subject allocated to class.']);
    }

    public function removeClassSubject(Request $request, string $id): Response
    {
        $row = QueryBuilder::table('class_subjects')->where('id', (int) $id)->first();
        $classId = (int) ($row['class_id'] ?? 0);
        if ($row !== null) {
            QueryBuilder::table('class_subjects')->where('id', (int) $id)->delete();
            AuditLogger::log('academic.class_subject_removed', 'class_subject', (int) $id, 'Removed class-subject allocation');
        }
        return $this->redirect('/admin/academic/class-subjects?class_id=' . $classId, ['success' => 'Allocation removed.']);
    }

    // ---- Cascading-select options (JSON) ----------------------------------
    /**
     * Context-aware dependent dropdown data. Returns { data: [{id,name}, ...] }.
     * type=sections&class_id=..            -> sections in a class
     * type=subjects&class_id=..            -> subjects allocated to a class
     * type=teachers&class_id&section_id&subject_id -> teachers assigned there
     */
    public function options(Request $request): Response
    {
        $type = (string) $request->query('type', '');
        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0);
        $subjectId = (int) $request->query('subject_id', 0);
        $data = [];

        try {
            switch ($type) {
                case 'class-config':
                    $cls = QueryBuilder::table('classes')->select(['id', 'name', 'uses_sections'])->where('id', $classId)->first();
                    return $this->json(['uses_sections' => (int) ($cls['uses_sections'] ?? 1)]);

                case 'sections':
                    $data = QueryBuilder::table('sections')
                        ->select(['id', 'name'])->where('class_id', $classId)
                        ->orderBy('name')->get();
                    break;

                case 'subjects':
                    $rows = QueryBuilder::table('class_subjects')
                        ->select(['subjects.id AS id', 'subjects.name AS name'])
                        ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
                        ->where('class_subjects.class_id', $classId)
                        ->orderBy('subjects.name')->get();
                    // Fall back to all subjects if the class has no allocation yet.
                    $data = $rows !== [] ? $rows : QueryBuilder::table('subjects')->select(['id', 'name'])->orderBy('name')->get();
                    break;

                case 'teachers':
                    $yearId = (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);
                    $q = QueryBuilder::table('teacher_assignments')
                        ->selectRaw('DISTINCT `staff`.`id` AS id, CONCAT(`staff`.`first_name`, \' \', `staff`.`last_name`) AS name')
                        ->join('staff', 'teacher_assignments.staff_id', '=', 'staff.id')
                        ->where('teacher_assignments.academic_year_id', $yearId)
                        ->where('teacher_assignments.class_id', $classId)
                        ->where('teacher_assignments.section_id', $sectionId);
                    if ($subjectId) {
                        $q->where('teacher_assignments.subject_id', $subjectId);
                    }
                    $data = $q->get();
                    break;
            }
        } catch (\Throwable $e) {
            return $this->json(['data' => [], 'error' => 'Could not load options.'], 200);
        }

        return $this->json(['data' => $data]);
    }
}
