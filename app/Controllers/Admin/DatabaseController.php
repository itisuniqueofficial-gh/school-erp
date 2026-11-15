<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuditLogger;
use App\Services\Database\BackupManager;
use App\Services\Database\DatabaseHealthService;
use App\Services\Database\DatabaseUpgradeService;
use App\Services\Database\SchemaManifest;
use App\Services\Setup\SetupService;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;
use Database\Migrator;

/**
 * Database administration dashboard. A fixed set of safe operations only: no SQL console, no
 * credentials, no restore-over-live. Every action is permission-gated (database.manage), CSRF
 * protected (global middleware), rate limited and audit logged.
 */
final class DatabaseController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.database.index', [
            'title'       => 'Database',
            'health'      => (new DatabaseHealthService())->report(),
            'policy'      => BackupManager::policy(),
            'autoMigrate' => \App\Support\StartupGuard::autoUpgradeEnabled(),
        ]);
    }

    /** Test the live connection and report the result. */
    public function test(Request $request): Response
    {
        $ping = Connection::instance()->ping();
        AuditLogger::log('database.connection_tested', 'database', null, $ping['ok'] ? 'Connection OK' : 'Connection failed');
        return $this->redirect('/admin/database', $ping['ok']
            ? ['success' => 'Connection successful.']
            : ['error' => (string) ($ping['message'] ?? 'Connection failed.')]);
    }

    /** Apply pending migrations through the safe upgrade path (verified backup first). */
    public function upgrade(Request $request): Response
    {
        if ((string) $request->input('confirm', '') !== 'APPLY') {
            return $this->redirect('/admin/database', ['error' => 'Type APPLY to confirm the database update.']);
        }
        $res = (new DatabaseUpgradeService())->upgrade(['user_id' => auth()->id()]);
        AuditLogger::log('database.upgrade', 'database', null, ($res['ok'] ? 'OK: ' : 'FAILED: ') . substr($res['message'], 0, 300));
        return $this->redirect('/admin/database', [$res['ok'] ? 'success' : 'error' => $res['message']]);
    }

    public function backup(Request $request): Response
    {
        try {
            $b = (new BackupManager())->create('manual', auth()->id());
        } catch (\Throwable $e) {
            AuditLogger::log('database.backup_failed', 'database', null, 'Backup failed');
            return $this->redirect('/admin/database', ['error' => 'Backup failed: ' . SetupService::redact((string) strtok($e->getMessage(), "\n"))]);
        }
        AuditLogger::log('database.backup_created', 'database', (int) $b['id'], 'Created and verified ' . $b['filename']);
        return $this->redirect('/admin/database', ['success' => sprintf('Backup %s created and verified (%d tables, %s rows).', $b['filename'], $b['tables'], number_format($b['rows']))]);
    }

    public function verifyBackup(Request $request): Response
    {
        $file = (string) $request->input('file', '');
        try {
            $r = (new BackupManager())->verify($file);
        } catch (\Throwable $e) {
            return $this->redirect('/admin/database', ['error' => $e->getMessage()]);
        }
        AuditLogger::log('database.backup_verified', 'database', null, ($r['ok'] ? 'Valid ' : 'INVALID ') . basename($file));
        return $this->redirect('/admin/database', [$r['ok'] ? 'success' : 'error' => $r['message']]);
    }

    public function validateSchema(Request $request): Response
    {
        $v = (new SchemaManifest())->validate();
        AuditLogger::log('database.schema_validated', 'database', null, $v['message']);
        $msg = $v['message'] . ($v['issues'] ? ' ' . implode('; ', array_map(static fn ($i) => $i['detail'], array_slice($v['issues'], 0, 5))) : '');
        return $this->redirect('/admin/database', [$v['ok'] ? 'success' : 'error' => $msg]);
    }

    /** Additive repair: re-run migrations idempotently after a verified backup. Removes nothing. */
    public function repairSchema(Request $request): Response
    {
        if ((string) $request->input('confirm', '') !== 'REPAIR') {
            return $this->redirect('/admin/database', ['error' => 'Type REPAIR to confirm.']);
        }
        try {
            (new BackupManager())->create('pre_migration', auth()->id());
            (new Migrator())->reapplyAll();
        } catch (\Throwable $e) {
            AuditLogger::log('database.repair_failed', 'database', null, 'Repair failed');
            return $this->redirect('/admin/database', ['error' => 'Repair stopped: ' . SetupService::redact((string) strtok($e->getMessage(), "\n"))]);
        }
        $v = (new SchemaManifest())->validate();
        AuditLogger::log('database.repaired', 'database', null, $v['message']);
        return $this->redirect('/admin/database', [$v['ok'] ? 'success' : 'error' => 'Repair finished. ' . $v['message']]);
    }
}
