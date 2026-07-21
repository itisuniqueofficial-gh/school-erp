<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $exams */
/** @var array<int,array<string,mixed>> $examTypes */
/** @var array<int,array<string,mixed>> $years */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Exam</th><th>Type</th><th>Dates</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($exams)): ?><tr><td colspan="5" class="py-8 text-center text-slate-400">No exams yet.</td></tr>
                <?php else: foreach ($exams as $ex): ?>
                    <tr>
                        <td class="font-medium text-slate-800"><?= e((string) $ex['name']) ?></td>
                        <td><?= e((string) ($ex['type_name'] ?? '—')) ?></td>
                        <td class="text-slate-500 text-xs"><?= e((string) ($ex['start_date'] ?? '—')) ?> → <?= e((string) ($ex['end_date'] ?? '—')) ?></td>
                        <td><span class="badge badge-blue"><?= e(ucfirst((string) $ex['status'])) ?></span></td>
                        <td class="text-right">
                            <a href="<?= e(url('/admin/exams/' . $ex['id'] . '/marks')) ?>" class="text-slate-500 hover:text-brand-600" title="Enter marks"><i class="fa-solid fa-pen-to-square"></i></a>
                            <a href="<?= e(url('/admin/exams/' . $ex['id'] . '/report-cards')) ?>" class="ml-2 text-slate-500 hover:text-brand-600" title="Report cards"><i class="fa-solid fa-file-lines"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (auth()->can('exams.manage')): ?>
    <div class="card p-5">
        <h2 class="font-semibold text-slate-800">Create exam</h2>
        <form method="POST" action="<?= e(url('/admin/exams')) ?>" class="mt-4 space-y-3" data-loading>
            <?= csrf_field() ?>
            <div><label class="form-label required" for="name">Name</label><input id="name" name="name" class="form-input" required></div>
            <div><label class="form-label required" for="exam_type_id">Type</label><select id="exam_type_id" name="exam_type_id" class="form-select" required><?php foreach($examTypes as $t):?><option value="<?=(int)$t['id']?>"><?=e((string)$t['name'])?></option><?php endforeach;?></select></div>
            <div><label class="form-label" for="academic_year_id">Academic year</label><select id="academic_year_id" name="academic_year_id" class="form-select"><option value="">—</option><?php foreach($years as $y):?><option value="<?=(int)$y['id']?>"><?=e((string)$y['name'])?></option><?php endforeach;?></select></div>
            <div><label class="form-label" for="start_date">Start</label><input id="start_date" name="start_date" type="date" class="form-input"></div>
            <div><label class="form-label" for="end_date">End</label><input id="end_date" name="end_date" type="date" class="form-input"></div>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Create exam</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
