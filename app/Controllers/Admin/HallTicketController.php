<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\HallTicketPdfService;
use App\Services\HallTicketService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Hall ticket / admit card management (Examinations). Reuses the exams
 * permissions: exams.view to browse/download, exams.manage to generate/revoke.
 */
final class HallTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $svc = new HallTicketService();
        $filters = [
            'exam_id' => (int) $request->query('exam_id', 0) ?: null,
            'type'    => (string) $request->query('type', ''),
            'status'  => (string) $request->query('status', ''),
        ];
        return $this->view('admin.hall-tickets.index', [
            'title'   => 'Hall Tickets / Admit Cards',
            'tickets' => $svc->list($filters),
            'types'   => $svc->types(),
            'exams'   => $this->exams(),
            'filters' => $filters,
        ]);
    }

    public function generate(Request $request): Response
    {
        $svc = new HallTicketService();
        $examId = (int) $request->query('exam_id', 0);
        $classId = (int) $request->query('class_id', 0);
        $sectionId = (int) $request->query('section_id', 0) ?: null;
        $type = (string) $request->query('type', 'hall_ticket');
        $eligible = ($examId && $classId && isset(HallTicketService::TYPES[$type]))
            ? $svc->eligibleStudents($examId, $classId, $sectionId, $type)
            : [];

        return $this->view('admin.hall-tickets.generate', [
            'title'    => 'Generate Hall Tickets',
            'types'    => $svc->types(),
            'exams'    => $this->exams(),
            'classes'  => QueryBuilder::table('classes')->select(['id', 'name', 'uses_sections'])->orderBy('numeric_level')->get(),
            'sections' => QueryBuilder::table('sections')->select(['id', 'name', 'class_id'])->get(),
            'eligible' => $eligible,
            'sel'      => ['exam_id' => $examId, 'class_id' => $classId, 'section_id' => $sectionId, 'type' => $type],
        ]);
    }

    public function store(Request $request): Response
    {
        $examId = (int) $request->input('exam_id', 0);
        $classId = (int) $request->input('class_id', 0);
        $sectionId = (int) $request->input('section_id', 0) ?: null;
        $type = (string) $request->input('type', '');
        if ($examId === 0 || $classId === 0 || !isset(HallTicketService::TYPES[$type])) {
            return $this->redirect('/admin/hall-tickets/generate', ['error' => 'Select an exam, class and valid hall ticket type.']);
        }
        $ids = $request->input('student_ids', []);
        $only = is_array($ids) && $ids !== [] ? array_map('intval', $ids) : null;
        $opts = [
            'center' => (string) $request->input('center', ''),
            'instructions' => (string) $request->input('instructions', ''),
            'signature_required' => (bool) $request->input('signature_required', false),
            'stamp_required' => (bool) $request->input('stamp_required', false),
        ];
        $r = (new HallTicketService())->issueForClass($examId, $classId, $sectionId, $type, $opts, $only);
        $q = '?exam_id=' . $examId . '&class_id=' . $classId . ($sectionId ? '&section_id=' . $sectionId : '') . '&type=' . $type;
        return $this->redirect('/admin/hall-tickets/generate' . $q, ['success' => "Issued {$r['created']} hall ticket(s); {$r['skipped']} already existed or skipped."]);
    }

    public function show(Request $request, string $code): Response
    {
        $doc = (new HallTicketService())->document($code);
        if ($doc === null) {
            abort(404, 'Hall ticket not found.');
        }
        return $this->view('hall-tickets.show', [
            'title'       => $doc['type_label'],
            'doc'         => $doc,
            'downloadUrl' => url('/admin/hall-tickets/' . $code . '/download'),
            'backUrl'     => url('/admin/hall-tickets'),
            'canRevoke'   => auth()->can('exams.manage'),
        ]);
    }

    public function download(Request $request, string $code): Response
    {
        $doc = (new HallTicketService())->document($code);
        if ($doc === null) {
            abort(404, 'Hall ticket not found.');
        }
        $pdf = (new HallTicketPdfService())->render($doc);
        $disp = $request->query('inline') ? 'inline' : 'attachment';
        return (new Response($pdf, 200))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disp . '; filename="' . $code . '.pdf"');
    }

    public function revoke(Request $request, string $code): Response
    {
        $ok = (new HallTicketService())->revoke($code, trim((string) $request->input('reason', '')));
        return $this->redirect('/admin/hall-tickets/' . $code, $ok
            ? ['success' => 'Hall ticket revoked. It remains on record and shows as revoked on verification.']
            : ['error' => 'Could not revoke (already revoked or not found).']);
    }

    /** @return array<int,array<string,mixed>> */
    private function exams(): array
    {
        return QueryBuilder::table('exams')
            ->select(['exams.id', 'exams.name', 'academic_years.name AS year_name'])
            ->leftJoin('academic_years', 'exams.academic_year_id', '=', 'academic_years.id')
            ->orderBy('exams.id', 'DESC')->limit(200)->get();
    }
}
