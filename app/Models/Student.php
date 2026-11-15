<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\StudentIdentifierService;
use Core\Database\Connection;
use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Student model.
 */
final class Student extends Model
{
    protected string $table = 'students';
    protected bool $softDeletes = true;

    protected array $fillable = [
        'user_id', 'uid', 'prn', 'public_id', 'admission_no', 'roll_no', 'first_name', 'last_name', 'gender', 'dob',
        'blood_group', 'email', 'phone', 'address', 'city', 'state', 'postal_code',
        'nationality', 'religion', 'category', 'class_id', 'section_id', 'academic_year_id',
        'aadhaar_enc', 'aadhaar_last4', 'abc_id', 'apaar_id', 'pen_id', 'udise_id',
        'apaar_status', 'abc_status', 'digilocker_status', 'state_student_id', 'samagra_id',
        'emis_number', 'board_registration_no', 'digilocker_ref',
        'admission_date', 'photo', 'medical_notes', 'status',
    ];

    /**
     * Create a student. The permanent PRN (and an admission number when none is
     * given) is issued here, in the same transaction as the INSERT, so every
     * creation path (admin form, API, imports) gets one automatically.
     * A caller-supplied `prn` is rejected: PRNs are only ever generated.
     */
    public static function create(array $data): int
    {
        if (!empty($data['prn'])) {
            throw new \DomainException('PRN is generated automatically and cannot be supplied.');
        }
        return Connection::instance()->transaction(static function () use ($data): int {
            $svc = new StudentIdentifierService();
            $ids = $svc->reserve($data['admission_no'] ?? null, $data['admission_date'] ?? null);
            $data['admission_no'] = $ids['admission_no'];
            $data['admission_date'] = $ids['admission_date'];
            $data['prn'] = $ids['prn'];
            $id = parent::create($data);
            $svc->register($id, $ids, isset($data['uid']) ? (string) $data['uid'] : null);
            return $id;
        });
    }

    /**
     * PRN is immutable: any attempt to set a different value is an error, not a
     * silent no-op. Passing the identical value is ignored.
     */
    public static function updateById(int|string $id, array $data): int
    {
        if (array_key_exists('prn', $data)) {
            $current = QueryBuilder::table('students')->where('id', $id)->value('prn');
            if ($current !== null && (string) $current !== (string) $data['prn']) {
                throw new \DomainException('PRN is permanent and cannot be changed.');
            }
            if ($current !== null) {
                unset($data['prn']);
            } else {
                throw new \DomainException('PRN is generated automatically; use the backfill command for legacy students.');
            }
        }
        // Roll number is derived from the enrollment; use EnrollmentService::changeRoll().
        unset($data['roll_no']);
        return parent::updateById($id, $data);
    }

    public static function findByPrn(string $prn): ?array
    {
        return QueryBuilder::table('students')->where('prn', trim($prn))->first();
    }

    /**
     * Build a filtered, joined query for the index listing.
     *
     * @param array{search?:string,class_id?:int,section_id?:int,status?:string} $filters
     */
    public static function filtered(array $filters = []): QueryBuilder
    {
        $q = QueryBuilder::table('students')
            ->select([
                'students.id', 'students.prn', 'students.admission_no', 'students.roll_no',
                'students.first_name', 'students.last_name', 'students.gender',
                'students.status', 'students.photo', 'students.class_id', 'students.section_id',
                'classes.name AS class_name', 'sections.name AS section_name',
            ])
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'students.section_id', '=', 'sections.id')
            ->whereNull('students.deleted_at');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            // Grouped OR search so it doesn't break the AND filters.
            $q->whereRaw(
                '(`students`.`first_name` LIKE ? OR `students`.`last_name` LIKE ? OR `students`.`admission_no` LIKE ? OR `students`.`roll_no` LIKE ? OR `students`.`prn` LIKE ?)',
                [$term, $term, $term, $term, $term]
            );
        }
        if (!empty($filters['class_id'])) {
            $q->where('students.class_id', (int) $filters['class_id']);
        }
        if (!empty($filters['section_id'])) {
            $q->where('students.section_id', (int) $filters['section_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('students.status', $filters['status']);
        }

        return $q->orderBy('students.id', 'DESC');
    }

    /**
     * Full profile with class/section/year names.
     *
     * @return array<string,mixed>|null
     */
    public static function profile(int $id): ?array
    {
        return QueryBuilder::table('students')
            ->select([
                'students.*',
                'classes.name AS class_name', 'sections.name AS section_name',
                'academic_years.name AS year_name',
            ])
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'students.section_id', '=', 'sections.id')
            ->leftJoin('academic_years', 'students.academic_year_id', '=', 'academic_years.id')
            ->where('students.id', $id)
            ->whereNull('students.deleted_at')
            ->first();
    }

    /**
     * Preview of the next admission number (display only). The real number is
     * reserved atomically inside Student::create().
     */
    public static function generateAdmissionNo(): string
    {
        try {
            return (new StudentIdentifierService())->peekAdmissionNo();
        } catch (\Throwable) {
            return 'ADM-' . date('Y') . '-0001';
        }
    }
}
