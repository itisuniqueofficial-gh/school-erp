<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $exam */
/** @var array<int,array<string,mixed>> $cards */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/exams')) ?>" class="hover:text-brand-600">Exams</a> / Report Cards<?php $this->endSection(); ?>
<?php $this->section('page_actions'); ?>
<button onclick="window.print()" class="btn btn-secondary no-print"><i class="fa-solid fa-print"></i> Print</button>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="font-semibold text-slate-800"><?= e((string) $exam['name']) ?> — Ranked Results</h2>
    </div>
    <table class="data-table">
        <thead><tr><th>Rank</th><th>Admission</th><th>Student</th><th>Obtained</th><th>Total</th><th>%</th><th>Result</th></tr></thead>
        <tbody>
        <?php if (empty($cards)): ?><tr><td colspan="7" class="py-8 text-center text-slate-400">No marks entered yet.</td></tr>
        <?php else: foreach ($cards as $i => $c): ?>
            <tr>
                <td class="font-bold text-slate-800">#<?= $i + 1 ?></td>
                <td class="font-mono text-xs"><?= e((string) $c['admission_no']) ?></td>
                <td class="font-medium text-slate-800"><?= e((string) $c['name']) ?></td>
                <td><?= e((string) $c['obtained_marks']) ?></td>
                <td><?= e((string) $c['total_marks']) ?></td>
                <td><?= e((string) $c['percentage']) ?>%</td>
                <td><span class="badge <?= $c['result'] === 'pass' ? 'badge-green' : 'badge-red' ?>"><?= e(ucfirst((string) $c['result'])) ?></span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php $this->endSection(); ?>
