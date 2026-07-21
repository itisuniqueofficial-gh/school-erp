<?php
/** @var \Core\View\View $this */
/** @var string[] $types */
$icons = ['attendance' => 'fa-clipboard-check', 'fees' => 'fa-file-invoice-dollar', 'academic' => 'fa-award', 'students' => 'fa-user-graduate', 'staff' => 'fa-users'];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($types as $t): ?>
    <a href="<?= e(url('/admin/reports/' . $t)) ?>" class="card p-5 transition hover:shadow-sm" data-animate-card>
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid <?= e($icons[$t] ?? 'fa-chart-line') ?>"></i></span>
        <h3 class="mt-3 font-semibold capitalize text-slate-900"><?= e($t) ?> report</h3>
        <p class="mt-1 text-sm text-slate-500">View and export the <?= e($t) ?> report.</p>
    </a>
    <?php endforeach; ?>
</div>
<?php $this->endSection(); ?>
