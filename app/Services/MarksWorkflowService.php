<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Marks workflow state machine, transitioned per exam_schedule (a batch =
 * one exam + class + subject). Enforces allowed transitions, permissions and
 * audit logging. Locked/approved/published marks cannot be edited by teachers.
 *
 * States: draft → submitted → under_review → approved → published → locked
 *         (with correction_required / rejected side paths).
 */
final class MarksWorkflowService
{
    /** action => [fromStates, toState, permission] */
    private const TRANSITIONS = [
        'submit'  => [['draft', 'correction_required'], 'submitted', 'marks.submit'],
        'review'  => [['submitted'], 'under_review', 'marks.approve'],
        'approve' => [['submitted', 'under_review'], 'approved', 'marks.approve'],
        'reject'  => [['submitted', 'under_review'], 'correction_required', 'marks.approve'],
        'publish' => [['approved'], 'published', 'marks.publish'],
        'lock'    => [['published', 'approved'], 'locked', 'marks.publish'],
        'unlock'  => [['locked', 'published', 'approved'], 'draft', 'marks.unlock'],
    ];

    public function currentStatus(int $scheduleId): string
    {
        $row = QueryBuilder::table('exam_marks')
            ->where('exam_schedule_id', $scheduleId)
            ->orderBy('id', 'DESC')
            ->first();
        return (string) ($row['status'] ?? 'draft');
    }

    /**
     * A schedule is edit-locked once approved/published/locked.
     */
    public function isLocked(int $scheduleId): bool
    {
        return in_array($this->currentStatus($scheduleId), ['approved', 'published', 'locked'], true);
    }

    /**
     * @return array{ok:bool,message:string,status:string}
     */
    public function transition(int $scheduleId, string $action, ?int $userId = null): array
    {
        if (!isset(self::TRANSITIONS[$action])) {
            return ['ok' => false, 'message' => 'Unknown action.', 'status' => $this->currentStatus($scheduleId)];
        }
        [$from, $to, $permission] = self::TRANSITIONS[$action];

        if (!auth()->can($permission)) {
            return ['ok' => false, 'message' => 'You are not authorized to ' . $action . ' marks.', 'status' => $this->currentStatus($scheduleId)];
        }

        $current = $this->currentStatus($scheduleId);
        if (!in_array($current, $from, true)) {
            return ['ok' => false, 'message' => "Cannot {$action} marks from status '{$current}'.", 'status' => $current];
        }

        if (QueryBuilder::table('exam_marks')->where('exam_schedule_id', $scheduleId)->count() === 0) {
            return ['ok' => false, 'message' => 'No marks entered yet.', 'status' => $current];
        }

        Connection::instance()->transaction(function () use ($scheduleId, $to): void {
            QueryBuilder::table('exam_marks')
                ->where('exam_schedule_id', $scheduleId)
                ->update(['status' => $to, 'updated_at' => date('Y-m-d H:i:s')]);
        });

        AuditLogger::log('marks.' . $action, 'exam_schedule', $scheduleId, "Marks {$action}: {$current} → {$to}", $userId);
        return ['ok' => true, 'message' => 'Marks ' . $action . 'd (' . $to . ').', 'status' => $to];
    }
}
