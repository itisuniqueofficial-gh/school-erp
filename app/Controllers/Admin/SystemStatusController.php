<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\InstallService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Read-only database & environment indicator for administrators. Shows the
 * environment, driver, database identity, connection + migration status, record
 * counts and the last backup. Never exposes credentials or secrets.
 */
final class SystemStatusController extends Controller
{
    public function index(Request $request): Response
    {
        $state = (new InstallService())->state();
        $conn = (string) config('database.default');

        // Record counts for post-deploy verification (nothing sensitive).
        $counts = [];
        foreach (['users', 'students', 'staff', 'exams', 'exam_marks', 'report_cards', 'certificates', 'hall_tickets', 'student_attendance', 'fee_invoices', 'audit_logs'] as $t) {
            try {
                $counts[$t] = (int) QueryBuilder::table($t)->count();
            } catch (\Throwable) {
                $counts[$t] = null; // table not present
            }
        }

        // Last backup (filename + time only).
        $lastBackup = null;
        $dir = dirname(__DIR__, 3) . '/storage/backups';
        if (is_dir($dir)) {
            $files = glob($dir . '/*.sql*') ?: [];
            if ($files !== []) {
                usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
                $lastBackup = ['name' => basename($files[0]), 'at' => date('Y-m-d H:i', (int) filemtime($files[0]))];
            }
        }

        return $this->view('admin.system.status', [
            'title'   => 'System & Database Status',
            'env'     => (string) config('app.env'),
            'version' => (string) config('app.version'),
            'driver'  => (string) config("database.connections.{$conn}.database") !== '' ? (string) config("database.connections.{$conn}.driver") : 'mysql',
            'dbName'  => (string) config("database.connections.{$conn}.database"),
            'dbHost'  => (string) config("database.connections.{$conn}.host") . ':' . (string) config("database.connections.{$conn}.port"),
            'state'   => $state,
            'counts'  => $counts,
            'lastBackup' => $lastBackup,
        ]);
    }
}
