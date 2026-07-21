<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $receipt */
$cur = (string) config('app.currency', '₹');
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<button onclick="window.print()" class="btn btn-secondary no-print"><i class="fa-solid fa-print"></i> Print</button>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="mx-auto max-w-2xl">
    <div class="card p-8">
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900"><?= e((string) config('app.name')) ?></h2>
                <p class="text-sm text-slate-500">Fee Payment Receipt</p>
            </div>
            <div class="text-right">
                <p class="font-mono text-sm font-semibold"><?= e((string) $receipt['receipt_no']) ?></p>
                <p class="text-xs text-slate-500"><?= e((string) $receipt['payment_date']) ?></p>
            </div>
        </div>
        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
            <div><dt class="text-slate-400">Student</dt><dd class="font-medium text-slate-800"><?= e((string) (($receipt['first_name'] ?? '') . ' ' . ($receipt['last_name'] ?? ''))) ?></dd></div>
            <div><dt class="text-slate-400">Admission No</dt><dd class="font-medium text-slate-800"><?= e((string) ($receipt['admission_no'] ?? '—')) ?></dd></div>
            <div><dt class="text-slate-400">Invoice</dt><dd class="font-medium text-slate-800"><?= e((string) ($receipt['invoice_no'] ?? '—')) ?></dd></div>
            <div><dt class="text-slate-400">Method</dt><dd class="font-medium text-slate-800"><?= e(ucfirst(str_replace('_', ' ', (string) $receipt['method']))) ?></dd></div>
        </dl>
        <div class="mt-6 flex items-center justify-between rounded-lg bg-slate-50 px-5 py-4">
            <span class="text-sm font-medium text-slate-600">Amount Paid</span>
            <span class="text-2xl font-bold text-slate-900"><?= e($cur) ?><?= number_format((float) $receipt['amount'], 2) ?></span>
        </div>
        <p class="mt-6 text-center text-xs text-slate-400">This is a computer-generated receipt.</p>
    </div>
</div>
<?php $this->endSection(); ?>
