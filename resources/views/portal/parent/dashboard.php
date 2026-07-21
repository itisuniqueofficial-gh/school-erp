<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $children */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php if (empty($children)): ?>
        <div class="card p-8 text-center sm:col-span-2 lg:col-span-3">
            <i class="fa-solid fa-children mb-3 text-4xl text-slate-300"></i>
            <p class="text-slate-500">No children linked to your account yet. Please contact the school office.</p>
        </div>
    <?php else: foreach ($children as $child): ?>
        <div class="card p-5" data-animate-card>
            <div class="flex items-center gap-3">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 text-lg font-semibold text-white">
                    <?= e(strtoupper(substr((string) $child['first_name'], 0, 1))) ?>
                </span>
                <div>
                    <p class="font-semibold text-slate-800"><?= e((string) ($child['first_name'] . ' ' . $child['last_name'])) ?></p>
                    <p class="text-xs text-slate-500">Adm: <?= e((string) $child['admission_no']) ?></p>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <a href="<?= e(url('/parent/fees')) ?>" class="badge badge-blue">Fees</a>
                <span class="badge badge-<?= ($child['status'] ?? '') === 'active' ? 'green' : 'slate' ?>"><?= e(ucfirst((string) $child['status'])) ?></span>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php $this->endSection(); ?>
