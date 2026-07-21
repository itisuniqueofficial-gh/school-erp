<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Section;
use App\Models\Subject;
use App\Services\AuditLogger;
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

        $id = ClassModel::create($data);
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

        $id = Section::create([
            'class_id' => (int) $data['class_id'],
            'name'     => $data['name'],
            'capacity' => (int) ($data['capacity'] ?? 40),
        ]);
        AuditLogger::log('academic.section_created', 'section', $id, 'Created section');
        return $this->redirect('/admin/academic/sections', ['success' => 'Section added.']);
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
        AuditLogger::log('academic.subject_created', 'subject', $id, 'Created subject ' . $data['name']);
        return $this->redirect('/admin/academic/subjects', ['success' => 'Subject added.']);
    }
}
