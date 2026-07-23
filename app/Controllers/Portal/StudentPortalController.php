<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Models\Guardian;
use App\Models\StudentEnrollment;
use App\Services\AttendanceService;
use App\Services\CertificatePdfService;
use App\Services\CertificateRequestService;
use App\Services\CertificateService;
use App\Services\DocumentService;
use App\Services\HallTicketPdfService;
use App\Services\HallTicketService;
use App\Services\ReportCardDocumentService;
use App\Services\ReportCardPdfService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;
use Core\Security\Crypto;

/**
 * Student portal — self-service views for the authenticated student.
 * All attendance figures come from the single AttendanceService so the
 * dashboard, profile and attendance page always agree.
 */
final class StudentPortalController extends Controller
{
    /** @return array<string,mixed>|null */
    private function currentStudent(): ?array
    {
        try {
            return QueryBuilder::table('students')
                ->select([
                    'students.*',
                    'classes.name AS class_name', 'classes.uses_sections',
                    'sections.name AS section_name',
                    'academic_years.name AS year_name',
                ])
                ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
                ->leftJoin('sections', 'students.section_id', '=', 'sections.id')
                ->leftJoin('academic_years', 'students.academic_year_id', '=', 'academic_years.id')
                ->where('students.user_id', auth()->id())
                ->whereNull('students.deleted_at')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Resolve the selected academic year (query ?year=ID, validated) or current. */
    private function selectedYearId(Request $request): int
    {
        $req = (int) $request->query('year', 0);
        if ($req && QueryBuilder::table('academic_years')->where('id', $req)->exists()) {
            return $req;
        }
        return (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);
    }

    /** Years the student has been enrolled in (for the year selector). */
    private function availableYears(int $studentId): array
    {
        try {
            return QueryBuilder::table('student_enrollments')
                ->select(['academic_years.id', 'academic_years.name'])
                ->join('academic_years', 'student_enrollments.academic_year_id', '=', 'academic_years.id')
                ->where('student_enrollments.student_id', $studentId)
                ->orderBy('academic_years.start_date', 'DESC')->get();
        } catch (\Throwable) {
            return [];
        }
    }

    // ===================================================================
    // Dashboard
    // ===================================================================
    public function dashboard(Request $request): Response
    {
        $student = $this->currentStudent();
        $studentId = (int) ($student['id'] ?? 0);
        $yearId = $this->selectedYearId($request);

        $attendance = (new AttendanceService())->getStudentAttendanceSummary($studentId, $yearId);

        $feesDue = 0.0;
        $upcomingExams = [];
        $recentMarks = [];
        try {
            if ($studentId) {
                $feesDue = (float) (QueryBuilder::table('fee_invoices')
                    ->where('student_id', $studentId)
                    ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                    ->selectRaw('COALESCE(SUM(total_amount + fine_amount - discount_amount - paid_amount),0) AS due')
                    ->value('due') ?? 0);
                $upcomingExams = QueryBuilder::table('exams')
                    ->whereRaw('(`start_date` IS NULL OR `start_date` >= ?)', [date('Y-m-d')])
                    ->orderBy('start_date')->limit(5)->get();
                $recentMarks = QueryBuilder::table('report_cards')
                    ->select(['report_cards.*', 'exams.name AS exam_name'])
                    ->leftJoin('exams', 'report_cards.exam_id', '=', 'exams.id')
                    ->where('report_cards.student_id', $studentId)
                    ->orderBy('report_cards.id', 'DESC')->limit(5)->get();
            }
        } catch (\Throwable) {
        }

        return $this->view('portal.student.dashboard', [
            'title'         => 'Student Dashboard',
            'student'       => $student,
            'attendance'    => $attendance,
            'feesDue'       => $feesDue,
            'upcomingExams' => $upcomingExams,
            'recentMarks'   => $recentMarks,
            'docCompletion' => $studentId ? (new DocumentService())->completion($studentId) : ['percent' => 0, 'missing' => []],
            'years'         => $this->availableYears($studentId),
            'selectedYear'  => $yearId,
        ]);
    }

    // ===================================================================
    // My Profile — complete information center
    // ===================================================================
    public function profile(Request $request): Response
    {
        $student = $this->currentStudent();
        $studentId = (int) ($student['id'] ?? 0);
        $yearId = $this->selectedYearId($request);

        $attendance = (new AttendanceService())->getStudentAttendanceSummary($studentId, $yearId);
        $subjectAttendance = (new AttendanceService())->getStudentSubjectAttendance($studentId, $yearId);

        $userUid = $student && !empty($student['user_id'])
            ? (string) (QueryBuilder::table('users')->where('id', (int) $student['user_id'])->value('uid') ?? '')
            : '';

        $documents = [];
        try {
            $documents = $studentId ? QueryBuilder::table('student_documents')->where('student_id', $studentId)->orderBy('id', 'DESC')->get() : [];
        } catch (\Throwable) {
        }

        $aadhaarMasked = !empty($student['aadhaar_last4']) ? 'XXXX-XXXX-' . $student['aadhaar_last4'] : '—';

        return $this->view('portal.student.profile', [
            'title'             => 'My Profile',
            'student'           => $student,
            'userUid'           => $userUid,
            'guardians'         => $studentId ? Guardian::forStudent($studentId) : [],
            'enrollments'       => $studentId ? StudentEnrollment::history($studentId) : [],
            'attendance'        => $attendance,
            'subjectAttendance' => $subjectAttendance,
            'documents'         => $documents,
            'documentTypes'     => DocumentService::TYPES,
            'aadhaarMasked'     => $aadhaarMasked,
            'completion'        => $studentId ? (new DocumentService())->completion($studentId) : ['percent' => 0, 'missing' => []],
            'years'             => $this->availableYears($studentId),
            'selectedYear'      => $yearId,
        ]);
    }

    // ===================================================================
    // Attendance page
    // ===================================================================
    public function attendance(Request $request): Response
    {
        $student = $this->currentStudent();
        $studentId = (int) ($student['id'] ?? 0);
        $yearId = $this->selectedYearId($request);
        $svc = new AttendanceService();

        return $this->view('portal.student.attendance', [
            'title'      => 'My Attendance',
            'student'    => $student,
            'summary'    => $svc->getStudentAttendanceSummary($studentId, $yearId),
            'monthly'    => $svc->getStudentMonthlyAttendance($studentId, $yearId),
            'subjects'   => $svc->getStudentSubjectAttendance($studentId, $yearId),
            'records'    => $svc->getStudentAttendanceHistory($studentId, $yearId),
            'years'      => $this->availableYears($studentId),
            'selectedYear' => $yearId,
        ]);
    }

    // ===================================================================
    // My Documents (self-service, own-only)
    // ===================================================================
    public function documents(Request $request): Response
    {
        $student = $this->currentStudent();
        $studentId = (int) ($student['id'] ?? 0);
        $documents = [];
        try {
            $documents = $studentId ? QueryBuilder::table('student_documents')->where('student_id', $studentId)->orderBy('id', 'DESC')->get() : [];
        } catch (\Throwable) {
        }
        return $this->view('portal.student.documents', [
            'title'         => 'My Documents',
            'student'       => $student,
            'documents'     => $documents,
            'documentTypes' => DocumentService::TYPES,
            'completion'    => $studentId ? (new DocumentService())->completion($studentId) : ['percent' => 0, 'missing' => []],
        ]);
    }

    public function uploadDocument(Request $request): Response
    {
        $student = $this->currentStudent();
        if ($student === null) {
            abort(403, 'No student profile is linked to your account.');
        }
        $file = $request->file('document');
        if ($file === null) {
            return $this->redirect('/student/documents', ['error' => 'Please choose a file to upload.']);
        }
        $res = (new DocumentService())->store(
            (int) $student['id'], $file, (string) $request->input('title', ''), (string) $request->input('type', 'other'), auth()->id()
        );
        return $this->redirect('/student/documents', [$res['ok'] ? 'success' : 'error' => $res['message']]);
    }

    /** Stream one of the student's OWN documents (server-side ownership check). */
    public function downloadDocument(Request $request, string $docId): Response
    {
        $student = $this->currentStudent();
        if ($student === null) {
            abort(403);
        }
        $svc = new DocumentService();
        $doc = $svc->find((int) $docId);
        if ($doc === null || (int) $doc['student_id'] !== (int) $student['id']) {
            abort(404, 'Document not found.');
        }
        $path = $svc->absolutePath($doc);
        if ($path === null) {
            abort(404, 'File is missing.');
        }
        \App\Services\AuditLogger::log('document.downloaded', 'student_document', (int) $docId, 'Student downloaded own document');
        return (new Response((string) file_get_contents($path), 200))
            ->header('Content-Type', (string) ($doc['mime_type'] ?? 'application/octet-stream'))
            ->header('Content-Disposition', 'inline; filename="' . basename((string) $doc['file_path']) . '"')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    // ===================================================================
    public function results(Request $request): Response
    {
        $student = $this->currentStudent();
        $results = [];
        try {
            if ($student) {
                $results = QueryBuilder::table('report_cards')
                    ->select(['report_cards.*', 'exams.name AS exam_name'])
                    ->leftJoin('exams', 'report_cards.exam_id', '=', 'exams.id')
                    ->where('report_cards.student_id', (int) $student['id'])
                    ->orderBy('report_cards.id', 'DESC')->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.student.results', ['title' => 'My Results', 'results' => $results]);
    }

    // ===================================================================
    // Report cards — the student may only ever access their OWN cards
    // ===================================================================
    public function reportCards(Request $request): Response
    {
        $student = $this->currentStudent();
        $sid = (int) ($student['id'] ?? 0);
        $cards = [];
        if ($sid) {
            $cards = QueryBuilder::table('report_cards')
                ->select(['report_cards.*', 'exams.name AS exam_name', 'academic_years.name AS year_name'])
                ->leftJoin('exams', 'report_cards.exam_id', '=', 'exams.id')
                ->leftJoin('academic_years', 'exams.academic_year_id', '=', 'academic_years.id')
                ->where('report_cards.student_id', $sid)
                ->orderBy('report_cards.id', 'DESC')->get();
        }
        return $this->view('portal.student.report-cards', ['title' => 'My Report Cards', 'cards' => $cards]);
    }

    /** Resolve a report card ONLY if it belongs to the authenticated student. */
    private function ownedCard(string $rcUid): ?array
    {
        $student = $this->currentStudent();
        $sid = (int) ($student['id'] ?? 0);
        if ($sid === 0) {
            return null;
        }
        return QueryBuilder::table('report_cards')->where('rc_uid', $rcUid)->where('student_id', $sid)->first();
    }

    public function reportCard(Request $request, string $code): Response
    {
        $card = $this->ownedCard($code);
        if ($card === null) {
            abort(403, 'You are not authorized to view this report card.');
        }
        $doc = (new ReportCardDocumentService())->build((int) $card['exam_id'], (int) $card['student_id']);
        if ($doc === null) {
            abort(404, 'Report card not available.');
        }
        return $this->view('report-cards.show', [
            'title' => 'Report Card', 'doc' => $doc,
            'downloadUrl' => url('/student/report-cards/' . $code . '/download'),
            'backUrl' => url('/student/report-cards'),
        ]);
    }

    public function downloadReportCard(Request $request, string $code): Response
    {
        $card = $this->ownedCard($code);
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

    // ===================================================================
    // Documents & Certificates — request workflow (own records only)
    // ===================================================================
    public function certificates(Request $request): Response
    {
        $student = $this->currentStudent();
        $sid = (int) ($student['id'] ?? 0);
        $requests = $sid ? (new CertificateRequestService())->forStudent($sid) : [];
        $issued = [];
        if ($sid) {
            $issued = QueryBuilder::table('certificates')
                ->where('student_id', $sid)->orderBy('id', 'DESC')->get();
        }
        return $this->view('portal.student.certificates', [
            'title'      => 'Documents & Certificates',
            'requests'   => $requests,
            'issued'     => $issued,
            'types'      => (new CertificateService())->types(),
            'requestable'=> CertificateRequestService::REQUESTABLE,
            'purposes'   => (new CertificateRequestService())->purposes(),
            'labels'     => CertificateRequestService::STATUS_LABELS,
        ]);
    }

    public function submitCertificateRequest(Request $request): Response
    {
        $student = $this->currentStudent();
        $sid = (int) ($student['id'] ?? 0);
        if ($sid === 0) {
            abort(403, 'No student profile.');
        }
        $type = (string) $request->input('type', '');
        $purpose = (string) $request->input('purpose', '');
        if ($purpose === 'Other') {
            $purpose = trim((string) $request->input('other_purpose', '')) ?: 'Other';
        }
        $reason = (string) $request->input('reason', '');
        $reqUid = (new CertificateRequestService())->create($sid, $type, $purpose, $reason);
        return $this->redirect('/student/certificates', $reqUid !== null
            ? ['success' => 'Request ' . $reqUid . ' submitted. You can track its status here.']
            : ['error' => 'Could not submit. You may already have a pending request for this certificate.']);
    }

    public function cancelCertificateRequest(Request $request, string $reqUid): Response
    {
        $student = $this->currentStudent();
        $ok = (new CertificateRequestService())->cancel($reqUid, (int) ($student['id'] ?? 0));
        return $this->redirect('/student/certificates', $ok
            ? ['success' => 'Request cancelled.']
            : ['error' => 'This request can no longer be cancelled.']);
    }

    /** Download a generated certificate ONLY if it belongs to this student. */
    public function downloadCertificate(Request $request, string $code): Response
    {
        $student = $this->currentStudent();
        $sid = (int) ($student['id'] ?? 0);
        $own = $sid ? QueryBuilder::table('certificates')->where('cert_uid', $code)->where('student_id', $sid)->first() : null;
        if ($own === null) {
            abort(403, 'You are not authorized to download this certificate.');
        }
        $doc = (new CertificateService())->document($code);
        if ($doc === null) {
            abort(404, 'Certificate not available.');
        }
        $pdf = (new CertificatePdfService())->render($doc);
        $disp = $request->query('inline') ? 'inline' : 'attachment';
        return (new Response($pdf, 200))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disp . '; filename="' . $code . '.pdf"');
    }

    // ===================================================================
    // Hall tickets / admit cards — own records only
    // ===================================================================
    public function hallTickets(Request $request): Response
    {
        $student = $this->currentStudent();
        $sid = (int) ($student['id'] ?? 0);
        $tickets = $sid ? (new HallTicketService())->forStudent($sid) : [];
        return $this->view('portal.student.hall-tickets', [
            'title'   => 'Hall Tickets / Admit Cards',
            'tickets' => $tickets,
            'types'   => HallTicketService::TYPES,
        ]);
    }

    private function ownedTicket(string $htUid): ?array
    {
        $student = $this->currentStudent();
        $sid = (int) ($student['id'] ?? 0);
        if ($sid === 0) {
            return null;
        }
        return QueryBuilder::table('hall_tickets')->where('ht_uid', $htUid)->where('student_id', $sid)->first();
    }

    public function hallTicket(Request $request, string $code): Response
    {
        if ($this->ownedTicket($code) === null) {
            abort(403, 'You are not authorized to view this hall ticket.');
        }
        $doc = (new HallTicketService())->document($code);
        if ($doc === null) {
            abort(404, 'Hall ticket not available.');
        }
        return $this->view('hall-tickets.show', [
            'title' => $doc['type_label'], 'doc' => $doc,
            'downloadUrl' => url('/student/hall-tickets/' . $code . '/download'),
            'backUrl' => url('/student/hall-tickets'), 'canRevoke' => false,
        ]);
    }

    public function downloadHallTicket(Request $request, string $code): Response
    {
        $own = $this->ownedTicket($code);
        if ($own === null) {
            abort(403, 'You are not authorized to download this hall ticket.');
        }
        if (($own['status'] ?? '') === 'revoked') {
            abort(403, 'This hall ticket has been revoked.');
        }
        $doc = (new HallTicketService())->document($code);
        if ($doc === null) {
            abort(404, 'Hall ticket not available.');
        }
        $pdf = (new HallTicketPdfService())->render($doc);
        $disp = $request->query('inline') ? 'inline' : 'attachment';
        return (new Response($pdf, 200))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disp . '; filename="' . $code . '.pdf"');
    }

    public function fees(Request $request): Response
    {
        $student = $this->currentStudent();
        $invoices = [];
        try {
            if ($student) {
                $invoices = QueryBuilder::table('fee_invoices')
                    ->where('student_id', (int) $student['id'])
                    ->orderBy('id', 'DESC')->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.student.fees', ['title' => 'My Fees', 'invoices' => $invoices]);
    }

    public function timetable(Request $request): Response
    {
        $student = $this->currentStudent();
        $periods = [];
        try {
            if ($student && (int) ($student['class_id'] ?? 0)) {
                $q = QueryBuilder::table('timetables')
                    ->select(['timetables.*', 'timetable_slots.name AS slot_name', 'timetable_slots.start_time', 'timetable_slots.end_time', 'subjects.name AS subject_name', 'rooms.name AS room_name', 'timetables.day_of_week'])
                    ->leftJoin('timetable_slots', 'timetables.slot_id', '=', 'timetable_slots.id')
                    ->leftJoin('subjects', 'timetables.subject_id', '=', 'subjects.id')
                    ->leftJoin('rooms', 'timetables.room_id', '=', 'rooms.id')
                    ->where('timetables.class_id', (int) $student['class_id']);
                if (!empty($student['section_id'])) {
                    $q->where('timetables.section_id', (int) $student['section_id']);
                }
                $periods = $q->orderBy('timetables.day_of_week')->orderBy('timetable_slots.sort_order')->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.student.timetable', ['title' => 'My Timetable', 'periods' => $periods]);
    }
}
