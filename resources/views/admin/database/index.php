<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $health */
$h = $health;
$bytes = static function (?int $b): string {
    if ($b === null) { return 'n/a'; }
    foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $u) { if ($b < 1024 || $u === 'TB') { return ($i === 0 ? (string) $b : number_format($b, 1)) . ' ' . $u; } $b /= 1024; }
    return '';
};
$badge = ['ok' => 'badge-green', 'warn' => 'badge-amber', 'fail' => 'badge-red'][$h['status']] ?? 'badge-slate';
$label = ['ok' => 'Healthy', 'warn' => 'Needs attention', 'fail' => 'Unavailable'][$h['status']] ?? $h['status'];
$engineName = ['mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL'][$h['engine']] ?? $h['engine'];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Database<?php $this->endSection(); ?>

<?php $this->section('page_actions'); ?>
<form method="POST" action="<?= e(url('/admin/database/test')) ?>" class="inline"><?= csrf_field() ?>
    <button class="btn btn-secondary"><i class="fa-solid fa-plug" aria-hidden="true"></i> Test connection</button></form>
<form method="POST" action="<?= e(url('/admin/database/schema/validate')) ?>" class="inline"><?= csrf_field() ?>
    <button class="btn btn-secondary"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Validate schema</button></form>
<?php $this->endSection(); ?>

<?php $this->section('main'); ?>
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <section class="card p-6 lg:col-span-2" aria-labelledby="db-status">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 id="db-status" class="font-semibold text-slate-800">Connection &amp; health</h2>
            <span class="badge <?= $badge ?>"><?= e($label) ?></span>
        </div>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <?php foreach ([
                'Engine' => $engineName . ($h['version'] ? ' ' . $h['version'] : ''),
                'Connection' => $h['reachable'] ? 'Connected (' . $h['latency_ms'] . ' ms)' : 'Not connected',
                'Database' => $h['database'],
                'Server' => $h['host'] . ($h['port'] ? ':' . $h['port'] : '') . ($h['ssl_mode'] !== 'disable' ? ' · SSL ' . $h['ssl_mode'] : ''),
                'User' => $h['username'],
                'Password' => 'Stored in .env, never shown',
                'Schema version' => $h['schema_version'] ?? 'not initialised',
                'Tables' => (string) $h['tables'],
                'Database size' => $bytes($h['size_bytes']),
                'Open connections' => $h['connections'] === null ? 'n/a' : (string) $h['connections'],
                'Storage in use' => $bytes($h['storage']['used_bytes']) . ($h['storage']['free_bytes'] !== null ? ' (' . $bytes($h['storage']['free_bytes']) . ' free)' : ''),
                'Backup before updates' => $policy . ($autoMigrate ? ' · automatic updates ON' : ' · automatic updates off'),
            ] as $k => $v): ?>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= e($k) ?></dt><dd class="break-words text-slate-700"><?= e((string) $v) ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <?php if ($h['problems'] !== []): ?>
        <ul class="mt-5 space-y-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">
            <?php foreach ($h['problems'] as $p): ?><li><i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i><?= e((string) $p) ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="card p-6" aria-labelledby="db-pending">
        <h2 id="db-pending" class="mb-3 font-semibold text-slate-800">Updates</h2>
        <?php if ($h['pending'] === []): ?>
            <p class="text-sm text-slate-600"><i class="fa-solid fa-circle-check mr-1 text-green-600" aria-hidden="true"></i>The database is up to date (<?= (int) $h['migrations_applied'] ?> migrations applied).</p>
        <?php else: ?>
            <p class="mb-2 text-sm text-slate-600"><?= count($h['pending']) ?> pending:</p>
            <ul class="mb-3 list-disc pl-5 text-xs text-slate-600"><?php foreach ($h['pending'] as $n): ?><li class="font-mono"><?= e((string) $n) ?></li><?php endforeach; ?></ul>
            <form method="POST" action="<?= e(url('/admin/database/upgrade')) ?>" data-no-validate class="space-y-2">
                <?= csrf_field() ?>
                <label class="form-label" for="db-apply">Type APPLY to update</label>
                <input id="db-apply" name="confirm" class="form-input" autocomplete="off" placeholder="APPLY">
                <p class="text-xs text-slate-500">A verified backup is taken first (policy: <?= e($policy) ?>). Nothing is dropped.</p>
                <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Apply updates</button>
            </form>
        <?php endif; ?>
        <?php if (!($h['schema']['ok'] ?? true)): ?>
        <details class="mt-4 text-sm"><summary class="cursor-pointer font-medium text-slate-700">Schema differences (<?= count($h['schema']['issues']) ?>)</summary>
            <ul class="mt-2 list-disc pl-5 text-xs text-slate-600"><?php foreach (array_slice($h['schema']['issues'], 0, 15) as $i): ?><li><?= e((string) $i['detail']) ?></li><?php endforeach; ?></ul>
            <form method="POST" action="<?= e(url('/admin/database/schema/repair')) ?>" data-no-validate class="mt-3 space-y-2"><?= csrf_field() ?>
                <label class="form-label" for="db-repair">Type REPAIR to re-create missing objects</label>
                <input id="db-repair" name="confirm" class="form-input" autocomplete="off" placeholder="REPAIR">
                <button class="btn btn-secondary w-full justify-center">Repair additively</button>
                <p class="text-xs text-slate-500">Creates what is missing, skips what exists, removes nothing. A backup is taken first.</p>
            </form></details>
        <?php endif; ?>
    </section>

    <section class="card p-6 lg:col-span-3" aria-labelledby="db-backups">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 id="db-backups" class="font-semibold text-slate-800">Backups</h2>
            <form method="POST" action="<?= e(url('/admin/database/backup')) ?>" class="inline"><?= csrf_field() ?>
                <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Create &amp; verify backup</button></form>
        </div>
        <?php if (!empty($h['backup_summary']['warning'])): ?><p class="mb-3 text-sm text-amber-700"><?= e((string) $h['backup_summary']['warning']) ?></p><?php endif; ?>
        <div class="overflow-x-auto"><table class="data-table table-cards">
            <thead><tr><th scope="col">File</th><th scope="col">Kind</th><th scope="col">Status</th><th scope="col">Size</th><th scope="col">Rows</th><th scope="col">Created</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($h['backups'] as $b): ?>
                <tr>
                    <td data-label="File" class="font-mono text-xs break-all"><?= e((string) $b['filename']) ?><?= $b['encrypted'] ? ' <i class="fa-solid fa-lock" title="Encrypted"></i>' : '' ?></td>
                    <td data-label="Kind"><?= e(str_replace('_', ' ', (string) $b['kind'])) ?></td>
                    <td data-label="Status"><span class="badge <?= !$b['exists'] || $b['status'] === 'failed' ? 'badge-red' : ($b['status'] === 'restore_tested' ? 'badge-blue' : 'badge-green') ?>"><?= e($b['exists'] ? str_replace('_', ' ', (string) $b['status']) : 'file missing') ?></span></td>
                    <td data-label="Size"><?= e($bytes((int) $b['size_bytes'])) ?></td>
                    <td data-label="Rows"><?= e(number_format((int) $b['rows_count'])) ?></td>
                    <td data-label="Created" class="whitespace-nowrap text-xs"><?= e((string) $b['created_at']) ?></td>
                    <td data-label="" class="text-right"><?php if ($b['exists']): ?>
                        <form method="POST" action="<?= e(url('/admin/database/backup/verify')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="file" value="<?= e((string) $b['filename']) ?>">
                            <button class="btn btn-secondary btn-sm">Verify</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; if ($h['backups'] === []): ?><tr><td colspan="7" class="py-6 text-center text-slate-400">No backups yet.</td></tr><?php endif; ?>
            </tbody></table></div>
        <p class="mt-3 text-xs text-slate-500">Restoring never overwrites this database: load a backup into a new empty database with
            <code>php bin/console db:import</code>, check the report, then switch over. Prove a backup restores with <code>php bin/console backup:test</code>.</p>
    </section>

    <section class="card p-6 lg:col-span-2" aria-labelledby="db-history">
        <h2 id="db-history" class="mb-3 font-semibold text-slate-800">Migration history</h2>
        <div class="overflow-x-auto"><table class="data-table table-cards">
            <thead><tr><th scope="col">Migration</th><th scope="col">Batch</th><th scope="col">Applied</th><th scope="col">Time</th></tr></thead>
            <tbody><?php foreach ($h['history'] as $m): ?>
                <tr><td data-label="Migration" class="font-mono text-xs"><?= e((string) $m['migration']) ?></td><td data-label="Batch"><?= (int) $m['batch'] ?></td>
                    <td data-label="Applied" class="whitespace-nowrap text-xs"><?= e((string) $m['executed_at']) ?></td><td data-label="Time"><?= $m['execution_ms'] !== null ? (int) $m['execution_ms'] . ' ms' : '—' ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
    </section>

    <section class="card p-6" aria-labelledby="db-errors">
        <h2 id="db-errors" class="mb-3 font-semibold text-slate-800">Recent database errors</h2>
        <?php if ($h['recent_errors'] === []): ?><p class="text-sm text-slate-500">None recorded.</p>
        <?php else: ?><ul class="space-y-2 text-xs text-slate-600"><?php foreach ($h['recent_errors'] as $err): ?><li class="break-words font-mono"><?= e((string) $err) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
</div>
<?php $this->endSection(); ?>
