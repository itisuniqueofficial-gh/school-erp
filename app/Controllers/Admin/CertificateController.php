<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\CertificatePdfService;
use App\Services\CertificateService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Digital certificate management: issue, list, view, download (PDF) and revoke
 * Bonafide / Transfer / Leaving / Character / Study / custom certificates.
 */
final class CertificateController extends Controller
{
    public function index(Request $request): Response
    {
        $svc = new CertificateService();
        $filters = [
            'type'    => (string) $request->query('type', ''),
            'status'  => (string) $request->query('status', ''),
        ];
        return $this->view('admin.certificates.index', [
            'title'        => 'Certificates',
            'certificates' => $svc->list($filters),
            'types'        => $svc->types(),
            'filters'      => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $students = QueryBuilder::table('students')
            ->select(['students.id', 'students.first_name', 'students.last_name', 'students.admission_no', 'classes.name AS class_name'])
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->whereNull('students.deleted_at')
            ->where('students.status', 'active')
            ->orderBy('students.first_name')->limit(2000)->get();
        return $this->view('admin.certificates.create', [
            'title'    => 'Issue Certificate',
            'students' => $students,
            'types'    => (new CertificateService())->types(),
        ]);
    }

    public function store(Request $request): Response
    {
        $studentId = (int) $request->input('student_id', 0);
        $type = (string) $request->input('type', '');
        if ($studentId === 0 || !isset(CertificateService::TYPES[$type])) {
            return $this->redirect('/admin/certificates/create', ['error' => 'Please select a student and a valid certificate type.']);
        }
        $certUid = (new CertificateService())->issue($studentId, $type, [
            'body'         => (string) $request->input('body', ''),
            'reference_no' => (string) $request->input('reference_no', ''),
            'issued_on'    => (string) $request->input('issued_on', ''),
            'title'        => (string) $request->input('title', ''),
        ]);
        if ($certUid === null) {
            return $this->redirect('/admin/certificates/create', ['error' => 'Could not issue the certificate. A custom certificate requires a body.']);
        }
        return $this->redirect('/admin/certificates/' . $certUid, ['success' => 'Certificate ' . $certUid . ' issued successfully.']);
    }

    public function show(Request $request, string $code): Response
    {
        $doc = (new CertificateService())->document($code);
        if ($doc === null) {
            abort(404, 'Certificate not found.');
        }
        return $this->view('certificates.show', [
            'title'       => $doc['title'],
            'doc'         => $doc,
            'downloadUrl' => url('/admin/certificates/' . $code . '/download'),
            'backUrl'     => url('/admin/certificates'),
            'canRevoke'   => auth()->can('certificates.manage'),
        ]);
    }

    public function download(Request $request, string $code): Response
    {
        $doc = (new CertificateService())->document($code);
        if ($doc === null) {
            abort(404, 'Certificate not found.');
        }
        $pdf = (new CertificatePdfService())->render($doc);
        return (new Response($pdf, 200))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $code . '.pdf"');
    }

    public function revoke(Request $request, string $code): Response
    {
        $reason = trim((string) $request->input('reason', ''));
        $ok = (new CertificateService())->revoke($code, $reason);
        return $this->redirect('/admin/certificates/' . $code, $ok
            ? ['success' => 'Certificate revoked. It remains on record and its verification page now shows "revoked".']
            : ['error' => 'Certificate could not be revoked (already revoked or not found).']);
    }
}
