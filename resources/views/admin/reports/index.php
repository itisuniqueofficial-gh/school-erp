<?php
/** @var \Core\View\View $this */
/** @var string[] $types */
$meta = [
    'attendance' => ['fa-clipboard-check', 'bg-emerald-50 text-emerald-600', 'Daily and periodic attendance summaries by class, section and student.'],
    'fees'       => ['fa-file-invoice-dollar', 'bg-amber-50 text-amber-600', 'Collections, outstanding dues and payment history with export.'],
    'academic'   => ['fa-award', 'bg-violet-50 text-violet-600', 'Exam performance, grades and report-card analytics.'],
    'students'   => ['fa-user-graduate', 'bg-blue-50 text-blue-600', 'Enrolment, demographics and status across the institution.'],
    'staff'      => ['fa-users', 'bg-rose-50 text-rose-600', 'Staff directory, roles and headcount reporting.'],
];
?>
<?php $this->extend('layouts.app'); ?>

<?php $this->section('breadcrumb'); ?>Generate and export institutional reports<?php $this->endSection(); ?>

<?php $this->section('main'); ?>
<div class="reports-grid">
    <?php foreach ($types as $t):
        [$icon, $tone, $desc] = $meta[$t] ?? ['fa-chart-line', 'bg-brand-50 text-brand-600', 'View and export the ' . $t . ' report.'];
    ?>
    <a href="<?= e(url('/admin/reports/' . $t)) ?>"
       class="card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
       data-animate-card>
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg <?= $tone ?>">
            <i class="fa-solid <?= e($icon) ?> text-lg"></i>
        </span>
        <h3 class="mt-4 text-base font-semibold capitalize text-slate-900"><?= e($t) ?> report</h3>
        <p class="mt-1 flex-1 text-sm text-slate-500"><?= e($desc) ?></p>
        <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600">
            Open report <i class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-0.5"></i>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<style>
    /* Auto-fit grid keeps cards a readable width at every viewport and avoids
       the large empty gaps that a fixed 3-column grid leaves on wide screens. */
    .reports-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
    }
</style>
<?php $this->endSection(); ?>
