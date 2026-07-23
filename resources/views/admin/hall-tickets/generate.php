<?php
/** @var \Core\View\View $this */
/** @var array<string,string> $types */
/** @var array<int,array<string,mixed>> $exams */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $sections */
/** @var array<int,array<string,mixed>> $eligible */
/** @var array<string,mixed> $sel */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/hall-tickets')) ?>" class="hover:text-brand-600">Hall Tickets</a> / Generate<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card mb-5 p-5">
    <h2 class="mb-3 font-semibold text-slate-800">Select exam context</h2>
    <form method="GET" action="<?= e(url('/admin/hall-tickets/generate')) ?>" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div>
            <label class="block text-xs font-semibold text-slate-500">Exam</label>
            <select name="exam_id" class="form-input" required>
                <option value="">Select</option>
                <?php foreach ($exams as $ex): ?><option value="<?= (int) $ex['id'] ?>" <?= (int) $sel['exam_id'] === (int) $ex['id'] ? 'selected' : '' ?>><?= e((string) $ex['name']) ?><?= $ex['year_name'] ? ' (' . e((string) $ex['year_name']) . ')' : '' ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">Class</label>
            <select name="class_id" class="form-input" required>
                <option value="">Select</option>
                <?php foreach ($classes as $cl): ?><option value="<?= (int) $cl['id'] ?>" <?= (int) $sel['class_id'] === (int) $cl['id'] ? 'selected' : '' ?>><?= e((string) $cl['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">Section <span class="text-slate-400">(optional)</span></label>
            <select name="section_id" class="form-input">
                <option value="">All sections</option>
                <?php foreach ($sections as $se): ?><option value="<?= (int) $se['id'] ?>" data-class="<?= (int) $se['class_id'] ?>" <?= (int) ($sel['section_id'] ?? 0) === (int) $se['id'] ? 'selected' : '' ?>><?= e((string) $se['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">Type</label>
            <select name="type" class="form-input">
                <?php foreach ($types as $k => $lbl): ?><option value="<?= e($k) ?>" <?= ($sel['type'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="sm:col-span-4"><button class="btn btn-secondary"><i class="fa-solid fa-users"></i> Load eligible students</button></div>
    </form>
</div>

<?php if ($sel['exam_id'] && $sel['class_id']): ?>
<div class="card overflow-hidden">
    <form method="POST" action="<?= e(url('/admin/hall-tickets')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="exam_id" value="<?= (int) $sel['exam_id'] ?>">
        <input type="hidden" name="class_id" value="<?= (int) $sel['class_id'] ?>">
        <input type="hidden" name="section_id" value="<?= (int) ($sel['section_id'] ?? 0) ?>">
        <input type="hidden" name="type" value="<?= e((string) $sel['type']) ?>">
        <div class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-5 py-4">
            <input type="text" name="center" class="form-input" placeholder="Exam center (optional)">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="signature_required" value="1" class="rounded border-slate-300"> Signature required</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="stamp_required" value="1" class="rounded border-slate-300"> Stamp required</label>
            <button class="btn btn-primary"><i class="fa-solid fa-ticket"></i> Issue selected / all eligible</button>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th class="w-8"><input type="checkbox" onclick="document.querySelectorAll('.stu-cb').forEach(c=>c.checked=this.checked)"></th><th>Roll</th><th>Student</th><th>Admission</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (empty($eligible)): ?>
                    <tr><td colspan="5" class="py-8 text-center text-slate-400">No eligible students for this selection.</td></tr>
                <?php else: foreach ($eligible as $s): ?>
                    <tr>
                        <td><?php if (empty($s['issued'])): ?><input type="checkbox" class="stu-cb rounded border-slate-300" name="student_ids[]" value="<?= (int) $s['id'] ?>"><?php endif; ?></td>
                        <td><?= e((string) ($s['roll_no'] ?: '—')) ?></td>
                        <td class="font-medium text-slate-800"><?= e((string) $s['name']) ?></td>
                        <td class="font-mono text-xs"><?= e((string) $s['admission_no']) ?></td>
                        <td><span class="badge <?= $s['issued'] ? 'badge-green' : 'badge-amber' ?>"><?= $s['issued'] ? 'Issued' : 'Eligible' ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400">Leave all checkboxes unchecked to issue for every eligible student. Already-issued students are skipped (no duplicates).</p>
    </form>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
