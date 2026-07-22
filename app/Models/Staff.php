<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Staff model — teaching and non-teaching employees.
 */
final class Staff extends Model
{
    protected string $table = 'staff';
    protected bool $softDeletes = true;

    protected array $fillable = [
        'user_id', 'uid', 'public_id', 'department_id', 'employee_code', 'first_name', 'last_name',
        'staff_type', 'designation', 'gender', 'dob', 'email', 'phone', 'address',
        'joining_date', 'qualification', 'experience_years', 'basic_salary', 'photo', 'status',
    ];

    /**
     * @param array{search?:string,staff_type?:string,status?:string,department_id?:int} $filters
     */
    public static function filtered(array $filters = []): QueryBuilder
    {
        $q = QueryBuilder::table('staff')
            ->select([
                'staff.id', 'staff.employee_code', 'staff.first_name', 'staff.last_name',
                'staff.staff_type', 'staff.designation', 'staff.email', 'staff.phone',
                'staff.status', 'staff.photo', 'departments.name AS department_name',
            ])
            ->leftJoin('departments', 'staff.department_id', '=', 'departments.id')
            ->whereNull('staff.deleted_at');

        if (!empty($filters['search'])) {
            $t = '%' . $filters['search'] . '%';
            $q->whereRaw('(`staff`.`first_name` LIKE ? OR `staff`.`last_name` LIKE ? OR `staff`.`employee_code` LIKE ? OR `staff`.`email` LIKE ?)', [$t, $t, $t, $t]);
        }
        if (!empty($filters['staff_type'])) {
            $q->where('staff.staff_type', $filters['staff_type']);
        }
        if (!empty($filters['status'])) {
            $q->where('staff.status', $filters['status']);
        }
        if (!empty($filters['department_id'])) {
            $q->where('staff.department_id', (int) $filters['department_id']);
        }

        return $q->orderBy('staff.id', 'DESC');
    }

    /** @return array<string,mixed>|null */
    public static function profile(int $id): ?array
    {
        return QueryBuilder::table('staff')
            ->select(['staff.*', 'departments.name AS department_name'])
            ->leftJoin('departments', 'staff.department_id', '=', 'departments.id')
            ->where('staff.id', $id)
            ->whereNull('staff.deleted_at')
            ->first();
    }

    /** @return array<int,array<string,mixed>> */
    public static function qualifications(int $staffId): array
    {
        return QueryBuilder::table('teacher_qualifications')->where('staff_id', $staffId)->get();
    }

    /** @return array<int,array<string,mixed>> */
    public static function subjects(int $staffId): array
    {
        return QueryBuilder::table('teacher_subjects')
            ->select(['teacher_subjects.*', 'subjects.name AS subject_name', 'classes.name AS class_name'])
            ->leftJoin('subjects', 'teacher_subjects.subject_id', '=', 'subjects.id')
            ->leftJoin('classes', 'teacher_subjects.class_id', '=', 'classes.id')
            ->where('teacher_subjects.staff_id', $staffId)
            ->get();
    }

    public static function generateEmployeeCode(): string
    {
        $count = 0;
        try {
            $count = QueryBuilder::table('staff')->count();
        } catch (\Throwable) {
        }
        return sprintf('EMP-%04d', $count + 1);
    }
}
