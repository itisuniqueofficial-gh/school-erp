<?php
/** @var \Core\View\View $this */
/** @var string $query */
/** @var string|null $error */
/** @var string|null $denied */
/** @var array<string,string>|null $archived */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Go to Record by ID<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="mx-auto max-w-xl">
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-slate-800"><i class="fa-solid fa-magnifying-glass-arrow-right text-brand-600"></i> Go to Record by ID</h2>
        <p class="mt-1 text-sm text-slate-500">Enter any unique ID and jump straight to the record. Spacing, hyphens and letter case are handled automatically. Access still respects your role permissions, and every lookup is logged.</p>

        <?php if (!empty($error)): ?>
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><i class="fa-solid fa-circle-xmark"></i> <?= e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($denied)): ?>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><i class="fa-solid fa-lock"></i> <?= e($denied) ?></div>
        <?php endif; ?>
        <?php if (!empty($archived)): ?>
            <div class="mt-4 rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <i class="fa-solid fa-box-archive"></i> This <?= e(strtolower($archived['type'])) ?> <span class="font-mono"><?= e($archived['uid']) ?></span><?= $archived['name'] !== '' ? ' (' . e($archived['name']) . ')' : '' ?> exists but is <b>archived / revoked</b>.
                <a href="<?= e(url($archived['route'])) ?>" class="ml-1 font-semibold text-brand-600 hover:underline">Open anyway →</a>
            </div>
        <?php endif; ?>

        <form method="GET" action="<?= e(url('/admin/lookup')) ?>" class="mt-4 flex gap-2">
            <input type="text" name="id" value="<?= e($query) ?>" autofocus required
                   class="form-input flex-1 font-mono" placeholder="e.g. STU-2026-00001, ADM-0001, CERT-2026-00001">
            <button class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Go</button>
        </form>

        <div class="mt-6 border-t border-slate-100 pt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Supported IDs</p>
            <ul class="mt-2 grid grid-cols-1 gap-1 text-sm text-slate-600 sm:grid-cols-2">
                <li><span class="font-mono text-brand-600">STU-…</span> / Admission No / 12-digit ID → Student</li>
                <li><span class="font-mono text-brand-600">TCH-…</span> / Employee Code → Teacher/Staff</li>
                <li><span class="font-mono text-brand-600">CERT-…</span> → Certificate</li>
                <li><span class="font-mono text-brand-600">RC-…</span> → Report Card</li>
                <li><span class="font-mono text-brand-600">REQ-…</span> → Certificate Request</li>
            </ul>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
