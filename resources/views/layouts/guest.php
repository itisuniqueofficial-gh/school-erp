<?php /** @var \Core\View\View $this */ ?>
<?php $this->extend('layouts.base'); ?>

<?php $this->section('content'); ?>
<div class="flex min-h-full">
    <!-- Left: brand panel (hidden on small screens) -->
    <div class="relative hidden w-1/2 bg-brand-600 lg:block">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-600 to-brand-800"></div>
        <div class="relative flex h-full flex-col justify-between p-12 text-white">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-xl">
                    <i class="fa-solid fa-graduation-cap"></i>
                </span>
                <span class="text-xl font-bold"><?= e(config('app.name')) ?></span>
            </div>
            <div data-animate="brand">
                <h2 class="text-3xl font-extrabold leading-tight">Empowering education,<br>simplifying administration.</h2>
                <p class="mt-4 max-w-md text-brand-100">
                    Manage admissions, attendance, fees, exams and more from a single secure platform.
                </p>
            </div>
            <p class="text-sm text-brand-200">&copy; <?= date('Y') ?> <?= e(config('app.name')) ?></p>
        </div>
    </div>

    <!-- Right: form -->
    <div class="flex w-full items-center justify-center px-6 py-12 lg:w-1/2">
        <div class="w-full max-w-md" data-animate="form">
            <div class="mb-8 lg:hidden">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-xl text-white">
                    <i class="fa-solid fa-graduation-cap"></i>
                </span>
            </div>

            <?php if ($msg = flash('success')): ?>
                <div data-flash role="status" class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <i class="fa-solid fa-circle-check mr-1"></i> <?= e((string) $msg) ?>
                </div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div data-flash role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= e((string) $msg) ?>
                </div>
            <?php endif; ?>

            <?= $this->yield('form') ?>

            <p class="mt-8 text-center text-xs text-slate-400">
                Made with <i class="fa-solid fa-heart text-rose-500" aria-label="love"></i> by
                <a href="https://itisuniqueofficial.com" target="_blank" rel="noopener noreferrer" class="font-medium text-slate-500 hover:text-brand-600">It Is Unique Official</a>
            </p>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>

<?php // Combine the layout's own animations with any page-specific scripts. ?>
<?php $this->section('scripts'); ?>
<script>
    if (window.ERPAnim) {
        ERPAnim.fadeUp('[data-animate="form"]', { y: 20, duration: 0.5 });
        ERPAnim.fadeUp('[data-animate="brand"]', { y: 20, duration: 0.6, delay: 0.1 });
    }
</script>
<?= $this->yield('form_scripts') ?>
<?php $this->endSection(); ?>
