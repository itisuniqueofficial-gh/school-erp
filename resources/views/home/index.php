<?php /** @var \Core\View\View $this */ ?>
<?php $this->extend('layouts.base'); ?>

<?php $this->section('title'); ?><?= e(config('app.name')) ?> — Modern School Management<?php $this->endSection(); ?>

<?php $this->section('content'); ?>
<div class="min-h-full">
    <!-- Nav -->
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8" aria-label="Primary">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white">
                    <i class="fa-solid fa-graduation-cap"></i>
                </span>
                <span class="text-lg font-bold text-slate-900"><?= e(config('app.name')) ?></span>
            </div>
            <a href="<?= e(url('/login')) ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                <i class="fa-solid fa-right-to-bracket"></i> Sign in
            </a>
        </nav>
    </header>

    <!-- Hero -->
    <main>
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div data-animate="hero">
                    <span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
                        <i class="fa-solid fa-bolt mr-1"></i> Version <?= e(config('app.version')) ?>
                    </span>
                    <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">
                        Run your school on one clean, modern platform.
                    </h1>
                    <p class="mt-4 text-lg text-slate-600">
                        Admissions, attendance, examinations, fees, library, transport and more — with role-based
                        portals for admins, teachers, students and parents.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="<?= e(url('/login')) ?>"
                           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">
                            Get started <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <a href="#modules"
                           class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Explore modules
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4" id="modules" data-animate="cards">
                    <?php
                    $features = [
                        ['fa-user-graduate', 'Students', 'Admissions, profiles, promotion'],
                        ['fa-chalkboard-user', 'Teachers', 'Profiles, timetable, attendance'],
                        ['fa-clipboard-check', 'Attendance', 'Daily marking & reports'],
                        ['fa-file-invoice-dollar', 'Fees', 'Structures, receipts, dues'],
                        ['fa-award', 'Examinations', 'Marks, grades, report cards'],
                        ['fa-book', 'Library', 'Issue, return, fines'],
                    ];
                    foreach ($features as [$icon, $name, $desc]):
                    ?>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 transition hover:shadow-sm">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <i class="fa-solid <?= e($icon) ?>"></i>
                        </span>
                        <h3 class="mt-3 font-semibold text-slate-900"><?= e($name) ?></h3>
                        <p class="mt-1 text-sm text-slate-500"><?= e($desc) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 text-center text-sm text-slate-500 sm:px-6 lg:px-8">
            &copy; <?= date('Y') ?> <?= e(config('app.name')) ?>. All rights reserved.
        </div>
    </footer>
</div>
<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
    if (window.ERPAnim) {
        ERPAnim.fadeUp('[data-animate="hero"]', { y: 24, duration: 0.6 });
        ERPAnim.staggerCards('[data-animate="cards"] > div', { y: 20, duration: 0.5, stagger: 0.08 });
    }
</script>
<?php $this->endSection(); ?>
