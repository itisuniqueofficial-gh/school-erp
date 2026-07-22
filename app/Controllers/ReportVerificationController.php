<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReportCardService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Public report-card verification (no authentication). A QR code on each report
 * card points here; only non-sensitive fields are shown (no marks/percentage).
 */
final class ReportVerificationController extends Controller
{
    public function show(Request $request, string $code): Response
    {
        $data = (new ReportCardService())->verify($code);
        return $this->view('verify.report-card', [
            'title'  => 'Report Card Verification',
            'code'   => $code,
            'result' => $data,
        ]);
    }
}
