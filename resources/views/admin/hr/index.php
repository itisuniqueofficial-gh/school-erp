<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $leaves */
/** @var array<int,array<string,mixed>> $payrolls */
/** @var array<int,array<string,mixed>> $holidays */
/** @var array<int,array<string,mixed>> $leaveTypes */
$cur = (string) config('app.currency', '₹');
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid gap-6 lg:grid-cols-2">
    <!-- Leaves -->
    <div class="card overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold text-slate-800">Leave requests</h2></div>
        <table class="data-table">
            <thead><tr><th>Staff</th><th>Type</th><th>Days</th><th>Status</th><th class="text-right">Action</th></tr></thead>
            <tbody>
            <?php if (empty($leaves)): ?><tr><td colspan="5" class="py-6 text-center text-slate-400">No leave requests.</td></tr>
            <?php else: foreach ($leaves as $l): $m=['approved'=>'badge-green','rejected'=>'badge-red','pending'=>'badge-amber','cancelled'=>'badge-slate']; ?>
                <tr>
                    <td class="font-medium text-slate-800"><?= e((string) (($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''))) ?></td>
                    <td><?= e((string) ($l['leave_type'] ?? '—')) ?></td>
                    <td><?= (int) $l['days'] ?></td>
                    <td><span class="badge <?= $m[$l['status']] ?? 'badge-slate' ?>"><?= e(ucfirst((string) $l['status'])) ?></span></td>
                    <td class="text-right">
                        <?php if ($l['status'] === 'pending' && auth()->can('hr.manage')): ?>
                        <form method="POST" action="<?= e(url('/admin/hr/leaves/' . $l['id'] . '/status')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="status" value="approved"><button class="text-green-600 hover:underline text-sm">Approve</button></form>
                        <form method="POST" action="<?= e(url('/admin/hr/leaves/' . $l['id'] . '/status')) ?>" class="inline ml-1"><?= csrf_field() ?><input type="hidden" name="status" value="rejected"><button class="text-red-600 hover:underline text-sm">Reject</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php if (auth()->can('hr.manage')): ?>
        <form method="POST" action="<?= e(url('/admin/hr/leaves')) ?>" class="border-t border-slate-200 p-4 grid grid-cols-2 gap-2" data-loading>
            <?= csrf_field() ?>
            <input name="staff_id" type="number" class="form-input" placeholder="Staff ID" required>
            <select name="leave_type_id" class="form-select"><option value="">Type</option><?php foreach($leaveTypes as $lt):?><option value="<?=(int)$lt['id']?>"><?=e((string)$lt['name'])?></option><?php endforeach;?></select>
            <input name="start_date" type="date" class="form-input" required>
            <input name="end_date" type="date" class="form-input" required>
            <button class="btn btn-primary col-span-2 justify-center">Record leave</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Payroll -->
    <div class="card overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold text-slate-800">Payroll</h2></div>
        <table class="data-table">
            <thead><tr><th>Staff</th><th>Period</th><th>Net</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($payrolls)): ?><tr><td colspan="4" class="py-6 text-center text-slate-400">No payslips.</td></tr>
            <?php else: foreach ($payrolls as $p): ?>
                <tr><td class="font-medium text-slate-800"><?= e((string) (($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) ?></td><td><?= (int) $p['month'] ?>/<?= (int) $p['year'] ?></td><td><?= e($cur) ?><?= number_format((float) $p['net_salary'], 2) ?></td><td><span class="badge <?= $p['status'] === 'paid' ? 'badge-green' : 'badge-amber' ?>"><?= e(ucfirst((string) $p['status'])) ?></span></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php if (auth()->can('hr.manage')): ?>
        <form method="POST" action="<?= e(url('/admin/hr/payroll')) ?>" class="border-t border-slate-200 p-4 grid grid-cols-2 gap-2" data-loading>
            <?= csrf_field() ?>
            <input name="staff_id" type="number" class="form-input" placeholder="Staff ID" required>
            <input name="basic_salary" type="number" step="0.01" class="form-input" placeholder="Basic salary" required>
            <input name="month" type="number" min="1" max="12" class="form-input" placeholder="Month" required>
            <input name="year" type="number" class="form-input" placeholder="Year" value="<?= date('Y') ?>" required>
            <input name="allowances" type="number" step="0.01" class="form-input" placeholder="Allowances">
            <input name="deductions" type="number" step="0.01" class="form-input" placeholder="Deductions">
            <button class="btn btn-primary col-span-2 justify-center">Generate payslip</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Holidays -->
    <div class="card overflow-hidden lg:col-span-2">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold text-slate-800">Holidays</h2></div>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Date</th><th>Description</th></tr></thead>
            <tbody>
            <?php if (empty($holidays)): ?><tr><td colspan="3" class="py-6 text-center text-slate-400">No holidays.</td></tr>
            <?php else: foreach ($holidays as $h): ?>
                <tr><td class="font-medium text-slate-800"><?= e((string) $h['name']) ?></td><td><?= e((string) $h['date']) ?></td><td class="text-slate-500"><?= e((string) ($h['description'] ?? '')) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php if (auth()->can('hr.manage')): ?>
        <form method="POST" action="<?= e(url('/admin/hr/holidays')) ?>" class="border-t border-slate-200 p-4 grid grid-cols-3 gap-2" data-loading>
            <?= csrf_field() ?>
            <input name="name" class="form-input" placeholder="Holiday name" required>
            <input name="date" type="date" class="form-input" required>
            <input name="description" class="form-input" placeholder="Description">
            <button class="btn btn-primary col-span-3 justify-center">Add holiday</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
