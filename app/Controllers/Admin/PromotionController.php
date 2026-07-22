<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Services\PromotionService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;
use Core\Database\QueryBuilder;

/**
 * Academic Promotion & Rollover: review exam-based eligibility and promote
 * students into the next class for a new academic year (history-preserving).
 */
final class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $svc = new PromotionService();
        $years = AcademicYear::all();
        $currentId = (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);

        $fromYear = (int) $request->query('from_year_id', (string) $currentId);
        $classId  = (int) $request->query('class_id', '0') ?: null;
        $examId   = (int) $request->query('exam_id', '0') ?: null;

        $review = $fromYear ? $svc->evaluate($fromYear, $classId, $examId) : [];

        return $this->view('admin.promotions.index', [
            'title'    => 'Promotion & Rollover',
            'years'    => $years,
            'classes'  => ClassModel::ordered(),
            'exams'    => QueryBuilder::table('exams')->orderBy('id', 'DESC')->get(),
            'filters'  => ['from_year_id' => $fromYear, 'class_id' => $classId, 'exam_id' => $examId],
            'review'   => $review,
        ]);
    }

    public function apply(Request $request): Response
    {
        $fromYear = (int) $request->input('from_year_id', 0);
        $toYear   = (int) $request->input('to_year_id', 0);
        $decisionsIn = (array) $request->input('decisions', []);
        $selected = array_map('intval', (array) $request->input('student_ids', []));

        if ($fromYear === 0 || $toYear === 0) {
            return $this->back(['promotion' => ['Select both the source and target academic year.']]);
        }
        if ($fromYear === $toYear) {
            return $this->back(['promotion' => ['Target year must differ from the source year.']]);
        }
        if ($selected === []) {
            return $this->back(['promotion' => ['Select at least one student to process.']]);
        }

        // Only apply decisions for the checked students.
        $decisions = [];
        foreach ($selected as $sid) {
            $d = (array) ($decisionsIn[$sid] ?? []);
            $decisions[$sid] = [
                'action'        => (string) ($d['action'] ?? 'hold'),
                'to_class_id'   => (int) ($d['to_class_id'] ?? 0),
                'to_section_id' => (int) ($d['to_section_id'] ?? 0),
            ];
        }

        $r = (new PromotionService())->apply($fromYear, $toYear, $decisions);
        return $this->redirect('/admin/promotions', ['success' => sprintf(
            'Promotion applied: %d promoted, %d repeated, %d graduated, %d skipped, %d errors (history preserved).',
            $r['promoted'], $r['repeated'], $r['graduated'], $r['skipped'], $r['errors']
        )]);
    }
}
