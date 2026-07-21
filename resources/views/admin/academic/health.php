<?php
/** @var \Core\View\View $this */
/** @var array<int,array{key:string,label:string,count:int,severity:string,link:string}> $checks */
/** @var string $status */
$tone = ['critical' => 'badge-red', 'warning' => 'badge-amber', 'healthy' => 'badge-green'];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="card mb-6 p-5">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-slate-800">System Health</h2>
            <p class="text-sm text-slate-500">Automated academic data-integrity checks.</p>
        </div>
        <span class="badge <?= $tone[$status] ?? 'badge-slate' ?> text-sm"><?= e(strtoupper($status)) ?></span>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <?php foreach ($checks as $c): $ok = $c['count'] === 0; ?>
    <a href="<?= e(url($c['link'])) ?>" class="card p-5 transition hover:shadow-sm" data-animate-card>
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-700"><?= e($c['label']) ?></p>
                <p class="mt-1 text-2xl font-bold <?= $ok ? 'text-green-600' : ($c['severity'] === 'critical' ? 'text-red-600' : 'text-amber-600') ?>"><?= (int) $c['count'] ?></p>
            </div>
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg <?= $ok ? 'bg-green-50 text-green-600' : ($c['severity'] === 'critical' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600') ?>">
                <i class="fa-solid <?= $ok ? 'fa-check' : 'fa-triangle-exclamation' ?>"></i>
            </span>
        </div>
        <?php if (!$ok): ?><p class="mt-2 text-xs font-medium text-brand-600">Click to resolve →</p><?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>
<?php $this->endSection(); ?>
