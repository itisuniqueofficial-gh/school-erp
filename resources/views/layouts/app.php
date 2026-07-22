<?php /** @var \Core\View\View $this */ ?>
<?php $this->extend('layouts.base'); ?>

<?php $this->section('content'); ?>
<?php $user = auth()->user() ?? []; ?>
<div class="min-h-full lg:flex">
    <!-- Sidebar backdrop (mobile) -->
    <div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-slate-900/40 lg:hidden"></div>

    <!-- Sidebar -->
    <aside data-sidebar
           class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transform overflow-y-auto border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0"
           aria-label="Sidebar">
        <?php $this->include('partials.sidebar', ['user' => $user]); ?>
    </aside>

    <!-- Main column -->
    <div class="flex min-w-0 flex-1 flex-col">
        <?php $this->include('partials.navbar', ['user' => $user]); ?>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Breadcrumb / page header -->
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900"><?= e($this->yield('page_title', $title ?? 'Dashboard')) ?></h1>
                    <?php if ($this->hasSection('breadcrumb')): ?>
                        <nav class="mt-1 text-sm text-slate-500" aria-label="Breadcrumb"><?= $this->yield('breadcrumb') ?></nav>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2"><?= $this->yield('page_actions') ?></div>
            </div>

            <?php $this->include('partials.flash'); ?>

            <?= $this->yield('main') ?>
        </main>

        <footer class="border-t border-slate-200 px-4 py-4 text-center text-xs text-slate-400 sm:px-6 lg:px-8">
            <p><?= e(config('app.name')) ?> v<?= e(config('app.version')) ?> &middot; &copy; <?= date('Y') ?></p>
            <p class="mt-1">Made with <i class="fa-solid fa-heart text-rose-500" aria-label="love"></i> by
                <a href="https://itisuniqueofficial.com" target="_blank" rel="noopener noreferrer" class="font-medium text-slate-500 hover:text-brand-600">It Is Unique Official</a>
            </p>
        </footer>
    </div>
</div>
<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?><?= $this->yield('page_scripts') ?><?php $this->endSection(); ?>
