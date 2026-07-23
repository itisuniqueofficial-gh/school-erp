<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\IdSequenceService;
use Core\Database\QueryBuilder;
use Core\Http\UploadedFile;

/**
 * Secure student document management.
 *
 * Files are stored OUTSIDE the public web root (storage/uploads/documents) and
 * are only ever served through an authorized controller action. Uploads are
 * validated by real MIME type (from file content), extension, and size.
 */
final class DocumentService
{
    /** Document categories offered in the UI. */
    public const TYPES = [
        'photo' => 'Student Photo', 'aadhaar' => 'Aadhaar Card', 'birth_certificate' => 'Birth Certificate',
        'transfer_certificate' => 'Transfer Certificate', 'abc' => 'ABC ID Document', 'apaar' => 'APAAR Document',
        'previous_marksheet' => 'Previous Marksheet', 'current_marksheet' => 'Current Marksheet',
        'leaving_certificate' => 'Leaving Certificate', 'medical_certificate' => 'Medical Certificate',
        'caste_certificate' => 'Caste Certificate', 'income_certificate' => 'Income Certificate',
        'domicile_certificate' => 'Domicile Certificate', 'disability_certificate' => 'Disability Certificate',
        'parent_document' => 'Parent Document', 'other' => 'Other Document',
    ];

    private const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    ];

    private function baseDir(): string
    {
        return rtrim((string) config('paths.storage'), '/') . '/uploads/documents';
    }

    /**
     * Validate + store an uploaded document.
     *
     * @return array{ok:bool,message:string,id:?int}
     */
    public function store(int $studentId, UploadedFile $file, string $title, string $type, ?int $userId): array
    {
        if (!$file->isValid()) {
            return ['ok' => false, 'message' => 'No valid file was uploaded.', 'id' => null];
        }
        if (!array_key_exists($type, self::TYPES)) {
            return ['ok' => false, 'message' => 'Invalid document type.', 'id' => null];
        }
        $mime = $file->mimeType();
        if (!isset(self::ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'message' => 'Only PDF, JPG, PNG or WEBP files are allowed.', 'id' => null];
        }
        $maxSize = (int) config('app.upload_max_size', 5242880);
        if ($file->size() > $maxSize) {
            return ['ok' => false, 'message' => 'File exceeds the maximum allowed size.', 'id' => null];
        }

        // Defence in depth: verify magic-byte signature + reject embedded code.
        $category = str_starts_with($mime, 'image/') ? 'image' : 'document';
        $check = (new FileValidator())->inspect($file->path(), $category, null, $maxSize);
        if (!$check['ok']) {
            return ['ok' => false, 'message' => $check['reason'], 'id' => null];
        }

        $dir = $this->baseDir() . '/' . $studentId;
        try {
            $stored = $file->store($dir);
        } catch (\Throwable $e) {
            logger()->error('Document upload failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not store the file.', 'id' => null];
        }

        $id = QueryBuilder::table('student_documents')->insert([
            'student_id' => $studentId,
            'uid'        => (new IdSequenceService())->uidDocument(),
            'title'      => mb_substr($title !== '' ? $title : (self::TYPES[$type]), 0, 150),
            'type'       => $type,
            'file_path'  => $studentId . '/' . $stored, // relative to baseDir()
            'mime_type'  => $mime,
            'file_size'  => $file->size(),
            'uploaded_by'=> $userId,
            'verification_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log('document.uploaded', 'student_document', $id, "Uploaded {$type} for student {$studentId}");
        return ['ok' => true, 'message' => 'Document uploaded (pending verification).', 'id' => $id];
    }

    /** @return array<string,mixed>|null */
    public function find(int $docId): ?array
    {
        return QueryBuilder::table('student_documents')->where('id', $docId)->first();
    }

    /** Required document types for completeness tracking. */
    public const REQUIRED = [
        'aadhaar' => 'Aadhaar',
        'birth_certificate' => 'Birth Certificate',
        'previous_marksheet' => 'Previous Marksheet',
    ];

    /**
     * Document completeness for a student.
     *
     * @return array{percent:int,missing:array<int,string>,required:int,have:int}
     */
    public function completion(int $studentId): array
    {
        try {
            $haveTypes = array_column(
                QueryBuilder::table('student_documents')->select(['type'])->where('student_id', $studentId)->get(),
                'type'
            );
        } catch (\Throwable) {
            $haveTypes = [];
        }
        $missing = [];
        foreach (self::REQUIRED as $key => $label) {
            if (!in_array($key, $haveTypes, true)) {
                $missing[] = $label;
            }
        }
        $total = count(self::REQUIRED);
        $done = $total - count($missing);
        return [
            'percent'  => $total > 0 ? (int) round(($done / $total) * 100) : 100,
            'missing'  => $missing,
            'required' => $total,
            'have'     => $done,
        ];
    }

    /** Absolute filesystem path for a stored document (or null if missing). */
    public function absolutePath(array $doc): ?string
    {
        $path = $this->baseDir() . '/' . ltrim((string) $doc['file_path'], '/');
        return is_file($path) ? $path : null;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function review(int $docId, bool $approve, ?int $userId, string $reason = ''): array
    {
        $doc = $this->find($docId);
        if ($doc === null) {
            return ['ok' => false, 'message' => 'Document not found.'];
        }
        QueryBuilder::table('student_documents')->where('id', $docId)->update([
            'verification_status' => $approve ? 'verified' : 'rejected',
            'verified_by' => $userId, 'verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('document.' . ($approve ? 'verified' : 'rejected'), 'student_document', $docId,
            ($approve ? 'Verified' : 'Rejected') . ($reason !== '' ? ': ' . $reason : ''));
        return ['ok' => true, 'message' => 'Document ' . ($approve ? 'verified' : 'rejected') . '.'];
    }

    public function delete(int $docId): void
    {
        $doc = $this->find($docId);
        if ($doc === null) {
            return;
        }
        $path = $this->absolutePath($doc);
        if ($path !== null) {
            @unlink($path);
        }
        QueryBuilder::table('student_documents')->where('id', $docId)->delete();
        AuditLogger::log('document.deleted', 'student_document', $docId, 'Deleted document');
    }
}
