<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\StatsService;
use App\Services\IntegrityService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Admin/staff dashboard: KPI cards, charts, recent activity and notices.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = new StatsService();

        return $this->view('admin.dashboard', [
            'title'          => 'Dashboard',
            'overview'       => $stats->overview(),
            'attendanceRate' => $stats->attendanceRate(),
            'feeTrend'       => $stats->feeTrend(),
            'studentsByClass'=> $stats->studentsByClass(),
            'activity'       => $stats->recentActivity(),
            'notices'        => $stats->notices(),
            'attention'      => (new IntegrityService())->attention(6),
        ]);
    }

    /**
     * Academic management health dashboard (integrity checks).
     */
    public function academicHealth(Request $request): Response
    {
        $svc = new IntegrityService();
        return $this->view('admin.academic.health', [
            'title'  => 'Academic Health',
            'checks' => $svc->checks(),
            'status' => $svc->status(),
        ]);
    }

    /**
     * Admin Setup Center: configuration health + entity counts + quick actions.
     */
    public function setupCenter(Request $request): Response
    {
        $svc = new IntegrityService();
        $q = static fn (string $t) => (int) (\Core\Database\QueryBuilder::table($t)->count());
        $yearId = (int) (\Core\Database\QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);
        $currentYear = \Core\Database\QueryBuilder::table('academic_years')->where('is_current', 1)->first();

        $counts = [
            'academic_years' => $q('academic_years'),
            'classes'        => $q('classes'),
            'sections'       => $q('sections'),
            'subjects'       => $q('subjects'),
            'class_subjects' => $q('class_subjects'),
            'teachers'       => (int) \Core\Database\QueryBuilder::table('staff')->where('staff_type', 'teaching')->whereNull('deleted_at')->count(),
            'teacher_assignments' => $q('teacher_assignments'),
            'students'       => (int) \Core\Database\QueryBuilder::table('students')->whereNull('deleted_at')->count(),
            'enrollments'    => (int) \Core\Database\QueryBuilder::table('student_enrollments')->where('academic_year_id', $yearId)->count(),
        ];

        return $this->view('admin.setup.index', [
            'title'       => 'Setup Center',
            'checks'      => $svc->checks(),
            'status'      => $svc->status(),
            'counts'      => $counts,
            'currentYear' => $currentYear,
        ]);
    }

    /**
     * JSON stats endpoint (used by charts / async refresh).
     */
    public function stats(Request $request): Response
    {
        $stats = new StatsService();
        return $this->json([
            'overview'        => $stats->overview(),
            'attendance_rate' => $stats->attendanceRate(),
            'fee_trend'       => $stats->feeTrend(),
            'students_by_class' => $stats->studentsByClass(),
        ]);
    }
}
