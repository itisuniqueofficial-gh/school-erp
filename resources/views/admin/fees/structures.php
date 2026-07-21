<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $structures */
/** @var array<int,array<string,mixed>> $classes */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/fees')) ?>" class="hover:text-brand-600">Fees</a> / Structures<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Class</th><th>Frequency</th></tr></thead>
                <tbody>
                <?php if (empty($structures)): ?><tr><td colspan="3" class="py-8 text-center text-slate-400">No structures.</td></tr>
                <?php else: foreach ($structures as $s): ?>
                    <tr><td class="font-medium text-slate-800"><?= e((string) $s['name']) ?></td><td><?= e((string) ($s['class_name'] ?? 'All')) ?></td><td><span class="badge badge-slate"><?= e(str_replace('_', ' ', (string) $s['frequency'])) ?></span></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (auth()->can('fees.manage')): ?>
    <div class="card p-5">
        <h2 class="font-semibold text-slate-800">Add structure</h2>
        <form method="POST" action="<?= e(url('/admin/fees/structures')) ?>" class="mt-4 space-y-3" data-loading>
            <?= csrf_field() ?>
            <div><label class="form-label required" for="name">Name</label><input id="name" name="name" class="form-input" required></div>
            <div><label class="form-label" for="class_id">Class</label><select id="class_id" name="class_id" class="form-select"><option value="">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>"><?=e((string)$c['name'])?></option><?php endforeach;?></select></div>
            <div><label class="form-label required" for="frequency">Frequency</label><select id="frequency" name="frequency" class="form-select" required><?php foreach(['monthly','quarterly','half_yearly','yearly','one_time'] as $f):?><option value="<?=$f?>"><?=ucfirst(str_replace('_',' ',$f))?></option><?php endforeach;?></select></div>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add structure</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
