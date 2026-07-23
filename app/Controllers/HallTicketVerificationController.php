<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\HallTicketService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Public hall ticket verification (no authentication). Shows only non-sensitive
 * authenticity fields.
 */
final class HallTicketVerificationController extends Controller
{
    public function show(Request $request, string $code): Response
    {
        return $this->view('verify.hall-ticket', [
            'title'  => 'Hall Ticket Verification',
            'code'   => $code,
            'result' => (new HallTicketService())->verify($code),
        ]);
    }
}
