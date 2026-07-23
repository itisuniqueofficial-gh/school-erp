<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Class-wise subject structure: assign typed subjects (core / optional /
 * elective / extra / practical / language) to a class with a mandatory flag and
 * display order. Duplicate-safe (UNIQUE class+subject at the DB), auditable, and
 * the single source consumed by exam scheduling, marks, hall tickets and report
 * cards.
 */
final class ClassSubjectService
{
    public const TYPES = [
        'core'      => 'Core',
        'optional'  => 'Optional',
        'elective'  => 'Elective',
        'extra'     => 'Extra',
        'practical' => 'Practical',
        'language'  => 'Language',
    ];

    /** @return array<string,string> */
    public function types(): array
    {
        return self::TYPES;
    }

    /** Ordered subject allocations for a class. @return array<int,array<string,mixed>> */
    public function forClass(int $classId): array
    {
        return QueryBuilder::table('class_subjects')
            ->select(['class_subjects.*', 'subjects.name AS subject_name', 'subjects.code AS subject_code'])
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->where('class_subjects.class_id', $classId)
            ->orderBy('class_subjects.sort_order')->orderBy('subjects.name')->get();
    }

    /**
     * Assign a subject to a class (idempotent — updates if already allocated).
     *
     * @param array{type?:string,mandatory?:bool,full_marks?:int,pass_marks?:int} $opts
     * @return 'created'|'updated'
     */
    public function assign(int $classId, int $subjectId, array $opts = []): string
    {
        $type = isset(self::TYPES[$opts['type'] ?? '']) ? (string) $opts['type'] : 'core';
        // Core subjects are mandatory by default; optional/elective/extra default to not-mandatory.
        $mandatory = array_key_exists('mandatory', $opts)
            ? (!empty($opts['mandatory']) ? 1 : 0)
            : (in_array($type, ['core', 'language'], true) ? 1 : 0);
        $now = date('Y-m-d H:i:s');
        $payload = [
            'subject_type' => $type,
            'is_mandatory' => $mandatory,
            'full_marks'   => (int) ($opts['full_marks'] ?? 100),
            'pass_marks'   => (int) ($opts['pass_marks'] ?? 33),
            'status'       => 'active',
            'updated_at'   => $now,
        ];

        $existing = QueryBuilder::table('class_subjects')->where('class_id', $classId)->where('subject_id', $subjectId)->first();
        if ($existing !== null) {
            QueryBuilder::table('class_subjects')->where('id', (int) $existing['id'])->update($payload);
            AuditLogger::log('academic.class_subject_updated', 'class_subject', (int) $existing['id'], "Updated subject {$subjectId} on class {$classId} ({$type})");
            return 'updated';
        }

        $nextOrder = (int) (QueryBuilder::table('class_subjects')->where('class_id', $classId)->selectRaw('COALESCE(MAX(`sort_order`),0)+1 AS n')->value('n') ?? 1);
        $csId = QueryBuilder::table('class_subjects')->insert($payload + [
            'class_id' => $classId, 'subject_id' => $subjectId, 'sort_order' => $nextOrder, 'created_at' => $now,
        ]);
        QueryBuilder::table('class_subjects')->where('id', $csId)->update(['public_id' => sprintf('CSA-%04d', $csId)]);
        AuditLogger::log('academic.class_subject_allocated', 'class_subject', $csId, "Allocated subject {$subjectId} to class {$classId} ({$type})");
        return 'created';
    }

    /** Move an allocation up/down in display order. */
    public function move(int $csId, string $direction): bool
    {
        $row = QueryBuilder::table('class_subjects')->where('id', $csId)->first();
        if ($row === null) {
            return false;
        }
        $classId = (int) $row['class_id'];
        $order = (int) $row['sort_order'];
        $op = $direction === 'up' ? '<' : '>';
        $neighbour = QueryBuilder::table('class_subjects')
            ->where('class_id', $classId)->where('sort_order', $op, $order)
            ->orderBy('sort_order', $direction === 'up' ? 'DESC' : 'ASC')->first();
        if ($neighbour === null) {
            return false;
        }
        Connection::instance()->transaction(function () use ($row, $neighbour): void {
            QueryBuilder::table('class_subjects')->where('id', (int) $row['id'])->update(['sort_order' => (int) $neighbour['sort_order']]);
            QueryBuilder::table('class_subjects')->where('id', (int) $neighbour['id'])->update(['sort_order' => (int) $row['sort_order']]);
        });
        AuditLogger::log('academic.class_subject_reordered', 'class_subject', $csId, "Moved {$direction}");
        return true;
    }

    /**
     * Copy a whole subject structure from one class to another (idempotent).
     * @return array{copied:int}
     */
    public function copyFromClass(int $fromClassId, int $toClassId): array
    {
        $copied = 0;
        foreach ($this->forClass($fromClassId) as $r) {
            $this->assign($toClassId, (int) $r['subject_id'], [
                'type' => (string) $r['subject_type'], 'mandatory' => (int) $r['is_mandatory'] === 1,
                'full_marks' => (int) $r['full_marks'], 'pass_marks' => (int) $r['pass_marks'],
            ]);
            $copied++;
        }
        AuditLogger::log('academic.class_subjects_copied', 'class', $toClassId, "Copied {$copied} subject(s) from class {$fromClassId}");
        return ['copied' => $copied];
    }

    /**
     * Academic setup health for class-subject structure.
     * @return array{classes_without_subjects:array<int,string>,classes_without_mandatory:array<int,string>}
     */
    public function setupHealth(): array
    {
        $classes = QueryBuilder::table('classes')->select(['id', 'name'])->get();
        $noSubjects = [];
        $noMandatory = [];
        foreach ($classes as $c) {
            $cid = (int) $c['id'];
            $total = (int) QueryBuilder::table('class_subjects')->where('class_id', $cid)->count();
            if ($total === 0) {
                $noSubjects[] = (string) $c['name'];
                continue;
            }
            $mand = (int) QueryBuilder::table('class_subjects')->where('class_id', $cid)->where('is_mandatory', 1)->count();
            if ($mand === 0) {
                $noMandatory[] = (string) $c['name'];
            }
        }
        return ['classes_without_subjects' => $noSubjects, 'classes_without_mandatory' => $noMandatory];
    }
}
