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
