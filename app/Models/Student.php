<?php

declare(strict_types=1);

namespace App\Models;

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
        'user_id', 'admission_no', 'roll_no', 'first_name', 'last_name', 'gender', 'dob',
        'blood_group', 'email', 'phone', 'address', 'city', 'state', 'postal_code',
        'nationality', 'religion', 'category', 'class_id', 'section_id', 'academic_year_id',
        'admission_date', 'photo', 'medical_notes', 'status',
    ];

    /**
     * Build a filtered, joined query for the index listing.
     *
     * @param array{search?:string,class_id?:int,section_id?:int,status?:string} $filters
     */
    public static function filtered(array $filters = []): QueryBuilder
    {
        $q = QueryBuilder::table('students')
            ->select([
                'students.id', 'students.admission_no', 'students.roll_no',
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
                '(`students`.`first_name` LIKE ? OR `students`.`last_name` LIKE ? OR `students`.`admission_no` LIKE ? OR `students`.`roll_no` LIKE ?)',
                [$term, $term, $term, $term]
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
     * Generate the next sequential admission number, e.g. ADM-2026-0007.
     */
    public static function generateAdmissionNo(): string
    {
        $year = date('Y');
        $count = 0;
        try {
            $count = QueryBuilder::table('students')->count();
        } catch (\Throwable) {
        }
        return sprintf('ADM-%s-%04d', $year, $count + 1);
    }
}
