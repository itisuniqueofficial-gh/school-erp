<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\ReportService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Reporting: generate on-screen reports and export as CSV.
 */
final class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.reports.index', [
            'title' => 'Reports',
            'types' => (new ReportService())->types(),
        ]);
    }

    public function generate(Request $request, string $type): Response
    {
        $report = (new ReportService())->generate($type, $request->all());
        if ($report === null) {
            abort(404, 'Unknown report type.');
        }
        return $this->view('admin.reports.show', [
            'title'  => $report['title'],
            'type'   => $type,
            'report' => $report,
        ]);
    }

    public function export(Request $request, string $type): Response
    {
        $service = new ReportService();
        $report = $service->generate($type, $request->all());
        if ($report === null) {
            abort(404, 'Unknown report type.');
        }
        $csv = $service->toCsv($report);
        $filename = $type . '-report-' . date('Ymd_His') . '.csv';

        return (new Response($csv, 200))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
