<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $logs */
/** @var array<string,int> $meta */
/** @var array<string,string> $filters */
/** @var array<int,string> $actions */
/** @var array<int,string> $entities */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Audit Log<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search user / ID / description" class="form-input lg:col-span-2">
        <select name="action" class="form-select">
            <option value="">All actions</option>
            <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
        </select>
        <select name="entity_type" class="form-select">
            <option value="">All entities</option>
            <?php foreach ($entities as $en): ?><option value="<?= e($en) ?>" <?= $filters['entity_type'] === $en ? 'selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="form-input" aria-label="From date">
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="form-input" aria-label="To date">
    </div>
    <div class="mt-3 flex gap-2">
        <button class="btn btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= e(url('/admin/audit')) ?>" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    </div>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table table-cards">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="py-10 text-center text-slate-400">No audit entries match your filters.</td></tr>
            <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td data-label="Time" class="whitespace-nowrap text-xs text-slate-500"><?= e((string) ($l['created_at'] ?? '')) ?></td>
                    <td data-label="User"><span class="font-medium text-slate-800"><?= e((string) ($l['user_name'] ?? 'System')) ?></span><?php if (!empty($l['user_uid'])): ?><span class="block font-mono text-[11px] text-slate-400"><?= e((string) $l['user_uid']) ?></span><?php endif; ?></td>
                    <td data-label="Action"><span class="badge badge-blue"><?= e((string) $l['action']) ?></span></td>
                    <td data-label="Entity"><?= e((string) ($l['entity_type'] ?? '—')) ?><?php if (!empty($l['entity_id'])): ?> <span class="text-slate-400">#<?= (int) $l['entity_id'] ?></span><?php endif; ?></td>
                    <td data-label="Description" class="text-slate-600"><?= e((string) ($l['description'] ?? '')) ?></td>
                    <td data-label="IP" class="font-mono text-xs text-slate-400"><?= e((string) ($l['ip_address'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->include('partials.pagination', ['meta' => $meta]); ?>
<?php $this->endSection(); ?>
