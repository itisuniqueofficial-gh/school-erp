<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\StatsService;
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
