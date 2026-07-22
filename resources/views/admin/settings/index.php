<?php
/** @var \Core\View\View $this */
/** @var array<string,array<int,array<string,mixed>>> $grouped */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<a href="<?= e(url('/admin/settings/institution')) ?>" class="btn btn-secondary"><i class="fa-solid fa-sliders"></i> ERP Configuration</a>
<a href="<?= e(url('/admin/settings/payments')) ?>" class="btn btn-secondary"><i class="fa-solid fa-credit-card"></i> Payment Gateways</a>
<?php if (auth()->can('settings.manage')): ?>
<form method="POST" action="<?= e(url('/admin/settings/backup')) ?>" class="inline">
    <?= csrf_field() ?>
    <button class="btn btn-secondary" data-confirm="Create a database backup now?"><i class="fa-solid fa-database"></i> Backup DB</button>
</form>
<?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
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
