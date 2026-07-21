<?php /** @var \Core\View\View $this */ /** @var array<int,array<string,mixed>> $invoices */ ?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<?php $currency = (string) config('app.currency', '₹'); ?>
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Invoice</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th>Due Date</th></tr></thead>
            <tbody>
            <?php if (empty($invoices)): ?>
                <tr><td colspan="6" class="py-8 text-center text-slate-400">No invoices found.</td></tr>
            <?php else: foreach ($invoices as $inv):
                $due = (float) $inv['total_amount'] + (float) $inv['fine_amount'] - (float) $inv['discount_amount'] - (float) $inv['paid_amount'];
                $map = ['paid'=>'badge-green','partial'=>'badge-amber','unpaid'=>'badge-slate','overdue'=>'badge-red','cancelled'=>'badge-slate'];
            ?>
                <tr>
                    <td class="font-medium text-slate-800"><?= e((string) $inv['invoice_no']) ?></td>
                    <td><?= e($currency) ?><?= number_format((float) $inv['total_amount'], 2) ?></td>
                    <td><?= e($currency) ?><?= number_format((float) $inv['paid_amount'], 2) ?></td>
                    <td class="font-semibold"><?= e($currency) ?><?= number_format(max(0, $due), 2) ?></td>
                    <td><span class="badge <?= $map[$inv['status']] ?? 'badge-slate' ?>"><?= e(ucfirst((string) $inv['status'])) ?></span></td>
                    <td class="text-slate-500"><?= e((string) ($inv['due_date'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
