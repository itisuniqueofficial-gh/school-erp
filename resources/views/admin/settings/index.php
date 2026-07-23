<?php
/** @var \Core\View\View $this */
/** @var array<string,array<int,array<string,mixed>>> $grouped */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<a href="<?= e(url('/admin/settings/institution')) ?>" class="btn btn-secondary"><i class="fa-solid fa-sliders"></i> ERP Configuration</a>
<a href="<?= e(url('/admin/settings/integrations')) ?>" class="btn btn-secondary"><i class="fa-solid fa-building-columns"></i> Gov Integrations</a>
<a href="<?= e(url('/admin/settings/payments')) ?>" class="btn btn-secondary"><i class="fa-solid fa-credit-card"></i> Payment Gateways</a>
<?php if (auth()->can('settings.manage')): ?>
<form method="POST" action="<?= e(url('/admin/settings/backup')) ?>" class="inline">
    <?= csrf_field() ?>
    <button class="btn btn-secondary" data-confirm="Create a database backup now?"><i class="fa-solid fa-database"></i> Backup DB</button>
</form>
<?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<?php
$logoFile = '';
foreach ($grouped['general'] ?? [] as $row) { if (($row['key'] ?? '') === 'school_logo') { $logoFile = (string) $row['value']; } }
?>
<div class="card mb-6 p-5">
    <h2 class="font-semibold text-slate-800">School Logo</h2>
    <p class="mb-3 mt-1 text-sm text-slate-500">Used across navigation, the login page and every generated document (certificates, report cards, hall tickets). Square PNG/JPG/WebP recommended, max 5 MB.</p>
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
            <?php if ($logoFile !== ''): ?>
                <img src="<?= e(url('/media/avatar/' . pathinfo($logoFile, PATHINFO_FILENAME))) ?>" alt="School logo" class="h-full w-full object-contain" onerror="this.style.display='none'">
            <?php else: ?>
                <i class="fa-solid fa-school text-2xl text-slate-300"></i>
            <?php endif; ?>
        </div>
        <?php if (auth()->can('settings.manage')): ?>
        <form method="POST" action="<?= e(url('/admin/settings/logo')) ?>" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2" data-loading>
            <?= csrf_field() ?>
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" required class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> <?= $logoFile !== '' ? 'Replace' : 'Upload' ?> Logo</button>
        </form>
        <?php if ($logoFile !== ''): ?>
        <form method="POST" action="<?= e(url('/admin/settings/logo')) ?>" data-confirm="Remove the school logo? The default will be used.">
            <?= csrf_field() ?><?= method_field('DELETE') ?>
            <button class="text-xs font-medium text-red-600 hover:underline"><i class="fa-solid fa-trash"></i> Remove</button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<form method="POST" action="<?= e(url('/admin/settings')) ?>" class="space-y-6" data-loading>
    <?= csrf_field() ?>
    <?php if (empty($grouped)): ?>
        <div class="card p-8 text-center text-slate-400">No settings found. Run the seeder to create defaults.</div>
    <?php else: foreach ($grouped as $group => $items): ?>
    <div class="card p-6">
        <h2 class="mb-4 font-semibold capitalize text-slate-800"><?= e($group) ?></h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <?php foreach ($items as $s): ?>
            <div>
                <label class="form-label" for="s_<?= e((string) $s['key']) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $s['key']))) ?></label>
                <input id="s_<?= e((string) $s['key']) ?>" name="settings[<?= e((string) $s['key']) ?>]" class="form-input" value="<?= e((string) ($s['value'] ?? '')) ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; endif; ?>
    <?php if (!empty($grouped) && auth()->can('settings.manage')): ?>
    <div class="text-right"><button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save settings</button></div>
    <?php endif; ?>
</form>
<?php $this->endSection(); ?>
