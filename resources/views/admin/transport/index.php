<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $routes */
/** @var array<int,array<string,mixed>> $vehicles */
/** @var array<int,array<string,mixed>> $assignments */
$cur = (string) config('app.currency', '₹');
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid gap-6 lg:grid-cols-2">
    <!-- Routes -->
    <div class="card overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold text-slate-800">Routes</h2></div>
        <table class="data-table">
            <thead><tr><th>Name</th><th>From → To</th><th>Fare</th></tr></thead>
            <tbody>
            <?php if (empty($routes)): ?><tr><td colspan="3" class="py-6 text-center text-slate-400">No routes.</td></tr>
            <?php else: foreach ($routes as $r): ?>
                <tr><td class="font-medium text-slate-800"><?= e((string) $r['name']) ?></td><td class="text-slate-500 text-xs"><?= e((string) ($r['start_point'] ?? '—')) ?> → <?= e((string) ($r['end_point'] ?? '—')) ?></td><td><?= e($cur) ?><?= number_format((float) $r['fare'], 2) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php if (auth()->can('transport.manage')): ?>
        <form method="POST" action="<?= e(url('/admin/transport/routes')) ?>" class="border-t border-slate-200 p-4 grid grid-cols-2 gap-2" data-loading>
            <?= csrf_field() ?>
            <input name="name" class="form-input col-span-2" placeholder="Route name" required>
            <input name="start_point" class="form-input" placeholder="Start">
            <input name="end_point" class="form-input" placeholder="End">
            <input name="fare" type="number" step="0.01" class="form-input" placeholder="Fare">
            <button class="btn btn-primary justify-center">Add route</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Vehicles -->
    <div class="card overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold text-slate-800">Vehicles</h2></div>
        <table class="data-table">
            <thead><tr><th>Reg. No</th><th>Driver</th><th>Route</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($vehicles)): ?><tr><td colspan="4" class="py-6 text-center text-slate-400">No vehicles.</td></tr>
            <?php else: foreach ($vehicles as $v): ?>
                <tr><td class="font-mono text-xs"><?= e((string) $v['registration_no']) ?></td><td><?= e((string) ($v['driver_name'] ?? '—')) ?></td><td><?= e((string) ($v['route_name'] ?? '—')) ?></td><td><span class="badge badge-green"><?= e((string) $v['status']) ?></span></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php if (auth()->can('transport.manage')): ?>
        <form method="POST" action="<?= e(url('/admin/transport/vehicles')) ?>" class="border-t border-slate-200 p-4 grid grid-cols-2 gap-2" data-loading>
            <?= csrf_field() ?>
            <input name="registration_no" class="form-input" placeholder="Reg. no" required>
            <input name="model" class="form-input" placeholder="Model">
            <input name="driver_name" class="form-input" placeholder="Driver name">
            <input name="driver_phone" class="form-input" placeholder="Driver phone">
            <select name="route_id" class="form-select col-span-2"><option value="">No route</option><?php foreach($routes as $r):?><option value="<?=(int)$r['id']?>"><?=e((string)$r['name'])?></option><?php endforeach;?></select>
            <button class="btn btn-primary col-span-2 justify-center">Add vehicle</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Assignments -->
    <div class="card overflow-hidden lg:col-span-2">
        <div class="border-b border-slate-200 px-5 py-3 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800">Student allocations</h2>
            <?php if (auth()->can('transport.manage')): ?>
            <form method="POST" action="<?= e(url('/admin/transport/assign')) ?>" class="flex gap-2" data-loading>
                <?= csrf_field() ?>
                <input name="student_id" type="number" class="form-input w-32" placeholder="Student ID" required>
                <select name="route_id" class="form-select w-40" required><option value="">Route</option><?php foreach($routes as $r):?><option value="<?=(int)$r['id']?>"><?=e((string)$r['name'])?></option><?php endforeach;?></select>
                <button class="btn btn-secondary">Assign</button>
            </form>
            <?php endif; ?>
        </div>
        <table class="data-table">
            <thead><tr><th>Student</th><th>Route</th><th>Pickup</th></tr></thead>
            <tbody>
            <?php if (empty($assignments)): ?><tr><td colspan="3" class="py-6 text-center text-slate-400">No allocations.</td></tr>
            <?php else: foreach ($assignments as $a): ?>
                <tr><td class="font-medium text-slate-800"><?= e((string) (($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></td><td><?= e((string) ($a['route_name'] ?? '—')) ?></td><td class="text-slate-500"><?= e((string) ($a['pickup_point'] ?? '—')) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
