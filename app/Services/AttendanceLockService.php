<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Attendance date-locking with an admin unlock-approval workflow.
 *
 * Rule (default): today's attendance is editable; past dates are locked.
 * A teacher may request an unlock; an admin approves it, which temporarily
 * makes that (class, section, date) editable until the approval expires or is
 * consumed. Every request/approval is audit-logged.
 */
final class AttendanceLockService
{
    private const APPROVAL_TTL_HOURS = 24;

    /**
     * Is this (class, section, date) currently editable?
     * Future dates and dates outside the academic year are never editable.
     */
    public function isEditable(int $classId, int $sectionId, string $date): bool
    {
        $ts = strtotime($date);
        $today = strtotime(date('Y-m-d'));

        if ($ts === false || $ts > $today) {
            return false; // no future attendance
        }
        if ($ts === $today) {
            return true; // current day always editable
        }

        // Past date: editable only if an approved, unexpired unlock exists.
        try {
            $req = QueryBuilder::table('attendance_unlock_requests')
                ->where('class_id', $classId)->where('section_id', $sectionId)
                ->where('date', $date)->where('status', 'approved')
                ->orderBy('id', 'DESC')->first();
        } catch (\Throwable) {
            return false;
        }
        if ($req === null) {
            return false;
        }
        return $req['expires_at'] === null || strtotime((string) $req['expires_at']) >= time();
    }

    public function reason(int $classId, int $sectionId, string $date): string
    {
        return $this->isEditable($classId, $sectionId, $date)
            ? 'editable'
            : (strtotime($date) > strtotime(date('Y-m-d')) ? 'Future dates cannot be marked.' : 'Attendance for this date is locked. Request an unlock from an administrator.');
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function requestUnlock(int $classId, int $sectionId, string $date, string $reasonText, ?int $yearId = null): array
    {
        if (trim($reasonText) === '') {
            return ['ok' => false, 'message' => 'A reason is required.'];
        }
        // Avoid duplicate pending requests.
        $pending = QueryBuilder::table('attendance_unlock_requests')
            ->where('class_id', $classId)->where('section_id', $sectionId)
            ->where('date', $date)->where('status', 'pending')->first();
        if ($pending !== null) {
            return ['ok' => false, 'message' => 'An unlock request is already pending for this date.'];
        }
        $id = QueryBuilder::table('attendance_unlock_requests')->insert([
            'academic_year_id' => $yearId,
            'class_id' => $classId, 'section_id' => $sectionId, 'date' => $date,
            'requested_by' => auth()->id(), 'reason' => mb_substr($reasonText, 0, 255),
            'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('attendance.unlock_requested', 'attendance_unlock_request', $id, "Unlock requested for {$classId}-{$sectionId} on {$date}");
        return ['ok' => true, 'message' => 'Unlock request submitted for approval.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function review(int $requestId, bool $approve): array
    {
        $req = QueryBuilder::table('attendance_unlock_requests')->where('id', $requestId)->first();
        if ($req === null || $req['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Request not found or already reviewed.'];
        }
        QueryBuilder::table('attendance_unlock_requests')->where('id', $requestId)->update([
            'status'      => $approve ? 'approved' : 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'expires_at'  => $approve ? date('Y-m-d H:i:s', time() + self::APPROVAL_TTL_HOURS * 3600) : null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('attendance.unlock_' . ($approve ? 'approved' : 'rejected'), 'attendance_unlock_request', $requestId, 'Unlock ' . ($approve ? 'approved' : 'rejected'));
        return ['ok' => true, 'message' => 'Request ' . ($approve ? 'approved' : 'rejected') . '.'];
    }

    /** @return array<int,array<string,mixed>> Pending requests for the admin queue. */
    public function pending(): array
    {
        try {
            return QueryBuilder::table('attendance_unlock_requests')
                ->select(['attendance_unlock_requests.*', 'users.name AS requester', 'classes.name AS class_name', 'sections.name AS section_name'])
                ->leftJoin('users', 'attendance_unlock_requests.requested_by', '=', 'users.id')
                ->leftJoin('classes', 'attendance_unlock_requests.class_id', '=', 'classes.id')
                ->leftJoin('sections', 'attendance_unlock_requests.section_id', '=', 'sections.id')
                ->where('attendance_unlock_requests.status', 'pending')
                ->orderBy('attendance_unlock_requests.id', 'DESC')->get();
        } catch (\Throwable) {
            return [];
        }
    }
}
