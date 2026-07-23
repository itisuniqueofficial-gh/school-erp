<?php
/** @var \Core\View\View $this */
/** @var string $env */ /** @var string $version */ /** @var string $driver */
/** @var string $dbName */ /** @var string $dbHost */
/** @var array<string,mixed> $state */
/** @var array<string,int|null> $counts */
/** @var array{name:string,at:string}|null $lastBackup */
$prod = $env === 'production';
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>System &amp; Database Status<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card p-4"><div class="text-xs text-slate-500">Environment</div><div class="mt-1 text-lg font-bold <?= $prod ? 'text-green-600' : 'text-amber-600' ?>"><?= e(ucfirst($env)) ?></div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">ERP Version</div><div class="mt-1 text-lg font-bold text-slate-800">v<?= e($version) ?></div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Database connection</div><div class="mt-1 text-lg font-bold <?= !empty($state['db_reachable']) ? 'text-green-600' : 'text-red-600' ?>"><?= !empty($state['db_reachable']) ? 'Connected' : 'Unreachable' ?></div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Migrations</div><div class="mt-1 text-lg font-bold <?= (int) ($state['migrations_pending'] ?? 0) === 0 ? 'text-green-600' : 'text-amber-600' ?>"><?= (int) ($state['migrations_applied'] ?? 0) ?> applied<?= (int) ($state['migrations_pending'] ?? 0) > 0 ? ' · ' . (int) $state['migrations_pending'] . ' pending' : '' ?></div></div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Database</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Driver</dt><dd class="font-mono text-slate-800"><?= e($driver) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Database</dt><dd class="font-mono text-slate-800"><?= e($dbName) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Host</dt><dd class="font-mono text-slate-800"><?= e($dbHost) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Tables</dt><dd class="text-slate-800"><?= (int) ($state['tables'] ?? 0) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Install state</dt><dd class="font-semibold <?= !empty($state['installed']) ? 'text-green-600' : 'text-amber-600' ?>"><?= !empty($state['installed']) ? 'Existing installation' : 'Fresh' ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Last backup</dt><dd class="text-slate-800"><?= $lastBackup ? e($lastBackup['name']) . ' · ' . e($lastBackup['at']) : '<span class="text-slate-400">none found</span>' ?></dd></div>
        </dl>
        <p class="mt-3 text-xs text-slate-400"><i class="fa-solid fa-shield-halved"></i> Credentials and secrets are never displayed. Code deployment never resets this database.</p>
    </div>
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Record counts <span class="text-xs font-normal text-slate-400">(post-deploy verification)</span></h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <?php foreach ($counts as $t => $n): ?>
                <div class="flex justify-between"><dt class="text-slate-500"><?= e(ucwords(str_replace('_', ' ', $t))) ?></dt><dd class="font-semibold text-slate-800"><?= $n === null ? '—' : (int) $n ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </div>
</div>
<?php $this->endSection(); ?>
