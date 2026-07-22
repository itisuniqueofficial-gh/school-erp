<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AttendanceService;
use App\Services\DocumentService;
use App\Services\EnrollmentService;
use App\Services\IdSequenceService;
use App\Services\NotificationService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;
use Core\Security\Crypto;
use Core\Security\Hash;

/**
 * Admin management of students: admission, profile, documents, promotion.
 */
final class StudentController extends Controller
{
    private const UPLOAD_DIR = '/storage/uploads/students';

    public function index(Request $request): Response
    {
        $filters = [
            'search'     => (string) $request->query('search', ''),
            'class_id'   => (int) $request->query('class_id', 0),
            'section_id' => (int) $request->query('section_id', 0),
            'status'     => (string) $request->query('status', ''),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $result = Student::paginate($page, (int) config('app.per_page', 15), Student::filtered($filters));

        return $this->view('admin.students.index', [
            'title'    => 'Students',
            'students' => $result['data'],
            'meta'     => $result['meta'],
            'classes'  => ClassModel::ordered(),
            'filters'  => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin.students.create', [
            'title'        => 'Admit Student',
            'classes'      => ClassModel::ordered(),
            'sections'     => Section::withClass(),
            'years'        => AcademicYear::all(),
            'admissionNo'  => Student::generateAdmissionNo(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'first_name'    => 'required|string|max:80',
            'last_name'     => 'required|string|max:80',
            'gender'        => 'required|in:male,female,other',
            'dob'           => 'nullable|date',
            'email'         => 'nullable|email|max:190',
            'phone'         => 'nullable|phone',
            'class_id'      => 'nullable|integer',
            'section_id'    => 'nullable|integer',
            'academic_year_id' => 'nullable|integer',
            'guardian_name' => 'nullable|string|max:150',
            'guardian_phone'=> 'nullable|phone',
            'create_login'  => 'nullable|boolean',
        ]);

        $photo = $this->handlePhoto($request);

        // Permanent, never-reused public identifier (STU-YYYY-NNNNN).
        $publicId = (new IdSequenceService())->student();
        $uid = (new IdSequenceService())->uidStudent();
        $govIds = $this->govIdData($request);

        try {
            $studentId = Connection::instance()->transaction(function () use ($data, $request, $photo, $publicId, $uid, $govIds): int {
                $userId = null;

                // Optionally create a login account for the student.
                if ($request->boolean('create_login') && !empty($data['email'])) {
                    $userId = User::create([
                        'name'      => $data['first_name'] . ' ' . $data['last_name'],
                        'uid'       => (new IdSequenceService())->uidUser(),
                        'email'     => $data['email'],
                        'username'  => strtolower($data['first_name']) . random_int(100, 999),
                        'password'  => Hash::make('Password123!'),
                        'is_active' => 1,
                    ]);
                    User::assignRole($userId, 'student');
                }

                $studentId = Student::create(array_merge([
                    'user_id'          => $userId,
                    'uid'              => $uid,
                    'public_id'        => $publicId,
                    'admission_no'     => Student::generateAdmissionNo(),
                    'roll_no'          => $request->input('roll_no'),
                    'first_name'       => $data['first_name'],
                    'last_name'        => $data['last_name'],
                    'gender'           => $data['gender'],
                    'dob'              => $data['dob'] ?: null,
                    'blood_group'      => $request->input('blood_group'),
                    'email'            => $data['email'] ?: null,
                    'phone'            => $data['phone'] ?: null,
                    'address'          => $request->input('address'),
                    'class_id'         => $data['class_id'] ?: null,
                    'section_id'       => $data['section_id'] ?: null,
                    'academic_year_id' => $data['academic_year_id'] ?: null,
                    'admission_date'   => date('Y-m-d'),
                    'photo'            => $photo,
                    'status'           => 'active',
                ], $govIds));

                // Optionally create + link a guardian.
                if (!empty($data['guardian_name'])) {
                    $guardianId = Guardian::create([
                        'name'  => $data['guardian_name'],
                        'phone' => $data['guardian_phone'] ?: null,
                        'relation' => 'guardian',
                    ]);
                    QueryBuilder::table('student_guardians')->insert([
                        'student_id' => $studentId, 'guardian_id' => $guardianId, 'is_primary' => 1,
                    ]);
                }

                return $studentId;
            });
        } catch (\Throwable $e) {
            logger()->error('Student creation failed: ' . $e->getMessage());
            return $this->back(['general' => ['Could not save the student. Please try again.']], $request->all());
        }

        AuditLogger::log('student.created', 'student', $studentId, 'Admitted student ' . $data['first_name']);

        // Automatic academic enrollment + roll number (scoped to year/class/section).
        // Runs in its own transaction (EnrollmentService) — must be outside the
        // creation transaction because the connection does not nest.
        $rollMsg = '';
        $yearId = (int) ($data['academic_year_id'] ?: (AcademicYear::current()['id'] ?? 0));
        $classId = (int) ($data['class_id'] ?: 0);
        $sectionId = (int) ($data['section_id'] ?: 0);
        if ($yearId && $classId) {
            $enr = (new EnrollmentService())->enroll($studentId, $yearId, $classId, $sectionId ?: null);
            if ($enr['ok']) {
                $rollMsg = ' Roll number ' . $enr['roll_no'] . ' assigned.';
            }
        }

        return $this->redirect('/admin/students/' . $studentId, ['success' => 'Student admitted successfully.' . $rollMsg]);
    }

    public function show(Request $request, string $id): Response
    {
        $student = Student::profile((int) $id);
        if ($student === null) {
            abort(404, 'Student not found.');
        }

        $documents = [];
        try {
            $documents = QueryBuilder::table('student_documents')->where('student_id', (int) $id)->get();
        } catch (\Throwable) {
        }

        return $this->view('admin.students.show', [
            'title'     => $student['first_name'] . ' ' . $student['last_name'],
            'student'   => $student,
            'guardians' => Guardian::forStudent((int) $id),
            'documents' => $documents,
            'documentTypes' => DocumentService::TYPES,
            'aadhaarMasked' => !empty($student['aadhaar_last4']) ? 'XXXX-XXXX-' . $student['aadhaar_last4'] : '—',
            'hasAadhaar' => !empty($student['aadhaar_enc']),
            'canReveal' => auth()->can('students.reveal_id'),
            'canVerify' => auth()->can('students.verify'),
            'canDocs'   => auth()->can('students.documents'),
            'attendance' => (new AttendanceService())->getStudentAttendanceSummary((int) $id, (int) ($student['academic_year_id'] ?? 0) ?: null),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $student = Student::find((int) $id);
        if ($student === null) {
            abort(404, 'Student not found.');
        }

        return $this->view('admin.students.edit', [
            'title'    => 'Edit Student',
            'student'  => $student,
            'classes'  => ClassModel::ordered(),
            'sections' => Section::withClass(),
            'years'    => AcademicYear::all(),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $student = Student::find((int) $id);
        if ($student === null) {
            abort(404, 'Student not found.');
        }

        $data = $this->validate($request, [
            'first_name' => 'required|string|max:80',
            'last_name'  => 'required|string|max:80',
            'gender'     => 'required|in:male,female,other',
            'dob'        => 'nullable|date',
            'email'      => 'nullable|email|max:190',
            'phone'      => 'nullable|phone',
            'class_id'   => 'nullable|integer',
            'section_id' => 'nullable|integer',
            'status'     => 'required|in:active,inactive,graduated,transferred,alumni',
        ]);

        $update = [
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'gender'     => $data['gender'],
            'dob'        => $data['dob'] ?: null,
            'email'      => $data['email'] ?: null,
            'phone'      => $data['phone'] ?: null,
            'roll_no'    => $request->input('roll_no'),
            'address'    => $request->input('address'),
            'blood_group'=> $request->input('blood_group'),
            'class_id'   => $data['class_id'] ?: null,
            'section_id' => $data['section_id'] ?: null,
            'status'     => $data['status'],
        ];

        $photo = $this->handlePhoto($request);
        if ($photo !== null) {
            $update['photo'] = $photo;
        }

        // Government IDs (only overwrite fields actually provided).
        $update = array_merge($update, $this->govIdData($request));

        Student::updateById((int) $id, $update);
        AuditLogger::log('student.updated', 'student', (int) $id, 'Updated student');

        return $this->redirect('/admin/students/' . $id, ['success' => 'Student updated successfully.']);
    }

    public function destroy(Request $request, string $id): Response
    {
        $student = Student::find((int) $id);
        if ($student === null) {
            abort(404, 'Student not found.');
        }

        Student::deleteById((int) $id);
        AuditLogger::log('student.deleted', 'student', (int) $id, 'Soft-deleted student');

        return $this->redirect('/admin/students', ['success' => 'Student removed.']);
    }

    /**
     * Bulk-promote students from one class/section to another.
     */
    public function promote(Request $request): Response
    {
        $ids = array_map('intval', (array) $request->input('student_ids', []));
        $toClass = (int) $request->input('to_class_id', 0);
        $toSection = (int) $request->input('to_section_id', 0);
        $toYear = (int) $request->input('to_year_id', 0);

        if ($ids === [] || $toClass === 0) {
            return $this->back(['promote' => ['Select students and a destination class.']]);
        }

        // History-preserving promotion into a new academic year (Student ID kept,
        // new roll number issued, prior enrollment marked 'promoted').
        if ($toYear && $toSection) {
            $svc = new EnrollmentService();
            $count = 0;
            foreach ($ids as $sid) {
                $student = Student::find($sid);
                if ($student === null) {
                    continue;
                }
                $fromYear = (int) ($student['academic_year_id'] ?? 0);
                $res = $svc->promote($sid, $fromYear, $toYear, $toClass, $toSection);
                if ($res['ok']) {
                    $count++;
                }
            }
            AuditLogger::log('student.promoted', 'student', null, "Promoted {$count} students to year {$toYear}");
            return $this->redirect('/admin/students', ['success' => "Promoted {$count} student(s) (history preserved)."]);
        }

        // Legacy in-place class move (same academic context).
        $count = 0;
        foreach ($ids as $sid) {
            $student = Student::find($sid);
            if ($student === null) {
                continue;
            }
            QueryBuilder::table('student_promotions')->insert([
                'student_id'      => $sid,
                'from_class_id'   => $student['class_id'],
                'to_class_id'     => $toClass,
                'from_section_id' => $student['section_id'],
                'to_section_id'   => $toSection ?: null,
                'result'          => 'promoted',
                'promoted_by'     => auth()->id(),
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            Student::updateById($sid, ['class_id' => $toClass, 'section_id' => $toSection ?: null]);
            $count++;
        }

        AuditLogger::log('student.promoted', 'student', null, "Promoted {$count} students");

        return $this->redirect('/admin/students', ['success' => "Promoted {$count} student(s)."]);
    }

    /**
     * Government ID fields from the request. Aadhaar is encrypted at rest and
     * only its last 4 digits are kept in clear (for masked display). Only
     * fields actually provided are returned (blanks won't wipe existing values).
     *
     * @return array<string,mixed>
     */
    private function govIdData(Request $request): array
    {
        $out = [];
        $aadhaar = preg_replace('/\D/', '', (string) $request->input('aadhaar', '')) ?? '';
        if ($aadhaar !== '') {
            $out['aadhaar_enc'] = Crypto::encrypt($aadhaar);
            $out['aadhaar_last4'] = substr($aadhaar, -4);
        }
        foreach (['abc_id', 'apaar_id', 'pen_id', 'udise_id'] as $f) {
            $v = trim((string) $request->input($f, ''));
            if ($v !== '') {
                $out[$f] = $v;
            }
        }
        return $out;
    }

    /** Reveal the decrypted Aadhaar (permission-gated by route); audit-logged. */
    public function revealAadhaar(Request $request, string $id): Response
    {
        $student = Student::find((int) $id);
        if ($student === null) {
            abort(404, 'Student not found.');
        }
        $plain = Crypto::decrypt($student['aadhaar_enc'] ?? null);
        AuditLogger::log('student.aadhaar_revealed', 'student', (int) $id, 'Revealed Aadhaar number');
        return $this->json(['aadhaar' => $plain ?? '']);
    }

    /** Upload a student document (secure, validated, pending verification). */
    public function uploadDocument(Request $request, string $id): Response
    {
        if (Student::find((int) $id) === null) {
            abort(404, 'Student not found.');
        }
        $file = $request->file('document');
        if ($file === null) {
            return $this->redirect('/admin/students/' . $id, ['error' => 'Please choose a file to upload.']);
        }
        $res = (new DocumentService())->store(
            (int) $id, $file, (string) $request->input('title', ''), (string) $request->input('type', 'other'), auth()->id()
        );
        return $this->redirect('/admin/students/' . $id, [$res['ok'] ? 'success' : 'error' => $res['message']]);
    }

    /** Verify or reject a document (permission-gated by route). */
    public function reviewDocument(Request $request, string $id, string $docId): Response
    {
        $approve = $request->input('decision') === 'approve';
        $res = (new DocumentService())->review((int) $docId, $approve, auth()->id(), (string) $request->input('reason', ''));
        $student = Student::find((int) $id);
        if ($student !== null && !empty($student['user_id'])) {
            (new NotificationService())->notify((int) $student['user_id'], 'document',
                'Document ' . ($approve ? 'verified' : 'rejected'),
                'Your document was ' . ($approve ? 'verified' : 'rejected') . '.', '/student/profile');
        }
        return $this->redirect('/admin/students/' . $id, [$res['ok'] ? 'success' : 'error' => $res['message']]);
    }

    /** Stream a document to authorized users only (files live outside web root). */
    public function downloadDocument(Request $request, string $id, string $docId): Response
    {
        $svc = new DocumentService();
        $doc = $svc->find((int) $docId);
        if ($doc === null || (int) $doc['student_id'] !== (int) $id) {
            abort(404, 'Document not found.');
        }
        $path = $svc->absolutePath($doc);
        if ($path === null) {
            abort(404, 'File is missing.');
        }
        AuditLogger::log('document.downloaded', 'student_document', (int) $docId, 'Served document');
        return (new Response((string) file_get_contents($path), 200))
            ->header('Content-Type', (string) ($doc['mime_type'] ?? 'application/octet-stream'))
            ->header('Content-Disposition', 'inline; filename="' . basename((string) $doc['file_path']) . '"')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function deleteDocument(Request $request, string $id, string $docId): Response
    {
        (new DocumentService())->delete((int) $docId);
        return $this->redirect('/admin/students/' . $id, ['success' => 'Document deleted.']);
    }

    /**
     * Validate and store an uploaded photo; returns the stored filename or null.
     */
    private function handlePhoto(Request $request): ?string
    {
        $file = $request->file('photo');
        if ($file === null) {
            return null;
        }

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = (int) config('app.upload_max_size', 5242880);

        if (!$file->isValid() || !in_array($file->mimeType(), $allowedMime, true) || $file->size() > $maxSize) {
            return null;
        }

        try {
            return $file->store(base_path(self::UPLOAD_DIR));
        } catch (\Throwable $e) {
            logger()->warning('Photo upload failed: ' . $e->getMessage());
            return null;
        }
    }
}
