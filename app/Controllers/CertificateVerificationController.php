<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CertificateService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Public certificate verification (no authentication). A QR code on each
 * certificate points here; only non-sensitive authenticity fields are shown.
 */
final class CertificateVerificationController extends Controller
{
    public function show(Request $request, string $code): Response
    {
        $data = (new CertificateService())->verify($code);
        return $this->view('verify.certificate', [
            'title'  => 'Certificate Verification',
            'code'   => $code,
            'result' => $data,
        ]);
    }
}
