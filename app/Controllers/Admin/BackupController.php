<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuditLogger;
use App\Services\BackupService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Backup manager: create, list, download, restore and delete SQL backups.
 * Restore is destructive and therefore requires explicit confirmation.
 */
final class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.backups.index', [
            'title'   => 'Backups',
            'backups' => (new BackupService())->list(),
        ]);
    }

    public function create(Request $request): Response
    {
        $file = (new BackupService())->create();
        if ($file === null) {
            return $this->redirect('/admin/backups', ['error' => 'Backup failed. Ensure mysqldump is available.']);
        }
        AuditLogger::log('backup.created', 'backup', null, 'Created backup ' . basename($file));
        return $this->redirect('/admin/backups', ['success' => 'Backup created: ' . basename($file)]);
    }

    public function download(Request $request): Response
    {
        $name = (string) $request->query('file', '');
        $path = (new BackupService())->path($name);
        if ($path === null) {
            abort(404, 'Backup not found.');
        }
        AuditLogger::log('backup.downloaded', 'backup', null, 'Downloaded ' . basename($path));
        return (new Response((string) file_get_contents($path), 200))
            ->header('Content-Type', 'application/sql')
            ->header('Content-Disposition', 'attachment; filename="' . basename($path) . '"');
    }

    public function restore(Request $request): Response
    {
        $name = (string) $request->input('file', '');
        $ok = (new BackupService())->restore($name);
        AuditLogger::log('backup.restored', 'backup', null, 'Restored ' . $name);
        return $this->redirect('/admin/backups', $ok
            ? ['success' => 'Database restored from ' . basename($name) . '.']
            : ['error' => 'Restore failed. Ensure mysql client is available and the file exists.']);
    }

    public function destroy(Request $request): Response
    {
        $name = (string) $request->input('file', '');
        (new BackupService())->delete($name);
        AuditLogger::log('backup.deleted', 'backup', null, 'Deleted ' . $name);
        return $this->redirect('/admin/backups', ['success' => 'Backup deleted.']);
    }
}
