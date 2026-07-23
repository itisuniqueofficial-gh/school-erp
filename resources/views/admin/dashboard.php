<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $overview */
/** @var float $attendanceRate */
/** @var array{labels:string[],values:float[]} $feeTrend */
/** @var array{labels:string[],values:int[]} $studentsByClass */
/** @var array<int,array<string,mixed>> $activity */
/** @var array<int,array<string,mixed>> $notices */
/** @var array<int,array<string,mixed>> $attention */
$currency = (string) config('app.currency', '₹');
?>
<?php $this->extend('layouts.app'); ?>

<?php $this->section('breadcrumb'); ?>Overview of your institution<?php $this->endSection(); ?>

<?php $this->section('main'); ?>
<?php if (!empty($attention)): ?>
<?php $sevBadge = ['critical' => 'border-red-200 bg-red-50 text-red-700', 'warning' => 'border-amber-200 bg-amber-50 text-amber-800', 'info' => 'border-blue-200 bg-blue-50 text-blue-700']; ?>
<div class="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-3 flex items-center gap-2">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
        <h2 class="font-semibold text-slate-800">Needs your attention</h2>
        <span class="ml-auto text-xs text-slate-400">The ERP checks your setup and suggests the next step.</span>
    </div>
    <ul class="space-y-2">
        <?php foreach ($attention as $a): ?>
        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-4 py-2.5 text-sm <?= $sevBadge[$a['severity']] ?? 'border-slate-200 bg-slate-50 text-slate-700' ?>">
            <span><i class="fa-solid <?= ($a['severity'] ?? '') === 'critical' ? 'fa-circle-exclamation' : 'fa-triangle-exclamation' ?>"></i>
                <b><?= (int) $a['count'] ?></b> &middot; <?= e((string) $a['label']) ?></span>
            <a href="<?= e(url((string) $a['link'])) ?>" class="inline-flex items-center gap-1 rounded-md bg-white/70 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-white">Fix now <i class="fa-solid fa-arrow-right"></i></a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<?php
$cards = [
    ['Students', $overview['students'], 'fa-user-graduate', 'bg-blue-50 text-blue-600'],
    ['Teachers', $overview['teachers'], 'fa-chalkboard-user', 'bg-emerald-50 text-emerald-600'],
    ['Total Staff', $overview['staff'], 'fa-users', 'bg-violet-50 text-violet-600'],
    ['Classes', $overview['classes'], 'fa-school', 'bg-amber-50 text-amber-600'],
];
?>
<!-- KPI cards -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as [$label, $value, $icon, $tone]): ?>
    <div class="card p-5" data-animate-card>
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500"><?= e($label) ?></p>
                <p class="mt-1 text-3xl font-bold text-slate-900"><?= number_format((float) $value) ?></p>
            </div>
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl <?= $tone ?>">
                <i class="fa-solid <?= e($icon) ?> text-lg"></i>
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Secondary KPIs -->
<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="card p-5" data-animate-card>
        <p class="text-sm font-medium text-slate-500">Fees collected today</p>
        <p class="mt-1 text-2xl font-bold text-slate-900"><?= e($currency) ?><?= number_format((float) $overview['fees_today'], 2) ?></p>
    </div>
    <div class="card p-5" data-animate-card>
        <p class="text-sm font-medium text-slate-500">Fees this month</p>
        <p class="mt-1 text-2xl font-bold text-slate-900"><?= e($currency) ?><?= number_format((float) $overview['fees_month'], 2) ?></p>
    </div>
    <div class="card p-5" data-animate-card>
        <p class="text-sm font-medium text-slate-500">Attendance today</p>
        <p class="mt-1 text-2xl font-bold text-slate-900"><?= e((string) $attendanceRate) ?>%</p>
        <div class="mt-2 h-2 w-full rounded-full bg-slate-100">
            <div class="h-2 rounded-full bg-brand-600" style="width: <?= (float) $attendanceRate ?>%"></div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">Fee collection trend</h2>
        <canvas id="feeChart" height="140"></canvas>
    </div>
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">Students by class</h2>
        <canvas id="classChart" height="140"></canvas>
    </div>
</div>

<!-- Activity + notices -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="card p-5 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Recent activity</h2>
        <?php if (empty($activity)): ?>
            <p class="text-sm text-slate-400">No recent activity.</p>
        <?php else: ?>
        <ol class="relative space-y-4 border-l border-slate-200 pl-5">
            <?php foreach ($activity as $item): ?>
            <li>
                <span class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full bg-brand-500"></span>
                <p class="text-sm text-slate-700">
                    <span class="font-medium"><?= e((string) ($item['name'] ?? 'System')) ?></span>
                    — <?= e((string) ($item['description'] ?? $item['action'])) ?>
                </p>
                <p class="text-xs text-slate-400"><?= e((string) ($item['created_at'] ?? '')) ?></p>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">Notices</h2>
        <?php if (empty($notices)): ?>
            <p class="text-sm text-slate-400">No notices published.</p>
        <?php else: ?>
        <ul class="space-y-3">
            <?php foreach ($notices as $notice): ?>
            <li class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                <p class="text-sm font-medium text-slate-800"><?= e((string) $notice['title']) ?></p>
                <p class="mt-0.5 line-clamp-2 text-xs text-slate-500"><?= e(mb_substr(strip_tags((string) $notice['body']), 0, 100)) ?></p>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>

<?php $this->section('page_scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        const feeData = <?= json_encode($feeTrend) ?>;
        const classData = <?= json_encode($studentsByClass) ?>;
        const brand = '#2563eb';

        if (window.Chart && document.getElementById('feeChart')) {
            new Chart(document.getElementById('feeChart'), {
                type: 'line',
                data: { labels: feeData.labels, datasets: [{ label: 'Fees', data: feeData.values, borderColor: brand, backgroundColor: 'rgba(37,99,235,0.1)', fill: true, tension: 0.35 }] },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }
        if (window.Chart && document.getElementById('classChart')) {
            new Chart(document.getElementById('classChart'), {
                type: 'bar',
                data: { labels: classData.labels, datasets: [{ label: 'Students', data: classData.values, backgroundColor: brand, borderRadius: 6 }] },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }
    })();
</script>
<?php $this->endSection(); ?>
