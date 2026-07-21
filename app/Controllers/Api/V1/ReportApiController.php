<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Services\ReportService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * REST API for report generation.
 */
final class ReportApiController extends Controller
{
    public function generate(Request $request, string $type): Response
    {
        $service = new ReportService();
        $report = $service->generate($type, $request->all());

        if ($report === null) {
            return $this->error('Unknown report type.', 404);
        }
        return $this->success($report, 'Report generated.');
    }
}
