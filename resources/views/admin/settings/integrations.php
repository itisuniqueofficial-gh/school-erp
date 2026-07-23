<?php
/** @var \Core\View\View $this */
/** @var array<string,array{meta:array,config:array}> $providers */
/** @var array<int,array<string,mixed>> $logs */
$statusBadge = ['success' => 'badge-green', 'pending' => 'badge-slate', 'retry' => 'badge-amber', 'failed' => 'badge-red'];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/settings')) ?>" class="hover:text-brand-600">Settings</a> / Government Integrations<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<?php $sd = $syncDashboard ?? []; ?>
<div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <?php foreach ([
        'Sync jobs' => $sd['total_jobs'] ?? 0, 'Completed' => $sd['completed'] ?? 0, 'Partial' => $sd['partial'] ?? 0,
        'Failed' => $sd['failed'] ?? 0, 'Records created' => $sd['records_created'] ?? 0, 'Records updated' => $sd['records_updated'] ?? 0,
    ] as $label => $val): ?>
        <div class="card p-3 text-center">
            <div class="text-2xl font-bold text-slate-800"><?= (int) $val ?></div>
            <div class="text-xs text-slate-500"><?= e($label) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <i class="fa-solid fa-building-columns mr-1"></i>
    Configure future government-system adapters. Credentials are <strong>encrypted at rest</strong> and never shown in full. Integrations stay disabled until you connect them; enabling only stores config — no external calls are made automatically.
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <?php foreach ($providers as $key => $data): $c = $data['config']; $meta = $data['meta']; ?>
    <div class="card p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800"><?= e($meta['label']) ?></h2>
            <span class="badge <?= !empty($c['enabled']) ? 'badge-green' : 'badge-slate' ?>"><?= !empty($c['enabled']) ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <div class="mb-2 text-xs text-slate-400"><i class="fa-solid fa-right-left"></i> <?= e(ucfirst((string) ($directions[$key] ?? 'pull'))) ?> integration</div>
        <form method="POST" action="<?= e(url('/admin/settings/integrations/' . $key)) ?>" class="space-y-3" data-loading>
            <?= csrf_field() ?>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="enabled" value="1" <?= !empty($c['enabled']) ? 'checked' : '' ?> class="rounded border-slate-300"> Enable
            </label>
            <div>
                <label class="form-label" for="<?= $key ?>_mode">Mode</label>
                <select id="<?= $key ?>_mode" name="mode" class="form-select">
                    <option value="sandbox" <?= ($c['mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                    <option value="live" <?= ($c['mode'] ?? 'sandbox') === 'live' ? 'selected' : '' ?>>Live</option>
                </select>
            </div>
            <?php foreach ($meta['fields'] as $field): $isSecret = in_array($field, $meta['secret'], true); $isSet = !empty($c[$field . '_set']); ?>
            <div>
                <label class="form-label" for="<?= $key . '_' . $field ?>"><?= e(ucwords(str_replace('_', ' ', $field))) ?><?php if ($isSecret): ?> <span class="text-xs text-slate-400">(secret)</span><?php endif; ?></label>
                <input id="<?= $key . '_' . $field ?>" name="<?= e($field) ?>" type="<?= $isSecret ? 'password' : 'text' ?>" autocomplete="off"
                       class="form-input" value="<?= $isSecret ? '' : e((string) ($c[$field] ?? '')) ?>"
                       placeholder="<?= $isSecret && $isSet ? '•••••••• (saved — leave blank to keep)' : '' ?>">
            </div>
            <?php endforeach; ?>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-800">Recent synchronization log</h2></div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Time</th><th>Provider</th><th>Action</th><th>Student</th><th>Status</th><th>Ref</th><th>Message</th></tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="py-8 text-center text-slate-400">No synchronization attempts yet.</td></tr>
            <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td class="whitespace-nowrap text-xs text-slate-500"><?= e((string) ($l['created_at'] ?? '')) ?></td>
                    <td><?= e(strtoupper((string) $l['provider'])) ?></td>
                    <td><?= e((string) $l['action']) ?></td>
                    <td><?= e(trim((string) (($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''))) ?: '—') ?></td>
                    <td><span class="badge <?= $statusBadge[$l['status']] ?? 'badge-slate' ?>"><?= e(ucfirst((string) $l['status'])) ?></span></td>
                    <td class="font-mono text-xs text-slate-400"><?= e((string) ($l['request_ref'] ?? '')) ?></td>
                    <td class="text-slate-600"><?= e((string) ($l['message'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
