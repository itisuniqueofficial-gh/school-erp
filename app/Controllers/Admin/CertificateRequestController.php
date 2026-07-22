<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\CertificateRequestService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Admin review queue for student-submitted certificate requests. Approving a
 * request auto-issues the real certificate from verified ERP data.
 */
final class CertificateRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $svc = new CertificateRequestService();
        $filters = ['status' => (string) $request->query('status', '')];
        return $this->view('admin.certificate-requests.index', [
            'title'    => 'Certificate Requests',
            'requests' => $svc->list($filters),
            'labels'   => CertificateRequestService::STATUS_LABELS,
            'filters'  => $filters,
        ]);
    }

    public function approve(Request $request, string $reqUid): Response
    {
        $note = trim((string) $request->input('note', ''));
        $certUid = (new CertificateRequestService())->approve($reqUid, $note);
        return $this->redirect('/admin/certificate-requests', $certUid !== null
            ? ['success' => 'Approved. Certificate ' . $certUid . ' has been generated from verified data.']
            : ['error' => 'Could not approve this request (already processed or invalid).']);
    }

    public function reject(Request $request, string $reqUid): Response
    {
        $note = trim((string) $request->input('note', ''));
        $ok = (new CertificateRequestService())->reject($reqUid, $note);
        return $this->redirect('/admin/certificate-requests', $ok
            ? ['success' => 'Request rejected.']
            : ['error' => 'Could not reject this request (already processed).']);
    }
}
