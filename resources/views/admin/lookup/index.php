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
<?php
// Actual supported ID types (from IdResolverService) — do not invent types.
$supported = [
    ['icon' => 'fa-user-graduate',   'prefix' => 'STU-…',  'label' => 'Student',            'hint' => 'or Admission No / 12-digit Student ID'],
    ['icon' => 'fa-chalkboard-user', 'prefix' => 'TCH-…',  'label' => 'Teacher / Staff',    'hint' => 'or Employee Code (STF-… also works)'],
    ['icon' => 'fa-certificate',     'prefix' => 'CERT-…', 'label' => 'Certificate',        'hint' => 'Issued certificate record'],
    ['icon' => 'fa-file-lines',      'prefix' => 'RC-…',   'label' => 'Report Card',        'hint' => 'Generated report card'],
    ['icon' => 'fa-clipboard-list',  'prefix' => 'REQ-…',  'label' => 'Certificate Request','hint' => 'Pending / processed request'],
];
?>
<div class="w-full">

    <!-- Hero -->
    <div class="mb-6 flex items-start gap-4">
        <span class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-xl text-brand-600 sm:inline-flex">
            <i class="fa-solid fa-magnifying-glass-arrow-right"></i>
        </span>
        <div>
            <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Go to Record by ID</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Enter any supported ID to jump straight to its record. Spacing, hyphens and letter case are
                handled automatically. Access still respects your role permissions, and every lookup is logged.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 xl:grid-cols-4">
        <!-- Lookup card -->
        <div class="card p-6 lg:col-span-2 xl:col-span-3">
            <?php if (!empty($error)): ?>
                <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    <i class="fa-solid fa-circle-xmark mt-0.5"></i> <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($denied)): ?>
                <div class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                    <i class="fa-solid fa-lock mt-0.5"></i> <span><?= e($denied) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($archived)): ?>
                <div class="mb-4 flex items-start gap-2 rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status">
                    <i class="fa-solid fa-box-archive mt-0.5"></i>
                    <span>This <?= e(strtolower($archived['type'])) ?> <span class="font-mono"><?= e($archived['uid']) ?></span><?= $archived['name'] !== '' ? ' (' . e($archived['name']) . ')' : '' ?> exists but is <b>archived / revoked</b>.
                        <a href="<?= e(url($archived['route'])) ?>" class="ml-1 font-semibold text-brand-600 hover:underline">Open anyway &rarr;</a>
                    </span>
                </div>
            <?php endif; ?>

            <form method="GET" action="<?= e(url('/admin/lookup')) ?>" class="space-y-3" data-loading>
                <label for="lookup-id" class="block text-sm font-medium text-slate-700">Record ID</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-hashtag"></i>
                        </span>
                        <input type="text" id="lookup-id" name="id" value="<?= e($query) ?>" autofocus required
                               autocomplete="off" spellcheck="false"
                               aria-describedby="lookup-help"
                               class="form-input w-full pl-9 font-mono text-base"
                               placeholder="e.g. STU-2026-00001, ADM-0001, CERT-2026-00001">
                    </div>
                    <button type="submit" class="btn btn-primary justify-center sm:w-auto">
                        <i class="fa-solid fa-arrow-right"></i> Go
                    </button>
                </div>
                <p id="lookup-help" class="text-xs text-slate-400">Press Enter or click Go. Case, spaces and hyphens don&rsquo;t matter.</p>
            </form>
        </div>

        <!-- Supported IDs -->
        <div class="card p-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Supported IDs</h2>
            <ul class="mt-3 space-y-2">
                <?php foreach ($supported as $s): ?>
                    <li class="flex items-start gap-3 rounded-lg border border-slate-100 p-3">
                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <i class="fa-solid <?= e($s['icon']) ?>"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800"><span class="font-mono text-brand-600"><?= e($s['prefix']) ?></span> &middot; <?= e($s['label']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($s['hint']) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
