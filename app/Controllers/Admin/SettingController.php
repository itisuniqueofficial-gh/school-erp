<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuditLogger;
use App\Services\AvatarService;
use Core\Config\Config;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * System settings: school profile & configuration, plus database backup.
 */
final class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = [];
        try {
            $rows = QueryBuilder::table('settings')->orderBy('group')->orderBy('key')->get();
        } catch (\Throwable) {
        }

        // Group settings for display.
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['group']][] = $row;
        }

        return $this->view('admin.settings.index', [
            'title'   => 'Settings',
            'grouped' => $grouped,
        ]);
    }

    public function update(Request $request): Response
    {
        $settings = (array) $request->input('settings', []); // [key => value]

        foreach ($settings as $key => $value) {
            $key = (string) $key;
            $exists = QueryBuilder::table('settings')->where('key', $key)->first();
            if ($exists) {
                QueryBuilder::table('settings')->where('key', $key)->update([
                    'value'      => is_array($value) ? json_encode($value) : (string) $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                QueryBuilder::table('settings')->insert([
                    'group'      => 'general',
                    'key'        => $key,
                    'value'      => is_array($value) ? json_encode($value) : (string) $value,
                    'type'       => 'string',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        AuditLogger::log('settings.updated', 'settings', null, 'Updated system settings');
        return $this->redirect('/admin/settings', ['success' => 'Settings saved.']);
    }

    /** Upload/replace the school logo (used in navigation, login and all documents). */
    public function uploadLogo(Request $request): Response
    {
        $file = $request->file('logo');
        if ($file === null || !$file->isValid()) {
            return $this->redirect('/admin/settings', ['error' => 'Please choose a logo image to upload.']);
        }
        $svc = new AvatarService();
        $res = $svc->store($file, 5242880); // 5 MB
        if (!$res['ok']) {
            return $this->redirect('/admin/settings', ['error' => $res['message']]);
        }
        $old = QueryBuilder::table('settings')->where('key', 'school_logo')->value('value');
        $this->putSetting('school_logo', (string) $res['filename']);
        if (!empty($old)) {
            $svc->delete((string) $old);
        }
        AuditLogger::log('settings.logo_updated', 'settings', null, 'Updated school logo');
        return $this->redirect('/admin/settings', ['success' => 'School logo updated. It now appears across the ERP and generated documents.']);
    }

    /** Remove the school logo (falls back to the default). */
    public function removeLogo(Request $request): Response
    {
        $old = QueryBuilder::table('settings')->where('key', 'school_logo')->value('value');
        if (!empty($old)) {
            (new AvatarService())->delete((string) $old);
        }
        $this->putSetting('school_logo', '');
        AuditLogger::log('settings.logo_removed', 'settings', null, 'Removed school logo');
        return $this->redirect('/admin/settings', ['success' => 'School logo removed. The default logo will be used.']);
    }

    /** Upsert a single general setting. */
    private function putSetting(string $key, string $value): void
    {
        if (QueryBuilder::table('settings')->where('key', $key)->first() !== null) {
            QueryBuilder::table('settings')->where('key', $key)->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            QueryBuilder::table('settings')->insert([
                'group' => 'general', 'key' => $key, 'value' => $value, 'type' => 'string',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function backup(Request $request): Response
    {
        $c = (array) Config::get('database.connections.mysql');
        $dir = (string) Config::get('paths.backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/backup-' . date('Y-m-d_His') . '.sql';

        $cmd = sprintf(
            'mysqldump --host=%s --port=%d --user=%s %s %s > %s 2>/dev/null',
            escapeshellarg((string) ($c['host'] ?? '127.0.0.1')),
            (int) ($c['port'] ?? 3306),
            escapeshellarg((string) ($c['username'] ?? 'root')),
            !empty($c['password']) ? '--password=' . escapeshellarg((string) $c['password']) : '',
            escapeshellarg((string) ($c['database'] ?? '')),
            escapeshellarg($file)
        );

        @exec($cmd, $out, $code);

        if ($code === 0 && is_file($file) && filesize($file) > 0) {
            AuditLogger::log('settings.backup', 'settings', null, 'Database backup created');
            return $this->redirect('/admin/settings', ['success' => 'Backup created: ' . basename($file)]);
        }

        return $this->redirect('/admin/settings', ['error' => 'Backup failed. Ensure mysqldump is available on the server.']);
    }
}
