<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Services\ReportCardDocumentService;
use App\Services\ReportCardPdfService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Parent portal — view children, their attendance and fees.
 */
final class ParentPortalController extends Controller
{
    /** @return array<int,array<string,mixed>> */
    private function resolveChildren(): array
    {
        try {
            $guardian = QueryBuilder::table('guardians')->where('user_id', auth()->id())->first();
            if ($guardian === null) {
                return [];
            }
            return QueryBuilder::table('student_guardians')
                ->select(['students.*'])
                ->join('students', 'student_guardians.student_id', '=', 'students.id')
                ->where('student_guardians.guardian_id', (int) $guardian['id'])
                ->whereNull('students.deleted_at')
                ->get();
        } catch (\Throwable) {
            return [];
        }
    }

    public function dashboard(Request $request): Response
    {
        return $this->view('portal.parent.dashboard', [
            'title'    => 'Parent Dashboard',
            'children' => $this->resolveChildren(),
        ]);
    }

    public function children(Request $request): Response
    {
        return $this->view('portal.parent.dashboard', [
            'title'    => 'My Children',
            'children' => $this->resolveChildren(),
        ]);
    }

    public function fees(Request $request): Response
    {
        $children = $this->resolveChildren();
        $invoices = [];
        try {
            $ids = array_map(static fn ($c) => (int) $c['id'], $children);
            if ($ids !== []) {
                $invoices = QueryBuilder::table('fee_invoices')->whereIn('student_id', $ids)->orderBy('id', 'DESC')->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.parent.fees', [
            'title' => 'Children Fees', 'invoices' => $invoices,
        ]);
    }

    // ===================================================================
    // Report cards — restricted to the parent's linked children only
    // ===================================================================
    /** @return array<int,int> */
    private function childIds(): array
    {
        return array_values(array_map(static fn ($c) => (int) $c['id'], $this->resolveChildren()));
    }

    public function reportCards(Request $request): Response
    {
        $ids = $this->childIds();
        $cards = [];
        if ($ids !== []) {
            $cards = QueryBuilder::table('report_cards')
                ->select(['report_cards.*', 'exams.name AS exam_name', 'students.first_name', 'students.last_name'])
                ->leftJoin('exams', 'report_cards.exam_id', '=', 'exams.id')
                ->leftJoin('students', 'report_cards.student_id', '=', 'students.id')
                ->whereIn('report_cards.student_id', $ids)
                ->orderBy('report_cards.id', 'DESC')->get();
        }
        return $this->view('portal.parent.report-cards', ['title' => 'Children Report Cards', 'cards' => $cards]);
    }

    /** Resolve a report card ONLY if it belongs to one of the parent's children. */
    private function linkedCard(string $rcUid): ?array
    {
        $ids = $this->childIds();
        if ($ids === []) {
            return null;
        }
        return QueryBuilder::table('report_cards')->where('rc_uid', $rcUid)->whereIn('student_id', $ids)->first();
    }

    public function reportCard(Request $request, string $code): Response
    {
        $card = $this->linkedCard($code);
        if ($card === null) {
            abort(403, 'You are not authorized to view this report card.');
        }
        $doc = (new ReportCardDocumentService())->build((int) $card['exam_id'], (int) $card['student_id']);
        if ($doc === null) {
            abort(404, 'Report card not available.');
        }
        return $this->view('report-cards.show', [
            'title' => 'Report Card', 'doc' => $doc,
            'downloadUrl' => url('/parent/report-cards/' . $code . '/download'),
            'backUrl' => url('/parent/report-cards'),
        ]);
    }

    public function downloadReportCard(Request $request, string $code): Response
    {
        $card = $this->linkedCard($code);
        if ($card === null) {
            abort(403, 'You are not authorized to download this report card.');
        }
        $doc = (new ReportCardDocumentService())->build((int) $card['exam_id'], (int) $card['student_id']);
        if ($doc === null) {
            abort(404, 'Report card not available.');
        }
        $pdf = (new ReportCardPdfService())->render($doc);
        $disp = $request->query('inline') ? 'inline' : 'attachment';
        return (new Response($pdf, 200))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disp . '; filename="' . $code . '.pdf"');
    }
}
