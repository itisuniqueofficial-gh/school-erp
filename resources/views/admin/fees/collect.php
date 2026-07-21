<?php
/** @var \Core\View\View $this */
/** @var string $search */
/** @var array<string,mixed>|null $student */
/** @var array<int,array<string,mixed>> $invoices */
$cur = (string) config('app.currency', '₹');
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/fees')) ?>" class="hover:text-brand-600">Fees</a> / Collect<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<form method="GET" class="card mb-4 p-4">
    <div class="flex gap-3">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by admission no or name" class="form-input">
        <button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Find</button>
    </div>
</form>

<?php if ($search !== '' && $student === null): ?>
    <div class="card p-6 text-center text-slate-400">No student found for "<?= e($search) ?>".</div>
<?php elseif ($student !== null): ?>
<div class="card mb-4 p-5">
    <h2 class="font-semibold text-slate-800"><?= e((string) ($student['first_name'] . ' ' . $student['last_name'])) ?></h2>
    <p class="text-sm text-slate-500">Admission: <?= e((string) $student['admission_no']) ?></p>
</div>

<div class="card overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-800">Invoices</h2></div>
    <table class="data-table">
        <thead><tr><th>Invoice</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th class="text-right">Collect</th></tr></thead>
        <tbody>
        <?php if (empty($invoices)): ?><tr><td colspan="6" class="py-8 text-center text-slate-400">No invoices.</td></tr>
        <?php else: foreach ($invoices as $inv):
            $due = (float) $inv['total_amount'] + (float) $inv['fine_amount'] - (float) $inv['discount_amount'] - (float) $inv['paid_amount'];
            $map = ['paid'=>'badge-green','partial'=>'badge-amber','unpaid'=>'badge-slate','overdue'=>'badge-red','cancelled'=>'badge-slate'];
        ?>
            <tr>
                <td class="font-mono text-xs"><?= e((string) $inv['invoice_no']) ?></td>
                <td><?= e($cur) ?><?= number_format((float) $inv['total_amount'], 2) ?></td>
                <td><?= e($cur) ?><?= number_format((float) $inv['paid_amount'], 2) ?></td>
                <td class="font-semibold"><?= e($cur) ?><?= number_format(max(0, $due), 2) ?></td>
                <td><span class="badge <?= $map[$inv['status']] ?? 'badge-slate' ?>"><?= e(ucfirst((string) $inv['status'])) ?></span></td>
                <td class="text-right">
                    <?php if ($due > 0 && auth()->can('fees.collect')): ?>
                    <button type="button" class="btn btn-secondary" data-pay data-invoice="<?= (int) $inv['id'] ?>" data-due="<?= number_format(max(0, $due), 2, '.', '') ?>">Pay</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Payment modal -->
<div id="payModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800">Record payment</h3>
        <form method="POST" action="<?= e(url('/admin/fees/collect')) ?>" class="mt-4 space-y-3" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
            <input type="hidden" name="fee_invoice_id" id="pay_invoice">
            <div><label class="form-label required" for="amount">Amount</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" class="form-input" required></div>
            <div><label class="form-label required" for="method">Method</label><select id="method" name="method" class="form-select" required><?php foreach(['cash','card','bank_transfer','cheque','online','upi'] as $m):?><option value="<?=$m?>"><?=ucfirst(str_replace('_',' ',$m))?></option><?php endforeach;?></select></div>
            <div><label class="form-label" for="reference">Reference</label><input id="reference" name="reference" class="form-input"></div>
            <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close-pay>Cancel</button><button class="btn btn-primary">Save payment</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>

<?php $this->section('page_scripts'); ?>
<script>
$(function(){
    $('[data-pay]').on('click', function(){
        $('#pay_invoice').val($(this).data('invoice'));
        $('#amount').val($(this).data('due'));
        $('#payModal').removeClass('hidden').addClass('flex');
    });
    $('[data-close-pay]').on('click', function(){ $('#payModal').addClass('hidden').removeClass('flex'); });
});
</script>
<?php $this->endSection(); ?>
