<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AttendanceSettingsService;
use App\Services\AuditLogger;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Settings -> Academic -> Attendance & Calendar Configuration.
 *
 * A single, database-backed configuration surface for attendance type, the
 * working week and the timetable time-arrangement. Every value is validated
 * server-side, saved to the settings table, and consumed by CalendarService /
 * AttendanceSettingsService across the ERP — no hardcoded values, no code edits.
 */
final class AttendanceSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $svc = new AttendanceSettingsService();
        return $this->view('admin.settings.attendance', [
            'title'    => 'Attendance & Calendar',
            'config'   => $svc->config(),
            'types'    => AttendanceSettingsService::TYPES,
            'days'     => AttendanceSettingsService::DAYS,
            'weekend'  => $svc->weekendDays(),
            'working'  => $svc->workingDays(),
            'schedule' => $svc->periodSchedule(),
            'endTime'  => $svc->scheduleEndTime(),
        ]);
    }

    public function save(Request $request): Response
    {
        $svc = new AttendanceSettingsService();

        $weekend = array_values(array_filter(
            array_map('strval', (array) $request->input('weekend_days', [])),
            static fn ($d) => ctype_digit($d) && (int) $d >= 0 && (int) $d <= 6
        ));

        $input = [
            'attendance_type'       => (string) $request->input('attendance_type', 'daily'),
            'week_start_day'        => (string) $request->input('week_start_day', '1'),
            'weekend_days'          => $weekend,
            'school_open_time'      => (string) $request->input('school_open_time', ''),
            'school_close_time'     => (string) $request->input('school_close_time', ''),
            'assembly_duration_min' => (string) $request->input('assembly_duration_min', '0'),
            'period_duration_min'   => (string) $request->input('period_duration_min', '40'),
            'break_duration_min'    => (string) $request->input('break_duration_min', '0'),
            'lunch_duration_min'    => (string) $request->input('lunch_duration_min', '0'),
            'periods_count'         => (string) $request->input('periods_count', '1'),
            'buffer_between_min'    => (string) $request->input('buffer_between_min', '0'),
        ];

        $errors = $svc->validate($input);
        if ($errors !== []) {
            return $this->back(['attendance_settings' => $errors]);
        }

        $svc->set('attendance_type', $input['attendance_type']);
        $svc->set('week_start_day', $input['week_start_day']);
        $svc->set('attendance_weekend_days', implode(',', $weekend));
        foreach (['school_open_time', 'school_close_time', 'assembly_duration_min', 'period_duration_min', 'break_duration_min', 'lunch_duration_min', 'periods_count', 'buffer_between_min'] as $k) {
            $svc->set($k, $input[$k]);
        }

        AuditLogger::log('settings.attendance_configured', 'settings', null, 'Updated attendance & calendar configuration');
        return $this->redirect('/admin/settings/attendance', ['success' => 'Attendance & calendar settings saved. They now apply across the ERP.']);
    }
}
