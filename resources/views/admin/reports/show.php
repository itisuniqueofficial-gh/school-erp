<?php
/** @var \Core\View\View $this */
/** @var string $type */
/** @var array{title:string,columns:string[],rows:array<int,array<string,mixed>>} $report */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/reports')) ?>" class="hover:text-brand-600">Reports</a> / <?= e(ucfirst($type)) ?><?php $this->endSection(); ?>
<?php $this->section('page_actions'); ?>
<a href="<?= e(url('/admin/reports/' . $type . '/export')) ?>" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
<button onclick="window.print()" class="btn btn-secondary no-print"><i class="fa-solid fa-print"></i> Print</button>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-800"><?= e($report['title']) ?></h2></div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><?php foreach ($report['columns'] as $col): ?><th><?= e(ucwords(str_replace('_', ' ', $col))) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php if (empty($report['rows'])): ?><tr><td colspan="<?= count($report['columns']) ?>" class="py-8 text-center text-slate-400">No data.</td></tr>
            <?php else: foreach ($report['rows'] as $row): ?>
                <tr><?php foreach ($report['columns'] as $col): ?><td><?= e((string) ($row[$col] ?? '')) ?></td><?php endforeach; ?></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
