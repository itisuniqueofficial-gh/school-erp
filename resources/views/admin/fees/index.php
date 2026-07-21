<?php
/** @var \Core\View\View $this */
/** @var float $collectedToday */
/** @var float $collectedMonth */
/** @var float $outstanding */
/** @var array<int,array<string,mixed>> $recent */
$cur = (string) config('app.currency', '₹');
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<?php if (auth()->can('fees.collect')): ?><a href="<?= e(url('/admin/fees/collect')) ?>" class="btn btn-primary"><i class="fa-solid fa-hand-holding-dollar"></i> Collect Fees</a><?php endif; ?>
<a href="<?= e(url('/admin/fees/structures')) ?>" class="btn btn-secondary"><i class="fa-solid fa-layer-group"></i> Structures</a>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="card p-5" data-animate-card><p class="text-sm text-slate-500">Collected today</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= e($cur) ?><?= number_format($collectedToday, 2) ?></p></div>
    <div class="card p-5" data-animate-card><p class="text-sm text-slate-500">Collected this month</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= e($cur) ?><?= number_format($collectedMonth, 2) ?></p></div>
    <div class="card p-5" data-animate-card><p class="text-sm text-slate-500">Outstanding</p><p class="mt-1 text-2xl font-bold text-red-600"><?= e($cur) ?><?= number_format($outstanding, 2) ?></p></div>
</div>
<div class="card mt-6 overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-800">Recent payments</h2></div>
    <table class="data-table">
        <thead><tr><th>Receipt</th><th>Student</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($recent)): ?><tr><td colspan="5" class="py-8 text-center text-slate-400">No payments yet.</td></tr>
        <?php else: foreach ($recent as $p): ?>
            <tr>
                <td class="font-mono text-xs"><a href="<?= e(url('/admin/fees/receipt/' . $p['id'])) ?>" class="text-brand-600 hover:underline"><?= e((string) $p['receipt_no']) ?></a></td>
                <td><?= e((string) (($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) ?></td>
                <td><?= e($cur) ?><?= number_format((float) $p['amount'], 2) ?></td>
                <td><span class="badge badge-slate"><?= e(str_replace('_', ' ', (string) $p['method'])) ?></span></td>
                <td class="text-slate-500"><?= e((string) $p['payment_date']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php $this->endSection(); ?>
